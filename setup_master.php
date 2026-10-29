<?php

require_once 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* ==========================================================================
 * HELPER
 * ========================================================================== */

function halamanSebelumnya(): string
{
    $fallback = 'index.php';
    $referer  = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer === '') {
        return $fallback;
    }

    $bagian = parse_url($referer);
    if (!$bagian || empty($bagian['host'])) {
        return $fallback;
    }

    $hostSaya = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if (strcasecmp($bagian['host'], $hostSaya) !== 0) {
        return $fallback;
    }

    $path = $bagian['path'] ?? '/';
    if (basename($path) === basename(__FILE__)) {
        return $fallback;
    }

    return $path . (isset($bagian['query']) ? '?' . $bagian['query'] : '');
}

function flash(string $icon, string $title, string $text): void
{
    $_SESSION['flash'] = [
        'icon'  => $icon,
        'title' => $title,
        'text'  => $text,
    ];
}

/* ==========================================================================
 * RESET: hapus semua tabel di database aktif
 * ========================================================================== */

function hapusSemuaTabel(mysqli $conn): void
{
    $conn->query('SET foreign_key_checks = 0');

    try {
        $daftar = [];
        $hasil  = $conn->query('SHOW TABLES');
        while ($baris = $hasil->fetch_row()) {
            $daftar[] = $baris[0];
        }

        foreach ($daftar as $nama) {
            $conn->query("DROP TABLE IF EXISTS `$nama`");
        }
    } finally {
        $conn->query('SET foreign_key_checks = 1');
    }
}

/* ==========================================================================
 * STRUKTUR TABEL (NAMA TABEL SMALLCASE)
 * ========================================================================== */

