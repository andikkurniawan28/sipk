<?php

require_once 'config.php';

// Pastikan error MySQL dilempar sebagai exception agar tertangkap try/catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset('utf8mb4');

/**
 * Tujuan kembali: halaman sebelumnya (referer) bila berasal dari host yang sama,
 * selain itu ke index.php. Hanya path + query yang dipakai, jadi aman dari open redirect.
 */
function halaman_sebelumnya(): string
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

    // Host harus sama dengan host aplikasi ini (abaikan port)
    $hostSaya = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if (strcasecmp($bagian['host'], $hostSaya) !== 0) {
        return $fallback;
    }

    $path = $bagian['path'] ?? '/';
    // Hindari berputar ke file setup sendiri
    if (basename($path) === basename(__FILE__)) {
        return $fallback;
    }

    return $path . (isset($bagian['query']) ? '?' . $bagian['query'] : '');
}

try {
    // Matikan sementara foreign key check untuk proses drop tabel
    $conn->query("SET foreign_key_checks = 0");

    // 0. Hapus tabel jika sudah ada (urutan dari tabel anak/turunan ke induk atau bebas karena check dimatikan)
    $conn->query("DROP TABLE IF EXISTS produk");
    $conn->query("DROP TABLE IF EXISTS gudang");
    $conn->query("DROP TABLE IF EXISTS akun");
    $conn->query("DROP TABLE IF EXISTS mitra");
    $conn->query("DROP TABLE IF EXISTS `user`");

    // Aktifkan kembali foreign key check
    $conn->query("SET foreign_key_checks = 1");

    // 1. Tabel produk
    $conn->query("
        CREATE TABLE produk (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            satuan VARCHAR(20) NOT NULL,
            harga_beli DECIMAL(15,2) NOT NULL DEFAULT 0,
            harga_jual DECIMAL(15,2) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. Tabel gudang
    $conn->query("
        CREATE TABLE gudang (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            alamat TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 3. Tabel akun
    $conn->query("
        CREATE TABLE akun (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kode VARCHAR(20) NOT NULL UNIQUE,
            nama VARCHAR(100) NOT NULL,
            saldo_normal ENUM('Debit', 'Kredit') NOT NULL,
            saldo_awal DECIMAL(15,2) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 4. Tabel mitra
    $conn->query("
        CREATE TABLE mitra (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            alamat TEXT NULL,
            telepon VARCHAR(30) NULL,
            keterangan TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 5. Tabel user
    $conn->query("
        CREATE TABLE `user` (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL,
            aktif TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Seeder produk
    $conn->query("
        INSERT INTO produk (nama, satuan, harga_beli, harga_jual) VALUES
            ('Kelapa Tua', 'kg', 3500, 4500),
            ('Kelapa Muda', 'kg', 4000, 6000),
            ('Sabut Kelapa', 'kg', 500, 1000),
            ('Tempurung Kelapa', 'kg', 1000, 1800),
            ('Air Kelapa', 'liter', 500, 1500),
            ('Santan Kelapa', 'liter', 12000, 16000),
            ('Ampas Kelapa', 'kg', 800, 1500),
            ('Arang Tempurung', 'kg', 4500, 6500),
            ('Virgin Coconut Oil', 'liter', 45000, 65000),
            ('Karbon Aktif', 'kg', 8000, 12000)
    ");

    // Seeder gudang (Diperbaiki agar menyertakan kolom nama dan alamat)
    $conn->query("
        INSERT INTO gudang (nama, alamat) VALUES 
            ('Gudang Srigonco', 'Srigonco Bantur')
    ");

    // Seeder akun
    $conn->query("
        INSERT INTO akun (kode, nama, saldo_normal, saldo_awal) VALUES
            -- 1. ASET / HARTA (1-0000)
            ('1-1110', 'Kas', 'Debit', 0),
            ('1-1120', 'Bank', 'Debit', 0),
            ('1-1210', 'Piutang Usaha', 'Debit', 0),
            ('1-1310', 'Persediaan', 'Debit', 0),
            ('1-1510', 'Aset Tetap', 'Debit', 0),
            ('1-1520', 'Akumulasi Penyusutan Aset Tetap', 'Kredit', 0),

            -- 2. KEWAJIBAN / UTANG (2-0000)
            ('2-1110', 'Utang Usaha', 'Kredit', 0),
            ('2-1210', 'Utang Biaya', 'Kredit', 0),

            -- 3. EKUITAS / MODAL (3-0000)
            ('3-1110', 'Modal Disetor', 'Kredit', 0),
            ('3-1120', 'Prive', 'Debit', 0),
            ('3-1210', 'Laba Ditahan', 'Kredit', 0),

            -- 4. PENDAPATAN (4-0000)
            ('4-1100', 'Pendapatan Penjualan', 'Kredit', 0),
            ('4-1200', 'Pendapatan Lain-lain', 'Kredit', 0),

            -- 5. BEBAN POKOK PENJUALAN (5-0000)
            ('5-1100', 'Harga Pokok Penjualan (HPP)', 'Debit', 0),

            -- 6. BEBAN OPERASIONAL (6-0000)
            ('6-1100', 'Beban Gaji dan Upah', 'Debit', 0),
            ('6-1200', 'Beban Transportasi dan Pengiriman', 'Debit', 0),
            ('6-1300', 'Beban Listrik, Air, dan Bahan Bakar', 'Debit', 0),
            ('6-1400', 'Beban Pemeliharaan dan Perbaikan', 'Debit', 0),
            ('6-1500', 'Beban Penyusutan', 'Debit', 0),
            ('6-1900', 'Beban Operasional Lainnya', 'Debit', 0)
    ");

    // Seeder user sementara
    $userNama     = 'Andik Kurniawan';
    $userUsername = 'andik';
    $userHash     = password_hash('qc_789456', PASSWORD_BCRYPT);
    $userRole     = 'Direktur';

    $stmtUser = $conn->prepare("
        INSERT INTO `user` (nama, username, password, role, aktif)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmtUser->bind_param('ssss', $userNama, $userUsername, $userHash, $userRole);
    $stmtUser->execute();
    $stmtUser->close();

    $_SESSION['flash'] = [
        'icon'  => 'success',
        'title' => 'Reset & Setup Berhasil',
        'text'  => 'Telah berhasil menghapus tabel lama, lalu membuat ulang dan memasukkan data master.'
    ];
} catch (Throwable $e) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Setup Gagal',
        'text'  => $e->getMessage()
    ];
}

header('Location: ' . halaman_sebelumnya());
exit;