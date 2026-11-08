<?php
/**
 * api_dashboard.php
 * Endpoint JSON untuk dashboard (dipanggil dari home.php).
 * Butuh $conn (mysqli) dari config.php. session_start() sudah ada di config.php.
 */
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Proteksi sederhana: harus sudah login (session terisi).
// Jika config/login Anda memakai key tertentu, ganti dengan mis. empty($_SESSION['user_id'])
if (empty($_SESSION)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

/* ---------- Helper ---------- */
function rows(mysqli $conn, string $sql): array
{
    $res = $conn->query($sql);
    if (!$res) {
        throw new Exception($conn->error);
    }
    return $res->fetch_all(MYSQLI_ASSOC);
}

function nilai(mysqli $conn, string $sql): float
{
    $r = rows($conn, $sql);
    return $r ? (float) array_values($r[0])[0] : 0.0;
}

function pertumbuhan(float $sekarang, float $lalu): ?float
{
    if ($lalu == 0) {
        return null;
    }
    return round((($sekarang - $lalu) / abs($lalu)) * 100, 1);
}

/** Isi bulan kosong dengan 0 agar grafik utuh 12 bulan */
function deretBulanan(array $data, array $bulanList, string $kolom): array
{
    $map = [];
    foreach ($data as $d) {
        $map[$d['bulan']] = (float) $d[$kolom];
    }
    $out = [];
    foreach ($bulanList as $b) {
        $out[] = $map[$b] ?? 0;
    }
    return $out;
}

try {
    /* ---------- Periode ---------- */
    $awalBulan      = date('Y-m-01');
    $akhirBulan     = date('Y-m-t');
    $awalBulanLalu  = date('Y-m-01', strtotime('-1 month', strtotime($awalBulan)));
    $akhirBulanLalu = date('Y-m-t', strtotime($awalBulanLalu));
    $awal12         = date('Y-m-01', strtotime('-11 months', strtotime($awalBulan)));

    $bulanList = [];
    $label     = [];
    $namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    for ($i = 0; $i < 12; $i++) {
        $t           = strtotime("+$i months", strtotime($awal12));
        $bulanList[] = date('Y-m', $t);
        $label[]     = $namaBulan[(int) date('n', $t) - 1] . ' ' . date('y', $t);
    }

    /* ---------- KARTU RINGKASAN ---------- */
    $penjualanIni  = nilai($conn, "SELECT COALESCE(SUM(grand_total),0) FROM penjualan WHERE tanggal BETWEEN '$awalBulan' AND '$akhirBulan'");
    $penjualanLalu = nilai($conn, "SELECT COALESCE(SUM(grand_total),0) FROM penjualan WHERE tanggal BETWEEN '$awalBulanLalu' AND '$akhirBulanLalu'");

    $pembelianIni  = nilai($conn, "SELECT COALESCE(SUM(grand_total),0) FROM pembelian_langsung WHERE tanggal BETWEEN '$awalBulan' AND '$akhirBulan'");
    $pembelianLalu = nilai($conn, "SELECT COALESCE(SUM(grand_total),0) FROM pembelian_langsung WHERE tanggal BETWEEN '$awalBulanLalu' AND '$akhirBulanLalu'");

    // Laba kotor = subtotal penjualan - subtotal harga pokok
    $labaIni  = nilai($conn, "SELECT COALESCE(SUM(subtotal - subtotal_pokok),0) FROM penjualan WHERE tanggal BETWEEN '$awalBulan' AND '$akhirBulan'");
    $labaLalu = nilai($conn, "SELECT COALESCE(SUM(subtotal - subtotal_pokok),0) FROM penjualan WHERE tanggal BETWEEN '$awalBulanLalu' AND '$akhirBulanLalu'");

    $margin = $penjualanIni > 0 ? round(($labaIni / $penjualanIni) * 100, 1) : 0;

    $piutang = nilai($conn, "
        SELECT COALESCE(SUM(p.grand_total - COALESCE(b.bayar,0)),0)
        FROM penjualan p
        LEFT JOIN (SELECT penjualan_id, SUM(total) bayar FROM pembayaran_penjualan GROUP BY penjualan_id) b ON b.penjualan_id = p.id
        WHERE p.grand_total - COALESCE(b.bayar,0) > 0");

    $hutang = nilai($conn, "
        SELECT COALESCE(SUM(pl.grand_total - COALESCE(b.bayar,0)),0)
        FROM pembelian_langsung pl
        LEFT JOIN (
            SELECT pn.pembelian_langsung_id, SUM(py.total) bayar
            FROM pembayaran_pembelian_langsung py
            JOIN penerimaan_pembelian_langsung pn ON pn.id = py.penerimaan_pembelian_langsung_id
            GROUP BY pn.pembelian_langsung_id
        ) b ON b.pembelian_langsung_id = pl.id
        WHERE pl.grand_total - COALESCE(b.bayar,0) > 0");

    // Kas & bank: akun bersaldo normal Debit yang namanya mengandung kas/bank
    $kasBank = nilai($conn, "
        SELECT COALESCE(SUM(bb.debit - bb.kredit),0)
        FROM buku_besar bb
        JOIN akun a ON a.id = bb.akun_id
        WHERE a.saldo_normal = 'Debit'
          AND (a.nama LIKE '%kas%' OR a.nama LIKE '%bank%' OR a.kelompok LIKE '%kas%' OR a.kelompok LIKE '%bank%')");

    // Nilai persediaan = stok akhir x harga pokok produk
    $persediaan = nilai($conn, "
        SELECT COALESCE(SUM(s.qty * p.harga_pokok),0)
        FROM (SELECT produk_id, SUM(masuk - keluar) qty FROM mutasi_stok GROUP BY produk_id) s
        JOIN produk p ON p.id = s.produk_id");

    // Potensi penjualan dari kebutuhan pelanggan yang belum closing
    $potensi = nilai($conn, "
        SELECT COALESCE(SUM(k.qty * p.harga_jual),0)
        FROM kebutuhan k
        JOIN pelanggan pl ON pl.id = k.pelanggan_id
        JOIN produk p ON p.id = k.produk_id
        WHERE pl.status <> 'Closing'");

    $transaksiIni = (int) nilai($conn, "SELECT COUNT(*) FROM penjualan WHERE tanggal BETWEEN '$awalBulan' AND '$akhirBulan'");

    /* ---------- TREN 12 BULAN ---------- */
    $tPenjualan = rows($conn, "
        SELECT DATE_FORMAT(tanggal,'%Y-%m') bulan, SUM(grand_total) total, SUM(subtotal - subtotal_pokok) laba
        FROM penjualan WHERE tanggal >= '$awal12' GROUP BY bulan");
    $tPembelian = rows($conn, "
        SELECT DATE_FORMAT(tanggal,'%Y-%m') bulan, SUM(grand_total) total
        FROM pembelian_langsung WHERE tanggal >= '$awal12' GROUP BY bulan");

    $tren = [
        'label'     => $label,
        'penjualan' => deretBulanan($tPenjualan, $bulanList, 'total'),
        'pembelian' => deretBulanan($tPembelian, $bulanList, 'total'),
        'laba'      => deretBulanan($tPenjualan, $bulanList, 'laba'),
    ];

    /* ---------- PERINGKAT ---------- */
    $topProduk = rows($conn, "
        SELECT pr.nama, SUM(d.qty) qty, SUM(d.total) omzet, SUM(d.total - d.qty * d.harga_pokok) laba
        FROM penjualan_detail d
        JOIN penjualan pj ON pj.id = d.penjualan_id
        JOIN produk pr ON pr.id = d.produk_id
        WHERE pj.tanggal >= '$awal12'
        GROUP BY pr.id, pr.nama ORDER BY omzet DESC LIMIT 5");

    $topPelanggan = rows($conn, "
        SELECT pl.nama, COUNT(pj.id) transaksi, SUM(pj.grand_total) total
        FROM penjualan pj JOIN pelanggan pl ON pl.id = pj.pelanggan_id
        WHERE pj.tanggal >= '$awal12'
        GROUP BY pl.id, pl.nama ORDER BY total DESC LIMIT 5");

    $topPemasok = rows($conn, "
        SELECT ps.nama, COUNT(pb.id) transaksi, SUM(pb.grand_total) total
        FROM pembelian_langsung pb JOIN pemasok ps ON ps.id = pb.pemasok_id
        WHERE pb.tanggal >= '$awal12'
        GROUP BY ps.id, ps.nama ORDER BY total DESC LIMIT 5");

    /* ---------- PIUTANG PER PELANGGAN ---------- */
    $piutangList = rows($conn, "
        SELECT pl.nama, SUM(p.grand_total - COALESCE(b.bayar,0)) sisa
        FROM penjualan p
        JOIN pelanggan pl ON pl.id = p.pelanggan_id
        LEFT JOIN (SELECT penjualan_id, SUM(total) bayar FROM pembayaran_penjualan GROUP BY penjualan_id) b ON b.penjualan_id = p.id
        WHERE p.grand_total - COALESCE(b.bayar,0) > 0
        GROUP BY pl.id, pl.nama ORDER BY sisa DESC LIMIT 5");

    /* ---------- PIPELINE PELANGGAN ---------- */
    $pipeline = rows($conn, "SELECT status, COUNT(*) jumlah FROM pelanggan GROUP BY status");

    /* ---------- STOK ---------- */
    $stokGudang = rows($conn, "
        SELECT g.nama, COALESCE(SUM(s.qty * p.harga_pokok),0) nilai, COALESCE(SUM(s.qty),0) qty
        FROM gudang g
        LEFT JOIN (SELECT gudang_id, produk_id, SUM(masuk - keluar) qty FROM mutasi_stok GROUP BY gudang_id, produk_id) s ON s.gudang_id = g.id
        LEFT JOIN produk p ON p.id = s.produk_id
        GROUP BY g.id, g.nama ORDER BY nilai DESC");

    $stokMinus = rows($conn, "
        SELECT g.nama gudang, p.nama produk, p.satuan, s.qty
        FROM (SELECT gudang_id, produk_id, SUM(masuk - keluar) qty FROM mutasi_stok GROUP BY gudang_id, produk_id HAVING qty < 0) s
        JOIN gudang g ON g.id = s.gudang_id
        JOIN produk p ON p.id = s.produk_id
        ORDER BY s.qty ASC LIMIT 10");

    /* ---------- TRANSAKSI TERBARU ---------- */
    $terbaru = rows($conn, "
        SELECT pj.tanggal, pj.kode, pl.nama pelanggan, pj.grand_total
        FROM penjualan pj JOIN pelanggan pl ON pl.id = pj.pelanggan_id
        ORDER BY pj.tanggal DESC, pj.id DESC LIMIT 8");

    echo json_encode([
        'periode' => ['bulan_ini' => $awalBulan, 'dari_12_bulan' => $awal12],
        'kartu' => [
            'penjualan'  => ['nilai' => $penjualanIni, 'tumbuh' => pertumbuhan($penjualanIni, $penjualanLalu)],
            'pembelian'  => ['nilai' => $pembelianIni, 'tumbuh' => pertumbuhan($pembelianIni, $pembelianLalu)],
            'laba_kotor' => ['nilai' => $labaIni, 'tumbuh' => pertumbuhan($labaIni, $labaLalu), 'margin' => $margin],
            'transaksi'  => $transaksiIni,
            'piutang'    => $piutang,
            'hutang'     => $hutang,
            'kas_bank'   => $kasBank,
            'persediaan' => $persediaan,
            'potensi'    => $potensi,
        ],
        'tren'          => $tren,
        'top_produk'    => $topProduk,
        'top_pelanggan' => $topPelanggan,
        'top_pemasok'   => $topPemasok,
        'piutang_list'  => $piutangList,
        'pipeline'      => $pipeline,
        'stok_gudang'   => $stokGudang,
        'stok_minus'    => $stokMinus,
        'terbaru'       => $terbaru,
    ], JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal memuat data dashboard', 'detail' => $e->getMessage()]);
}