function buatTabel(mysqli $conn): void
{
    $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    $tabel = [

        'user' => "
            CREATE TABLE `user` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                role ENUM('Direktur', 'Tanaman', 'Logistik', 'Keuangan') NOT NULL,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                aktif BOOLEAN DEFAULT TRUE
            ) $engine",

        'produk' => "
            CREATE TABLE `produk` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kode VARCHAR(20) UNIQUE NOT NULL,
                nama VARCHAR(100) NOT NULL,
                satuan VARCHAR(50) NOT NULL,
                harga_beli DECIMAL(15,2) DEFAULT 0,
                harga_pokok DECIMAL(15,2) DEFAULT 0,
                harga_jual DECIMAL(15,2) DEFAULT 0
            ) $engine",

        'gudang' => "
            CREATE TABLE `gudang` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                alamat TEXT
            ) $engine",

        'wilayah' => "
            CREATE TABLE `wilayah` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kode VARCHAR(20) UNIQUE NOT NULL,
                nama VARCHAR(100) NOT NULL
            ) $engine",

        'pemasok' => "
            CREATE TABLE `pemasok` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                wilayah_id INT NOT NULL,
                kode VARCHAR(20) UNIQUE NOT NULL,
                nama VARCHAR(100) NOT NULL,
                alamat TEXT,
                telepon VARCHAR(20),
                bank VARCHAR(50),
                nomor_rekening VARCHAR(50),
                atas_nama_rekening VARCHAR(100),
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                user_id INT NOT NULL,
                FOREIGN KEY (wilayah_id) REFERENCES `wilayah`(id),
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

        'akun' => "
            CREATE TABLE `akun` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kode VARCHAR(20) UNIQUE NOT NULL,
                kelompok VARCHAR(50) NOT NULL,
                nama VARCHAR(100) NOT NULL,
                saldo_normal ENUM('Debit', 'Kredit') NOT NULL
            ) $engine", 
             
        'pelanggan' => "
            CREATE TABLE `pelanggan` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                alamat TEXT NULL,
                telepon VARCHAR(30) NULL,
                keterangan TEXT NULL,
                status ENUM('Prospek', 'Penawaran', 'Closing') NOT NULL DEFAULT 'Prospek',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                user_id INT NOT NULL,
                CONSTRAINT fk_pelanggan_user
                    FOREIGN KEY (user_id) REFERENCES `user` (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) $engine",

        'kebutuhan' => "
            CREATE TABLE `kebutuhan` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pelanggan_id INT NOT NULL,
                produk_id INT NOT NULL,
                qty DECIMAL(15,2) NOT NULL DEFAULT 0,
                keterangan TEXT NULL,
                CONSTRAINT fk_kebutuhan_pelanggan
                    FOREIGN KEY (pelanggan_id) REFERENCES pelanggan (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_kebutuhan_produk
                    FOREIGN KEY (produk_id) REFERENCES produk (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) $engine",

        'pembelian_langsung' => "
            CREATE TABLE `pembelian_langsung` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                kode VARCHAR(30) UNIQUE NOT NULL,
                pemasok_id INT NOT NULL,
                user_id INT NOT NULL,
                subtotal DECIMAL(15,2) DEFAULT 0,
                pajak DECIMAL(15,2) DEFAULT 0,
                ongkir DECIMAL(15,2) DEFAULT 0,
                biaya DECIMAL(15,2) DEFAULT 0,
                diskon DECIMAL(15,2) DEFAULT 0,
                rafaksi DECIMAL(15,2) DEFAULT 0,
                grand_total DECIMAL(15,2) DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pemasok_id) REFERENCES `pemasok`(id),
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

        'pembelian_langsung_detail' => "
            CREATE TABLE `pembelian_langsung_detail` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pembelian_langsung_id INT NOT NULL,
                produk_id INT NOT NULL,
                qty DECIMAL(10,2) NOT NULL,
                harga DECIMAL(15,2) NOT NULL,
                total DECIMAL(15,2) NOT NULL,
                FOREIGN KEY (pembelian_langsung_id) REFERENCES `pembelian_langsung`(id) ON DELETE CASCADE,
                FOREIGN KEY (produk_id) REFERENCES `produk`(id)
            ) $engine",

        'penerimaan_pembelian_langsung' => "
            CREATE TABLE `penerimaan_pembelian_langsung` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                kode VARCHAR(30) UNIQUE NOT NULL,
                pembelian_langsung_id INT NOT NULL,
                gudang_id INT NOT NULL,
                user_id INT NOT NULL,
                total DECIMAL(15,2) DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pembelian_langsung_id) REFERENCES `pembelian_langsung`(id) ON DELETE CASCADE,
                FOREIGN KEY (gudang_id) REFERENCES `gudang`(id),
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

        'penerimaan_pembelian_langsung_detail' => "
            CREATE TABLE `penerimaan_pembelian_langsung_detail` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                penerimaan_pembelian_langsung_id INT NOT NULL,
                produk_id INT NOT NULL,
                qty DECIMAL(10,2) NOT NULL,
                harga DECIMAL(15,2) NOT NULL,
                total DECIMAL(15,2) NOT NULL,
                FOREIGN KEY (penerimaan_pembelian_langsung_id) REFERENCES `penerimaan_pembelian_langsung`(id) ON DELETE CASCADE,
                FOREIGN KEY (produk_id) REFERENCES `produk`(id)
            ) $engine",

        'pembayaran_pembelian_langsung' => "
            CREATE TABLE `pembayaran_pembelian_langsung` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                kode VARCHAR(30) UNIQUE NOT NULL,
                penerimaan_pembelian_langsung_id INT NOT NULL,
                user_id INT NOT NULL,
                akun_id INT NOT NULL,
                total DECIMAL(15,2) DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (penerimaan_pembelian_langsung_id) REFERENCES `penerimaan_pembelian_langsung`(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES `user`(id),
                FOREIGN KEY (akun_id) REFERENCES `akun`(id)
            ) $engine",

        'penjualan' => "
            CREATE TABLE `penjualan` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                kode VARCHAR(30) UNIQUE NOT NULL,
                pelanggan_id INT NOT NULL,
                user_id INT NOT NULL,
                subtotal DECIMAL(15,2) DEFAULT 0,
                subtotal_pokok DECIMAL(15,2) DEFAULT 0,
                pajak DECIMAL(15,2) DEFAULT 0,
                ongkir DECIMAL(15,2) DEFAULT 0,
                biaya DECIMAL(15,2) DEFAULT 0,
                diskon DECIMAL(15,2) DEFAULT 0,
                grand_total DECIMAL(15,2) DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pelanggan_id) REFERENCES `pelanggan`(id),
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

        'penjualan_detail' => "
            CREATE TABLE `penjualan_detail` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                penjualan_id INT NOT NULL,
                produk_id INT NOT NULL,
                qty DECIMAL(10,2) NOT NULL,
                harga DECIMAL(15,2) NOT NULL,
                harga_pokok DECIMAL(15,2) DEFAULT 0,
                total DECIMAL(15,2) NOT NULL,
                FOREIGN KEY (penjualan_id) REFERENCES `penjualan`(id) ON DELETE CASCADE,
                FOREIGN KEY (produk_id) REFERENCES `produk`(id)
            ) $engine", 

        'pengiriman_produk' => "
            CREATE TABLE `pengiriman_produk` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                kode VARCHAR(30) UNIQUE NOT NULL,
                penjualan_id INT NOT NULL,
                gudang_id INT NOT NULL,
                user_id INT NOT NULL,
                total DECIMAL(15,2) DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (penjualan_id) REFERENCES `penjualan`(id) ON DELETE CASCADE,
                FOREIGN KEY (gudang_id) REFERENCES `gudang`(id),
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

        'pembayaran_penjualan' => "
            CREATE TABLE `pembayaran_penjualan` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                kode VARCHAR(30) UNIQUE NOT NULL,
                penjualan_id INT NOT NULL,
                user_id INT NOT NULL,
                akun_id INT NOT NULL,
                total DECIMAL(15,2) DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (penjualan_id) REFERENCES `penjualan`(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES `user`(id),
                FOREIGN KEY (akun_id) REFERENCES `akun`(id)
            ) $engine",

        'pengiriman_produk_detail' => "
            CREATE TABLE `pengiriman_produk_detail` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pengiriman_produk_id INT NOT NULL,
                produk_id INT NOT NULL,
                qty DECIMAL(10,2) NOT NULL,
                harga DECIMAL(15,2) NOT NULL,
                total DECIMAL(15,2) NOT NULL,
                FOREIGN KEY (pengiriman_produk_id) REFERENCES `pengiriman_produk`(id) ON DELETE CASCADE,
                FOREIGN KEY (produk_id) REFERENCES `produk`(id)
            ) $engine",

        'jurnal' => "
            CREATE TABLE `jurnal` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                kode VARCHAR(30) UNIQUE NOT NULL,
                user_id INT NOT NULL,
                keterangan TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

        'jurnal_detail' => "
            CREATE TABLE `jurnal_detail` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                jurnal_id INT NOT NULL,
                akun_id INT NOT NULL,
                debit DECIMAL(15,2) DEFAULT 0,
                kredit DECIMAL(15,2) DEFAULT 0,
                FOREIGN KEY (jurnal_id) REFERENCES `jurnal`(id) ON DELETE CASCADE,
                FOREIGN KEY (akun_id) REFERENCES `akun`(id)
            ) $engine",

        'pengambilan_bahan' => "
            CREATE TABLE `pengambilan_bahan` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                kode VARCHAR(30) UNIQUE NOT NULL,
                gudang_id INT NOT NULL,
                user_id INT NOT NULL,
                total DECIMAL(15,2) DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (gudang_id) REFERENCES `gudang`(id),
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

        'pengambilan_bahan_detail' => "
            CREATE TABLE `pengambilan_bahan_detail` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pengambilan_bahan_id INT NOT NULL,
                produk_id INT NOT NULL,
                qty DECIMAL(10,2) NOT NULL,
                harga DECIMAL(15,2) NOT NULL,
                total DECIMAL(15,2) NOT NULL,
                FOREIGN KEY (pengambilan_bahan_id) REFERENCES `pengambilan_bahan`(id) ON DELETE CASCADE,
                FOREIGN KEY (produk_id) REFERENCES `produk`(id)
            ) $engine",

        'serah_terima_produksi' => "
            CREATE TABLE `serah_terima_produksi` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                kode VARCHAR(30) UNIQUE NOT NULL,
                gudang_id INT NOT NULL,
                user_id INT NOT NULL,
                total DECIMAL(15,2) DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (gudang_id) REFERENCES `gudang`(id),
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

        'serah_terima_produksi_detail' => "
            CREATE TABLE `serah_terima_produksi_detail` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                serah_terima_produksi_id INT NOT NULL,
                produk_id INT NOT NULL,
                qty DECIMAL(10,2) NOT NULL,
                harga DECIMAL(15,2) NOT NULL,
                total DECIMAL(15,2) NOT NULL,
                FOREIGN KEY (serah_terima_produksi_id) REFERENCES `serah_terima_produksi`(id) ON DELETE CASCADE,
                FOREIGN KEY (produk_id) REFERENCES `produk`(id)
            ) $engine",

        'mutasi_stok' => "
            CREATE TABLE `mutasi_stok` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                penerimaan_pembelian_langsung_id INT NULL,
                pengambilan_bahan_id INT NULL,
                serah_terima_produksi_id INT NULL,
                pengiriman_produk_id INT NULL,
                tanggal DATE NOT NULL,
                keterangan VARCHAR(255),
                gudang_id INT NOT NULL,
                produk_id INT NOT NULL,
                masuk DECIMAL(10,2) DEFAULT 0,
                keluar DECIMAL(10,2) DEFAULT 0,
                user_id INT NOT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (penerimaan_pembelian_langsung_id) REFERENCES `penerimaan_pembelian_langsung`(id) ON DELETE CASCADE,
                FOREIGN KEY (pengambilan_bahan_id) REFERENCES `pengambilan_bahan`(id) ON DELETE CASCADE,
                FOREIGN KEY (serah_terima_produksi_id) REFERENCES `serah_terima_produksi`(id) ON DELETE CASCADE,
                FOREIGN KEY (pengiriman_produk_id) REFERENCES `pengiriman_produk`(id) ON DELETE CASCADE,
                FOREIGN KEY (gudang_id) REFERENCES `gudang`(id),
                FOREIGN KEY (produk_id) REFERENCES `produk`(id),
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

        'buku_besar' => "
            CREATE TABLE `buku_besar` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                pembelian_langsung_id INT NULL,
                penerimaan_pembelian_langsung_id INT NULL,
                pembayaran_pembelian_langsung_id INT NULL,
                pengambilan_bahan_id INT NULL,
                serah_terima_produksi_id INT NULL,
                penjualan_id INT NULL,
                pengiriman_produk_id INT NULL,
                pembayaran_penjualan_id INT NULL,
                jurnal_id INT NULL,
                akun_id INT NOT NULL,
                user_id INT NOT NULL,
                keterangan TEXT,
                debit DECIMAL(15,2) DEFAULT 0,
                kredit DECIMAL(15,2) DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pembelian_langsung_id) REFERENCES `pembelian_langsung`(id) ON DELETE CASCADE,
                FOREIGN KEY (penerimaan_pembelian_langsung_id) REFERENCES `penerimaan_pembelian_langsung`(id) ON DELETE CASCADE,
                FOREIGN KEY (pembayaran_pembelian_langsung_id) REFERENCES `pembayaran_pembelian_langsung`(id) ON DELETE CASCADE,
                FOREIGN KEY (pengambilan_bahan_id) REFERENCES `pengambilan_bahan`(id) ON DELETE CASCADE,
                FOREIGN KEY (serah_terima_produksi_id) REFERENCES `serah_terima_produksi`(id) ON DELETE CASCADE,
                FOREIGN KEY (penjualan_id) REFERENCES `penjualan`(id) ON DELETE CASCADE, 
                FOREIGN KEY (pengiriman_produk_id) REFERENCES `pengiriman_produk`(id) ON DELETE CASCADE,
                FOREIGN KEY (pembayaran_penjualan_id) REFERENCES `pembayaran_penjualan`(id) ON DELETE CASCADE,    
                FOREIGN KEY (jurnal_id) REFERENCES `jurnal`(id) ON DELETE CASCADE,
                FOREIGN KEY (akun_id) REFERENCES `akun`(id),
                FOREIGN KEY (user_id) REFERENCES `user`(id)
            ) $engine",

    ];

    foreach ($tabel as $sql) {
        $conn->query($sql);
    }
}

