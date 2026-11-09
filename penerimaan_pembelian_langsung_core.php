<?php

require_once 'penerimaan_pembelian_langsung_core.php';

const AKUN_PERSEDIAAN_BAHAN_BAKU = 4;
const AKUN_PERSEDIAAN_BELUM_DITERIMA = 3; 

const KOLOM_URUT_AWAL_PENERIMAAN = 2;

const KOLOM_TABEL_PENERIMAAN = [
    ['judul' => 'No', 'lebar' => '50px', 'urut' => false],
    ['judul' => 'Tanggal'],
    ['judul' => 'Kode'],
    ['judul' => 'Faktur Pembelian'],
    ['judul' => 'Gudang'],
    ['judul' => 'Admin'],
    ['judul' => 'Detail', 'urut' => false],
    ['judul' => 'Timestamp'],
    ['judul' => 'Aksi', 'lebar' => '120px', 'rata' => 'center', 'urut' => false],
];

const DEFINISI_TABEL_PENERIMAAN = [
    'tabelUtama'  => 'penerimaan_pembelian_langsung',
    'select'      => 'pn.id, pn.tanggal, pn.kode, pn.timestamp, pl.kode AS kode_pembelian, g.nama AS nama_gudang, u.nama AS nama_admin',
    'dari'        => 'FROM penerimaan_pembelian_langsung pn JOIN pembelian_langsung pl ON pl.id = pn.pembelian_langsung_id JOIN gudang g ON g.id = pn.gudang_id JOIN `user` u ON u.id = pn.user_id',
    'kolomCari'   => ['pn.kode', 'pl.kode', 'g.nama', 'u.nama'],
    'kolomUrut'   => [
        0 => 'pn.id',
        1 => 'pn.tanggal',
        2 => 'pn.kode',
        3 => 'pl.kode',
        4 => 'g.nama',
        5 => 'u.nama',
        7 => 'pn.timestamp',
    ],
    'urutDefault' => 'pn.tanggal',
    'kolomId'     => 'pn.id',
];

function ambilHeaderPenerimaanDariForm(array $form): array
{
    return [
        'tanggal'               => ambilTeksDariForm($form, 'tanggal'),
        'kode'                  => ambilTeksDariForm($form, 'kode'),
        'pembelian_langsung_id' => ambilIdDariForm($form, 'pembelian_langsung_id'),
        'gudang_id'             => ambilIdDariForm($form, 'gudang_id'),
    ];
}

function pastikanHeaderPenerimaanValid(array $header): void
{
    pastikanFormatTanggalValid($header['tanggal']);
    pastikanTidakKosong($header['kode'], 'Kode penerimaan');
    pastikanTerpilih($header['pembelian_langsung_id'], 'Pembelian');
    pastikanTerpilih($header['gudang_id'], 'Gudang');
}

function ambilKodePembelian(mysqli $conn, int $pembelianId): string
{
    $stmt = $conn->prepare("SELECT kode FROM pembelian_langsung WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $pembelianId);
    $stmt->execute();
    $pembelian = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pembelian) {
        throw new DomainException('Pembelian tidak ditemukan.');
    }

    return $pembelian['kode'];
}

