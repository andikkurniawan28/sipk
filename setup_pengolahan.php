<?php

require_once 'config.php';

// Pastikan error MySQL dilempar sebagai exception agar tertangkap try/catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset('utf8mb4');

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

try {
    // 0. Hapus tabel lama terkait preparasi dan produksi jika ada (urutan anak ke induk)
    $conn->query("SET foreign_key_checks = 0");
    $conn->query("DROP TABLE IF EXISTS produksi_detail");
    $conn->query("DROP TABLE IF EXISTS produksi");
    $conn->query("DROP TABLE IF EXISTS preparasi_detail");
    $conn->query("DROP TABLE IF EXISTS preparasi");
    $conn->query("SET foreign_key_checks = 1");

    // 1. Tabel preparasi (Header - Tanpa gudang_id)
    $conn->query("
        CREATE TABLE preparasi (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            kode VARCHAR(30) NOT NULL UNIQUE,
            keterangan TEXT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_preparasi_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. Tabel preparasi_detail (Item Barang Preparasi - Tanpa Valuasi Uang)
    $conn->query("
        CREATE TABLE preparasi_detail (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            preparasi_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_detail_preparasi
                FOREIGN KEY (preparasi_id) REFERENCES preparasi (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_detail_preparasi_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 3. Tabel produksi (Header - Terhubung ke preparasi, tanpa gudang_id)
    $conn->query("
        CREATE TABLE produksi (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            kode VARCHAR(30) NOT NULL UNIQUE,
            preparasi_id INT UNSIGNED NOT NULL,
            keterangan TEXT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_produksi_preparasi
                FOREIGN KEY (preparasi_id) REFERENCES preparasi (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_produksi_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 4. Tabel produksi_detail (Item Output / Hasil Produksi - Tanpa Valuasi Uang)
    $conn->query("
        CREATE TABLE produksi_detail (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            produksi_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_detail_produksi
                FOREIGN KEY (produksi_id) REFERENCES produksi (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_detail_produksi_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $_SESSION['flash'] = [
        'icon'  => 'success',
        'title' => 'Setup Preparasi & Produksi Berhasil',
        'text'  => 'Tabel preparasi dan produksi (tanpa gudang_id) berhasil dibuat dengan relasi yang terhubung.'
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