/* ==========================================================================
 * DATA AWAL (SEEDER)
 * ========================================================================== */

function isiTabel(mysqli $conn): void
{
    // 1. Seed User
    $conn->query("
        INSERT INTO `user` (id, nama, role, username, password, aktif) VALUES
        (1, 'Andik Kurniawan', 'Direktur', 'andik', '" . password_hash('qc_789456', PASSWORD_DEFAULT) . "', TRUE),
        (2, 'Fika Devina', 'Keuangan', 'keuangan', '" . password_hash('keu_123456', PASSWORD_DEFAULT) . "', TRUE),
        (3, 'Eko Purwanto', 'Logistik', 'logistik', '" . password_hash('log_123456', PASSWORD_DEFAULT) . "', TRUE),
        (4, 'Budi Raharjo', 'Tanaman', 'tanaman', '" . password_hash('tanam_123456', PASSWORD_DEFAULT) . "', TRUE)
    ");

    // 2. Seed Wilayah
    $conn->query("
        INSERT INTO `wilayah` (id, kode, nama) VALUES 
        (1, 'AA', 'Srigonco Bantur'),
        (2, 'AB', 'Rejosari Bantur')
    ");

    // 3. Seed Pemasok
    $conn->query("
        INSERT INTO `pemasok` (id, wilayah_id, kode, nama, alamat, telepon, bank, nomor_rekening, atas_nama_rekening, user_id) VALUES 
        (1, 1, 'AA-00001', 'Sutrisno', 'Dusun Krajan RT 01 RW 02, Srigonco', '081234567890', 'BRI', '352101023456501', 'Sutrisno', 1)
    ");

    // 4. Seed Gudang
    $conn->query("
        INSERT INTO `gudang` (id, nama, alamat) VALUES 
        (1, 'Srigonco', 'Srigonco Bantur')
    ");

    // 5. Seed Produk (Sesuai seeder awal Anda)
    $conn->query("
        INSERT INTO `produk` (id, kode, nama, harga_beli, harga_pokok, harga_jual, satuan) VALUES 
        (1, 'KTU', 'Kelapa Tua Utuh', 3500.00, 3500.00, 4500.00, 'buah'),
        (2, 'KTK', 'Kelapa Tua Kupas', 4000.00, 4000.00, 6000.00, 'buah'),
        (3, 'SBK', 'Sabut Kelapa', 500.00, 400.00, 1000.00, 'kg'),
        (4, 'TPK', 'Tempurung Kelapa', 1000.00, 800.00, 1800.00, 'kg'),
        (5, 'DGK', 'Daging Kelapa Utuh', 1000.00, 900.00, 1800.00, 'kg'),
        (6, 'AIK', 'Air Kelapa', 500.00, 300.00, 1500.00, 'liter'),
        (7, 'DGP', 'Daging Kelapa Parut', 500.00, 450.00, 1500.00, 'kg'),
        (8, 'STK', 'Santan Kelapa', 12000.00, 10000.00, 16000.00, 'liter'),
        (9, 'APS', 'Ampas Pasca Santan', 800.00, 600.00, 1500.00, 'kg'),
        (10, 'VCO', 'Virgin Coconut Oil', 45000.00, 40000.00, 65000.00, 'mL'),
        (11, 'CKK', 'Cuka Kelapa', 45000.00, 40000.00, 65000.00, 'liter')
    ");

    // 6. Seed Akun (Chart of Accounts)
    $conn->query("
        INSERT INTO `akun` (id, kode, kelompok, nama, saldo_normal) VALUES
        (1, '101.01', 'Aset', 'Kas di Kantor', 'Debit'),
        (2, '101.02', 'Aset', 'Kas di Tanaman', 'Debit'),
        (3, '103.01', 'Aset', 'Persediaan Bahan Baku Belum Diterima', 'Debit'),
        (4, '103.02', 'Aset', 'Persediaan Bahan Baku', 'Debit'),
        (5, '201.01', 'Kewajiban', 'Hutang Pemasok', 'Kredit'),
        (6, '301.01', 'Ekuitas', 'Modal Disetor', 'Kredit'),
        (7, '301.02', 'Ekuitas', 'Laba Ditahan', 'Kredit'),
        (8, '501.01', 'Beban', 'Pembelian Bahan Baku', 'Debit'),
        (9,  '105.01', 'Aset', 'Pajak Dibayar Dimuka / Pajak Masukan', 'Debit'),
        (10, '502.01', 'Beban', 'Beban Angkut / Ongkir', 'Debit'),
        (11, '503.01', 'Beban', 'Beban Operasional Lainnya', 'Debit'),
        (12, '402.01', 'Pendapatan', 'Diskon Pembelian', 'Kredit'),
        (13, '103.03', 'Aset', 'Barang Dalam Proses (WIP)', 'Debit'),
        (14, '103.04', 'Aset', 'Persediaan Barang Jadi', 'Debit'),
        (15, '101.03', 'Aset', 'Rekening Bank', 'Debit'),
        (16, '102.01', 'Aset', 'Piutang Pelanggan', 'Debit'),
        (17, '401.01', 'Pendapatan', 'Pendapatan Penjualan Barang Jadi', 'Kredit'),
        (18, '402.02', 'Pendapatan', 'Diskon Penjualan', 'Debit'),
        (19, '202.01', 'Kewajiban', 'PPN Keluaran / Hutang Pajak Penjualan', 'Kredit'),
        (20, '501.02', 'Beban', 'Harga Pokok Penjualan (HPP)', 'Debit')
    ");

    // 7. Seed Dummy Pelanggan
    $conn->query("
        INSERT INTO `pelanggan` (id, nama, alamat, telepon, keterangan, status, user_id) VALUES 
        (1, 'PT Surya Mandiri Sejahtera', 'Jl. Raya Industri No. 45, Malang', '0341555888', 'Potensi kontrak rutin bulanan santan kelapa.', 'Prospek', 1)
    ");

    // 8. Seed Dummy Kebutuhan Pelanggan
    $conn->query("
        INSERT INTO `kebutuhan` (id, pelanggan_id, produk_id, qty, keterangan) VALUES 
        (1, 1, 8, 500.00, 'Butuh suplai Santan Kelapa rutin setiap hari Senin.')
    ");
}

/* ==========================================================================
 * EKSEKUSI
 * ========================================================================== */

try {
    hapusSemuaTabel($conn);
    buatTabel($conn);
    isiTabel($conn);

    flash('success', 'Reset & Setup Berhasil', 'Seluruh tabel smallcase dan seeder produk awal berhasil dibuat.');
} catch (Throwable $e) {
    flash('error', 'Reset & Setup Gagal', $e->getMessage());
}

header('Location: ' . halamanSebelumnya());
exit;