function ambilHargaPembelianPerProduk(mysqli $conn, int $pembelianId): array
{
    $stmt = $conn->prepare("
        SELECT produk_id, SUM(total) / SUM(qty) AS harga
        FROM pembelian_langsung_detail
        WHERE pembelian_langsung_id = ?
        GROUP BY produk_id
        HAVING SUM(qty) > 0
    ");
    $stmt->bind_param('i', $pembelianId);
    $stmt->execute();
    $hasil = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $hargaPerProduk = [];

    foreach ($hasil as $baris) {
        $hargaPerProduk[(int)$baris['produk_id']] = (float)$baris['harga'];
    }

    return $hargaPerProduk;
}

function ambilBarisProdukPenerimaanDariForm(array $form, array $hargaPerProduk): array
{
    $produkIds = $form['produk_id'] ?? [];
    $qtys      = $form['qty'] ?? [];

    if (!is_array($produkIds) || !is_array($qtys)) {
        throw new DomainException('Struktur data produk tidak valid.');
    }

    $barisProduk = [];

    foreach ($produkIds as $i => $produkId) {
        $produkId = (int)$produkId;

        if ($produkId <= 0) {
            continue;
        }

        $qty = (float)($qtys[$i] ?? 0);
        pastikanTidakNegatif($qty, 'Qty');

        if ($qty <= 0) {
            continue;   // produk yang tidak diterima pada penerimaan ini
        }

        if (!isset($hargaPerProduk[$produkId])) {
            throw new DomainException('Ada produk yang tidak terdapat pada pembelian ini.');
        }

        $harga = $hargaPerProduk[$produkId];

        $barisProduk[] = [
            'produk_id' => $produkId,
            'qty'       => $qty,
            'harga'     => $harga,
            'total'     => round($qty * $harga, 2),
        ];
    }

    if (empty($barisProduk)) {
        throw new DomainException('Item produk penerimaan tidak ditemukan.');
    }

    return $barisProduk;
}

function keteranganPenerimaan(array $header, string $kodePembelian): string
{
    return "Diterima {$header['kode']} dari Pembelian Langsung {$kodePembelian}";
}

function susunBarisAkunPenerimaan(float $totalPenerimaan): array
{
    return hanyaBarisBernilai([
        barisAkunDebit(AKUN_PERSEDIAAN_BAHAN_BAKU, $totalPenerimaan),
        barisAkunKredit(AKUN_PERSEDIAAN_BELUM_DITERIMA, $totalPenerimaan),
    ]);
}

function simpanHeaderPenerimaan(mysqli $conn, array $header, float $totalPenerimaan, int $userId): int
{
    $stmt = $conn->prepare(
        "INSERT INTO penerimaan_pembelian_langsung (tanggal, kode, pembelian_langsung_id, gudang_id, user_id, total)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'ssiiid',
        $header['tanggal'],
        $header['kode'],
        $header['pembelian_langsung_id'],
        $header['gudang_id'],
        $userId,
        $totalPenerimaan
    );
    $stmt->execute();
    $penerimaanId = $conn->insert_id;
    $stmt->close();

    return $penerimaanId;
}

function simpanDetailPenerimaan(mysqli $conn, int $penerimaanId, array $barisProduk): void
{
    $baris = [];

    foreach ($barisProduk as $produk) {
        $baris[] = [$penerimaanId, $produk['produk_id'], $produk['qty'], $produk['harga'], $produk['total']];
    }

    insertBanyakBaris(
        $conn,
        'penerimaan_pembelian_langsung_detail',
        ['penerimaan_pembelian_langsung_id', 'produk_id', 'qty', 'harga', 'total'],
        'iiddd',
        $baris
    );
}

function simpanMutasiStokMasuk(
    mysqli $conn,
    int $penerimaanId,
    array $header,
    string $keterangan,
    array $barisProduk,
    int $userId
): void {
    $baris = [];

    foreach ($barisProduk as $produk) {
        $baris[] = [
            $penerimaanId,
            $header['tanggal'],
            $keterangan,
            $header['gudang_id'],
            $produk['produk_id'],
            $produk['qty'],
            0.0,
            $userId,
        ];
    }

    insertBanyakBaris(
        $conn,
        'mutasi_stok',
        ['penerimaan_pembelian_langsung_id', 'tanggal', 'keterangan', 'gudang_id', 'produk_id', 'masuk', 'keluar', 'user_id'],
        'issiiddi',
        $baris
    );
}

function ambilDetailProdukPerPenerimaan(mysqli $conn, array $penerimaanIds): array
{
    if (empty($penerimaanIds)) {
        return [];
    }

    $daftarId = implode(',', array_map('intval', $penerimaanIds));

    $hasil = $conn->query("
        SELECT d.penerimaan_pembelian_langsung_id, pr.kode, pr.nama AS nama_produk, pr.satuan, d.qty, d.harga, d.total
        FROM penerimaan_pembelian_langsung_detail d
        JOIN produk pr ON pr.id = d.produk_id
        WHERE d.penerimaan_pembelian_langsung_id IN ({$daftarId})
        ORDER BY d.id
    ");

    return kelompokkanBerdasarkanKolom($hasil, 'penerimaan_pembelian_langsung_id');
}

function htmlTombolAksiPenerimaan(int $penerimaanId): string
{
    $tombolLihat = '<a href="penerimaan_pembelian_langsung_lihat.php?id=' . $penerimaanId
                 . '" class="btn btn-info btn-sm" title="Lihat Detail"><i class="fas fa-eye"></i></a>';

    if (!canAccess('penerimaan_pembelian_langsung_hapus.php')) {
        return $tombolLihat;
    }

    return $tombolLihat . ' <a href="penerimaan_pembelian_langsung_hapus.php?id=' . $penerimaanId
         . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>';
}

function barisTabelPenerimaan(int $nomor, array $penerimaan, array $daftarProduk): array
{
    return [
        $nomor,
        h(tanggal_indo($penerimaan['tanggal'])),
        h($penerimaan['kode']),
        h($penerimaan['kode_pembelian']),
        h($penerimaan['nama_gudang']),
        h($penerimaan['nama_admin']),
        htmlDaftarProdukPenerimaan($daftarProduk),
        h($penerimaan['timestamp']),
        htmlTombolAksiPenerimaan((int)$penerimaan['id']),
    ];
}

// Daftar produk penerimaan: hanya nama dan qty, tanpa harga/total
function htmlDaftarProdukPenerimaan(array $daftarProduk): string
{
    if (empty($daftarProduk)) {
        return '-';
    }

    $html = '<ul class="mb-0 pl-3">';

    foreach ($daftarProduk as $p) {
        $qty   = rtrim(rtrim(number_format((float)$p['qty'], 2, ',', '.'), '0'), ',');
        $html .= '<li>' . h($p['kode'] . ' - ' . $p['nama_produk']) . ': ' . h($qty . ' ' . $p['satuan']) . '</li>';
    }

    return $html . '</ul>';
}

function ambilPenerimaanLengkap(mysqli $conn, int $penerimaanId): array
{
    $stmt = $conn->prepare("
        SELECT pn.*, pl.kode AS kode_pembelian, p.nama AS pemasok_nama,
               g.nama AS gudang_nama, g.alamat AS gudang_alamat, u.nama AS dibuat_oleh
        FROM penerimaan_pembelian_langsung pn
        JOIN pembelian_langsung pl ON pl.id = pn.pembelian_langsung_id
        JOIN pemasok p ON p.id = pl.pemasok_id
        JOIN gudang g ON g.id = pn.gudang_id
        JOIN `user` u ON u.id = pn.user_id
        WHERE pn.id = ? LIMIT 1
    ");
    $stmt->bind_param('i', $penerimaanId);
    $stmt->execute();
    $penerimaan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$penerimaan) {
        throw new DomainException('Data penerimaan tidak ada.');
    }

    return $penerimaan;
}

function ambilDetailProdukPenerimaan(mysqli $conn, int $penerimaanId): array
{
    return ambilDetailProdukPerPenerimaan($conn, [$penerimaanId])[$penerimaanId] ?? [];
}

function kunciBarisPenerimaan(mysqli $conn, int $penerimaanId): void
{
    $stmt = $conn->prepare("SELECT id FROM penerimaan_pembelian_langsung WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $penerimaanId);
    $stmt->execute();
    $ditemukan = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$ditemukan) {
        throw new DomainException('Data penerimaan tidak ditemukan.');
    }
}

function pastikanPenerimaanBelumDibayar(mysqli $conn, int $penerimaanId): void
{
    $jumlahPembayaran = hitungBaris(
        $conn,
        'SELECT COUNT(*) FROM pembayaran_pembelian_langsung WHERE penerimaan_pembelian_langsung_id = ?',
        'i',
        [$penerimaanId]
    );

    if ($jumlahPembayaran > 0) {
        throw new DomainException('Penerimaan ini sudah dibayar, jadi tidak bisa dihapus. Hapus data pembayarannya terlebih dahulu.');
    }
}

function hapusBarisPenerimaan(mysqli $conn, int $penerimaanId): void
{
    $stmt = $conn->prepare("DELETE FROM penerimaan_pembelian_langsung WHERE id = ?");
    $stmt->bind_param('i', $penerimaanId);
    $stmt->execute();
    $stmt->close();
}

// function hapusPenerimaan(mysqli $conn, int $penerimaanId): void
// {
//     jalankanDalamTransaksi($conn, function () use ($conn, $penerimaanId) {
//         kunciBarisPenerimaan($conn, $penerimaanId);
//         pastikanPenerimaanBelumDibayar($conn, $penerimaanId);
//         hapusBarisPenerimaan($conn, $penerimaanId);
//     });
// }

function perbaruiHargaPokokRataRata(mysqli $conn, array $barisProduk): void
{
    // Gabungkan baris dengan produk yang sama agar rata-rata dihitung sekali per produk
    $perProduk = [];

    foreach ($barisProduk as $baris) {
        $id = $baris['produk_id'];
        $perProduk[$id]['qty']   = ($perProduk[$id]['qty'] ?? 0.0) + $baris['qty'];
        $perProduk[$id]['total'] = ($perProduk[$id]['total'] ?? 0.0) + $baris['total'];
    }

    $stmtPokok = $conn->prepare("SELECT harga_pokok FROM produk WHERE id = ? FOR UPDATE");
    $stmtStok  = $conn->prepare("SELECT COALESCE(SUM(masuk - keluar), 0) AS stok FROM mutasi_stok WHERE produk_id = ?");
    $stmtUbah  = $conn->prepare("UPDATE produk SET harga_pokok = ? WHERE id = ?");

    foreach ($perProduk as $produkId => $masuk) {
        if ($masuk['qty'] <= 0) {
            continue;
        }

        // Kunci baris produk dulu agar dua penerimaan bersamaan tidak saling menimpa
        $stmtPokok->bind_param('i', $produkId);
        $stmtPokok->execute();
        $produk = $stmtPokok->get_result()->fetch_assoc();

        if (!$produk) {
            throw new DomainException('Produk tidak ditemukan (ID produk: ' . $produkId . ').');
        }
        $hppLama = (float)$produk['harga_pokok'];

        $stmtStok->bind_param('i', $produkId);
        $stmtStok->execute();
        $stokLama = max(0.0, (float)$stmtStok->get_result()->fetch_assoc()['stok']);

        $hppBaru = round(($stokLama * $hppLama + $masuk['total']) / ($stokLama + $masuk['qty']), 2);

        $stmtUbah->bind_param('di', $hppBaru, $produkId);
        $stmtUbah->execute();
    }

    $stmtPokok->close();
    $stmtStok->close();
    $stmtUbah->close();
}

function ambilSisaQtyPembelian(mysqli $conn, int $pembelianId): array
{
    $stmt = $conn->prepare("
        SELECT d.produk_id, d.qty_pesan, COALESCE(t.qty_terima, 0) AS qty_terima
        FROM (
            SELECT produk_id, SUM(qty) AS qty_pesan
            FROM pembelian_langsung_detail
            WHERE pembelian_langsung_id = ?
            GROUP BY produk_id
        ) d
        LEFT JOIN (
            SELECT pd.produk_id, SUM(pd.qty) AS qty_terima
            FROM penerimaan_pembelian_langsung_detail pd
            JOIN penerimaan_pembelian_langsung pn ON pn.id = pd.penerimaan_pembelian_langsung_id
            WHERE pn.pembelian_langsung_id = ?
            GROUP BY pd.produk_id
        ) t ON t.produk_id = d.produk_id
    ");
    $stmt->bind_param('ii', $pembelianId, $pembelianId);
    $stmt->execute();
    $hasil = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $sisaPerProduk = [];

    foreach ($hasil as $baris) {
        $sisaPerProduk[(int)$baris['produk_id']] = [
            'pesan'  => (float)$baris['qty_pesan'],
            'terima' => (float)$baris['qty_terima'],
            'sisa'   => round((float)$baris['qty_pesan'] - (float)$baris['qty_terima'], 2),
        ];
    }

    return $sisaPerProduk;
}

function kunciBarisPembelian(mysqli $conn, int $pembelianId): void
{
    $stmt = $conn->prepare("SELECT id FROM pembelian_langsung WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $pembelianId);
    $stmt->execute();
    $ditemukan = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$ditemukan) {
        throw new DomainException('Pembelian tidak ditemukan.');
    }
}

function pastikanQtyTidakMelebihiSisa(mysqli $conn, int $pembelianId, array $barisProduk): void
{
    $sisaPerProduk = ambilSisaQtyPembelian($conn, $pembelianId);

    $qtyBaru = [];
    foreach ($barisProduk as $baris) {
        $qtyBaru[$baris['produk_id']] = ($qtyBaru[$baris['produk_id']] ?? 0.0) + $baris['qty'];
    }

    foreach ($qtyBaru as $produkId => $qty) {
        $sisa = $sisaPerProduk[$produkId]['sisa'] ?? 0.0;

        if ($qty > $sisa + 0.0001) {
            throw new DomainException(
                'Qty penerimaan melebihi sisa pesanan (ID produk ' . $produkId . ': sisa ' . $sisa . ', diinput ' . $qty . ').'
            );
        }
    }
}

function ambilPembelianBelumLengkapDiterima(mysqli $conn): array
{
    return $conn->query("
        SELECT pl.id, pl.kode, pl.tanggal
        FROM pembelian_langsung pl
        WHERE EXISTS (
            SELECT 1
            FROM pembelian_langsung_detail d
            WHERE d.pembelian_langsung_id = pl.id
            GROUP BY d.produk_id
            HAVING SUM(d.qty) - COALESCE((
                SELECT SUM(pd.qty)
                FROM penerimaan_pembelian_langsung_detail pd
                JOIN penerimaan_pembelian_langsung pn ON pn.id = pd.penerimaan_pembelian_langsung_id
                WHERE pn.pembelian_langsung_id = pl.id AND pd.produk_id = d.produk_id
            ), 0) > 0.0001
        )
        ORDER BY pl.tanggal DESC, pl.id DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

// Ringkasan per produk dari penerimaan yang akan dihapus
function ambilRingkasanPenerimaanUntukHapus(mysqli $conn, int $penerimaanId): array
{
    $stmt = $conn->prepare("
        SELECT pn.gudang_id, d.produk_id, SUM(d.qty) AS qty, SUM(d.total) AS nilai
        FROM penerimaan_pembelian_langsung pn
        JOIN penerimaan_pembelian_langsung_detail d ON d.penerimaan_pembelian_langsung_id = pn.id
        WHERE pn.id = ?
        GROUP BY pn.gudang_id, d.produk_id
        ORDER BY d.produk_id
    ");
    $stmt->bind_param('i', $penerimaanId);
    $stmt->execute();
    $hasil = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $hasil;
}

// Tolak jika stok gudang akan negatif setelah penerimaan ini dihapus
function pastikanStokCukupUntukHapus(mysqli $conn, array $ringkasan): void
{
    $stmtStok = $conn->prepare("
        SELECT COALESCE(SUM(masuk - keluar), 0) AS stok
        FROM mutasi_stok
        WHERE produk_id = ? AND gudang_id = ?
    ");

    foreach ($ringkasan as $r) {
        $produkId = (int)$r['produk_id'];
        $gudangId = (int)$r['gudang_id'];

        $stmtStok->bind_param('ii', $produkId, $gudangId);
        $stmtStok->execute();
        $stok = (float)$stmtStok->get_result()->fetch_assoc()['stok'];

        if ($stok - (float)$r['qty'] < -0.0001) {
            $stmtStok->close();
            throw new DomainException(
                'Penerimaan tidak bisa dihapus: stok produk ID ' . $produkId
                . ' di gudang ini sudah terpakai (stok sekarang ' . $stok . ', diterima ' . (float)$r['qty'] . ').'
            );
        }
    }

    $stmtStok->close();
}

// Keluarkan nilai penerimaan dari rata-rata bergerak. Harus dipanggil SEBELUM mutasi stok dihapus.
function kembalikanHargaPokokSebelumHapus(mysqli $conn, array $ringkasan): void
{
    $perProduk = [];
    foreach ($ringkasan as $r) {
        $id = (int)$r['produk_id'];
        $perProduk[$id]['qty']   = ($perProduk[$id]['qty'] ?? 0.0) + (float)$r['qty'];
        $perProduk[$id]['nilai'] = ($perProduk[$id]['nilai'] ?? 0.0) + (float)$r['nilai'];
    }

    $stmtPokok = $conn->prepare("SELECT harga_pokok FROM produk WHERE id = ? FOR UPDATE");
    $stmtStok  = $conn->prepare("SELECT COALESCE(SUM(masuk - keluar), 0) AS stok FROM mutasi_stok WHERE produk_id = ?");
    $stmtUbah  = $conn->prepare("UPDATE produk SET harga_pokok = ? WHERE id = ?");

    foreach ($perProduk as $produkId => $keluar) {
        $stmtPokok->bind_param('i', $produkId);
        $stmtPokok->execute();
        $produk = $stmtPokok->get_result()->fetch_assoc();
        if (!$produk) {
            continue;
        }
        $hppSekarang = (float)$produk['harga_pokok'];

        $stmtStok->bind_param('i', $produkId);
        $stmtStok->execute();
        $stokSekarang = (float)$stmtStok->get_result()->fetch_assoc()['stok'];

        $sisaQty = $stokSekarang - $keluar['qty'];

        // Kalau stok jadi habis, harga pokok dibiarkan (belum ada dasar hitung baru)
        if ($sisaQty > 0.0001) {
            $hppBaru = round(max(0.0, ($stokSekarang * $hppSekarang - $keluar['nilai']) / $sisaQty), 2);
            $stmtUbah->bind_param('di', $hppBaru, $produkId);
            $stmtUbah->execute();
        }
    }

    $stmtPokok->close();
    $stmtStok->close();
    $stmtUbah->close();
}

function hapusJurnalDanMutasiPenerimaan(mysqli $conn, int $penerimaanId): void
{
    $stmt = $conn->prepare("DELETE FROM buku_besar WHERE penerimaan_pembelian_langsung_id = ?");
    $stmt->bind_param('i', $penerimaanId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM mutasi_stok WHERE penerimaan_pembelian_langsung_id = ?");
    $stmt->bind_param('i', $penerimaanId);
    $stmt->execute();
    $stmt->close();
}

function hapusPenerimaan(mysqli $conn, int $penerimaanId): void
{
    jalankanDalamTransaksi($conn, function () use ($conn, $penerimaanId) {
        kunciBarisPenerimaan($conn, $penerimaanId);

        $ringkasan = ambilRingkasanPenerimaanUntukHapus($conn, $penerimaanId);

        pastikanStokCukupUntukHapus($conn, $ringkasan);
        kembalikanHargaPokokSebelumHapus($conn, $ringkasan);   // sebelum mutasi stok dihapus
        hapusJurnalDanMutasiPenerimaan($conn, $penerimaanId);
        hapusBarisPenerimaan($conn, $penerimaanId);            // detail ikut terhapus lewat FK cascade
    });
}