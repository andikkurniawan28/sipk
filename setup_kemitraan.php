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
    // Hindari berputar ke setup_kemitraan.php sendiri
    if (basename($path) === basename(__FILE__)) {
        return $fallback;
    }

    return $path . (isset($bagian['query']) ? '?' . $bagian['query'] : '');
}

try {
    // Tabel master (user, produk) harus sudah ada.

    // ---------------------------------------------------------------
    // TAHAP 1: HAPUS tabel lama (dari tabel anak ke tabel induk)
    // PERHATIAN: semua data wilayah, petani, panen, dan panen_detail ikut hilang.
    // ---------------------------------------------------------------
    $conn->query("SET foreign_key_checks = 0");

    $conn->query("DROP TABLE IF EXISTS panen_detail");
    $conn->query("DROP TABLE IF EXISTS panen");
    $conn->query("DROP TABLE IF EXISTS petani");
    $conn->query("DROP TABLE IF EXISTS wilayah");

    $conn->query("SET foreign_key_checks = 1");

    // ---------------------------------------------------------------
    // TAHAP 2: BUAT tabel (dari tabel induk ke tabel anak)
    // ---------------------------------------------------------------

    // 1. Tabel wilayah (kode unik, tepat 2 karakter)
    $conn->query("
        CREATE TABLE wilayah (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kode CHAR(2) NOT NULL UNIQUE,
            nama VARCHAR(100) NOT NULL,
            CONSTRAINT chk_wilayah_kode CHECK (CHAR_LENGTH(kode) = 2)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. Tabel petani
    $conn->query("
        CREATE TABLE petani (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kode VARCHAR(20) NOT NULL UNIQUE,
            nama VARCHAR(100) NOT NULL,
            alamat TEXT NULL,
            telepon VARCHAR(30) NULL UNIQUE,
            wilayah_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_id INT UNSIGNED NOT NULL,
            CONSTRAINT fk_petani_wilayah
                FOREIGN KEY (wilayah_id) REFERENCES wilayah (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_petani_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 3. Tabel panen (gudang_id dihapus dari tabel panen)
    $conn->query("
        CREATE TABLE panen (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            kode VARCHAR(30) NOT NULL UNIQUE,
            petani_id INT UNSIGNED NOT NULL,
            subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
            pajak DECIMAL(15,2) NOT NULL DEFAULT 0,
            ongkir DECIMAL(15,2) NOT NULL DEFAULT 0,
            biaya DECIMAL(15,2) NOT NULL DEFAULT 0,
            rafaksi DECIMAL(15,2) NOT NULL DEFAULT 0,
            diskon DECIMAL(15,2) NOT NULL DEFAULT 0,
            grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            user_id INT UNSIGNED NOT NULL,
            CONSTRAINT fk_panen_petani
                FOREIGN KEY (petani_id) REFERENCES petani (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_panen_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 4. Tabel panen_detail
    $conn->query("
        CREATE TABLE panen_detail (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            panen_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 0,
            harga DECIMAL(15,2) NOT NULL DEFAULT 0,
            total DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_detail_panen
                FOREIGN KEY (panen_id) REFERENCES panen (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_detail_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ---------------------------------------------------------------
    // TAHAP 3: SEED
    // ---------------------------------------------------------------
    $conn->query("
        INSERT INTO wilayah (kode, nama) VALUES
            ('AA', 'Srigonco Bantur'),
            ('AB', 'Rejosari Bantur'),
            ('AC', 'Wonokerto Bantur')
    ");

    $_SESSION['flash'] = [
        'icon'  => 'success',
        'title' => 'Reset & Setup Kemitraan Berhasil',
        'text'  => 'Tabel wilayah, petani, panen (tanpa gudang_id), dan panen_detail berhasil dibuat ulang.'
    ];
} catch (Throwable $e) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Setup Kemitraan Gagal',
        'text'  => $e->getMessage()
    ];
}

header('Location: ' . halaman_sebelumnya());
exit;