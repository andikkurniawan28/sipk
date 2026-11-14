<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Proteksi sederhana: harus sudah login
if (empty($_SESSION)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function hitung(mysqli $conn, string $sql): ?int
{
    try {
        $res = $conn->query($sql);
        return $res ? (int)$res->fetch_row()[0] : null;
    } catch (Throwable $e) {
        return null;
    }
}

// Stok total (semua gudang) dan satuan produk berdasarkan kode
function infoProduk(mysqli $conn, string $kode): array
{
    $stmt = $conn->prepare("
        SELECT p.id, p.nama, p.satuan,
               COALESCE((SELECT SUM(ms.masuk - ms.keluar) FROM mutasi_stok ms WHERE ms.produk_id = p.id), 0) AS stok
        FROM produk p
        WHERE p.kode = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$p) {
        return ['ada' => false, 'stok' => 0, 'satuan' => ''];
    }
    return ['ada' => true, 'id' => (int)$p['id'], 'stok' => (float)$p['stok'], 'satuan' => $p['satuan']];
}

// Harga beli terakhir dari faktur pembelian; jika belum ada pembelian, pakai harga_beli di master
function hargaBeliTerakhir(mysqli $conn, int $produkId): float
{
    $stmt = $conn->prepare("
        SELECT d.harga
        FROM pembelian_langsung_detail d
        JOIN pembelian_langsung pl ON pl.id = d.pembelian_langsung_id
        WHERE d.produk_id = ?
        ORDER BY pl.tanggal DESC, pl.id DESC, d.id DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $produkId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) {
        return (float)$r['harga'];
    }

    $stmt = $conn->prepare("SELECT harga_beli FROM produk WHERE id = ?");
    $stmt->bind_param('i', $produkId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($r['harga_beli'] ?? 0);
}

// Total per bulan selama 12 bulan terakhir (bulan kosong diisi 0)
function trenBulanan(mysqli $conn, string $tabel, array $bulanList, string $awal): array
{
    $hasil = [];
    try {
        $res = $conn->query("
            SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bulan, COALESCE(SUM(grand_total), 0) AS total
            FROM `$tabel`
            WHERE tanggal >= '$awal'
            GROUP BY bulan
        ");
        while ($r = $res->fetch_assoc()) {
            $hasil[$r['bulan']] = (float)$r['total'];
        }
    } catch (Throwable $e) {
        // tabel bermasalah: grafik tampil kosong
    }

    $out = [];
    foreach ($bulanList as $b) {
        $out[] = $hasil[$b] ?? 0;
    }
    return $out;
}

// Total qty produk pada tanggal tertentu dari tabel header + detail
function qtyHariIni(mysqli $conn, string $header, string $detail, string $fk, string $kode, string $tanggal): float
{
    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(d.qty), 0)
            FROM `$detail` d
            JOIN `$header` h ON h.id = d.`$fk`
            JOIN produk p ON p.id = d.produk_id
            WHERE h.tanggal = ? AND p.kode = ?
        ");
        $stmt->bind_param('ss', $tanggal, $kode);
        $stmt->execute();
        $qty = (float)$stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $qty;
    } catch (Throwable $e) {
        return 0.0;
    }
}

try {

    $hariIni = date('Y-m-d');
    $vco     = infoProduk($conn, 'VCO');

    $kelapa = infoProduk($conn, 'KTU');
    $kelapa['harga_beli'] = $kelapa['ada'] ? hargaBeliTerakhir($conn, $kelapa['id']) : 0;

    // Periode 12 bulan terakhir
    $awalBulan = date('Y-m-01');
    $awal12    = date('Y-m-01', strtotime('-11 months', strtotime($awalBulan)));
    $namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $bulanList = [];
    $label     = [];
    for ($i = 0; $i < 12; $i++) {
        $t           = strtotime("+$i months", strtotime($awal12));
        $bulanList[] = date('Y-m', $t);
        $label[]     = $namaBulan[(int)date('n', $t) - 1] . ' ' . date('y', $t);
    }

    echo json_encode([
        'pemasok'    => hitung($conn, "SELECT COUNT(*) FROM pemasok"),
        'pelanggan'  => hitung($conn, "SELECT COUNT(*) FROM pelanggan"),
        'wilayah'    => hitung($conn, "SELECT COUNT(*) FROM wilayah"),
        'kelapa'     => $kelapa,
        'vco'        => infoProduk($conn, 'VCO'),
        'sabut'      => infoProduk($conn, 'SBK'),
        'tempurung'  => infoProduk($conn, 'TPK'),
        'tren' => [
            'label'     => $label,
            'penjualan' => trenBulanan($conn, 'penjualan', $bulanList, $awal12),
            'pembelian' => trenBulanan($conn, 'pembelian_langsung', $bulanList, $awal12),
        ],
        'hari_ini' => [
            'kelapa_diolah'  => qtyHariIni($conn, 'pengambilan_bahan', 'pengambilan_bahan_detail', 'pengambilan_bahan_id', 'KTU', $hariIni),
            'vco_diproduksi' => qtyHariIni($conn, 'serah_terima_produksi', 'serah_terima_produksi_detail', 'serah_terima_produksi_id', 'VCO', $hariIni),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal memuat data dashboard', 'detail' => $e->getMessage()]);
}