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
    // Hindari berputar ke setup_penjualan.php sendiri
    if (basename($path) === basename(__FILE__)) {
        return $fallback;
    }

    return $path . (isset($bagian['query']) ? '?' . $bagian['query'] : '');
}

try {
    // Tabel master (user, gudang, produk) harus sudah ada: jalankan setup_master.php lebih dulu.

    // Matikan sementara foreign key checks untuk proses drop tabel
    $conn->query("SET foreign_key_checks = 0");

    // Hapus tabel jika sudah ada (dari tabel anak/turunan ke tabel induk)
    $conn->query("DROP TABLE IF EXISTS komplain");
    $conn->query("DROP TABLE IF EXISTS penjualan_detail");
    $conn->query("DROP TABLE IF EXISTS penjualan");
    $conn->query("DROP TABLE IF EXISTS kebutuhan");
    $conn->query("DROP TABLE IF EXISTS pelanggan");

    // Aktifkan kembali foreign key checks
    $conn->query("SET foreign_key_checks = 1");

    // 1. Tabel pelanggan
    $conn->query("
        CREATE TABLE pelanggan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            alamat TEXT NULL,
            telepon VARCHAR(30) NULL,
            keterangan TEXT NULL,
            status ENUM('Prospek', 'Penawaran', 'Closing') NOT NULL DEFAULT 'Prospek',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_id INT UNSIGNED NOT NULL,
            CONSTRAINT fk_pelanggan_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. Tabel kebutuhan
    // Hapus pelanggan -> semua kebutuhan pelanggan tersebut ikut terhapus (CASCADE)
    $conn->query("
        CREATE TABLE kebutuhan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pelanggan_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            CONSTRAINT fk_kebutuhan_pelanggan
                FOREIGN KEY (pelanggan_id) REFERENCES pelanggan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_kebutuhan_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 3. Tabel penjualan
    // Hapus pelanggan -> semua penjualan pelanggan tersebut ikut terhapus (CASCADE)
    $conn->query("
        CREATE TABLE penjualan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            kode VARCHAR(30) NOT NULL UNIQUE,
            pelanggan_id INT UNSIGNED NOT NULL,
            subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
            pajak DECIMAL(15,2) NOT NULL DEFAULT 0,
            ongkir DECIMAL(15,2) NOT NULL DEFAULT 0,
            biaya DECIMAL(15,2) NOT NULL DEFAULT 0,
            diskon DECIMAL(15,2) NOT NULL DEFAULT 0,
            rafaksi DECIMAL(15,2) NOT NULL DEFAULT 0,
            grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            gudang_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            CONSTRAINT fk_penjualan_pelanggan
                FOREIGN KEY (pelanggan_id) REFERENCES pelanggan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_penjualan_gudang
                FOREIGN KEY (gudang_id) REFERENCES gudang (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_penjualan_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 4. Tabel penjualan_detail
    // Hapus penjualan -> semua detail penjualan tersebut ikut terhapus (CASCADE)
    $conn->query("
        CREATE TABLE penjualan_detail (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            penjualan_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 0,
            harga DECIMAL(15,2) NOT NULL DEFAULT 0,
            total DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_pjdetail_penjualan
                FOREIGN KEY (penjualan_id) REFERENCES penjualan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_pjdetail_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 5. Tabel komplain (kolom gambar menyimpan nama file)
    // Hapus pelanggan -> semua komplain pelanggan tersebut ikut terhapus (CASCADE)
    $conn->query("
        CREATE TABLE komplain (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            kode VARCHAR(30) NOT NULL UNIQUE,
            pelanggan_id INT UNSIGNED NOT NULL,
            keterangan TEXT NOT NULL,
            gambar VARCHAR(255) NULL,
            user_id INT UNSIGNED NOT NULL,
            CONSTRAINT fk_komplain_pelanggan
                FOREIGN KEY (pelanggan_id) REFERENCES pelanggan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_komplain_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $_SESSION['flash'] = [
        'icon'  => 'success',
        'title' => 'Reset & Setup Penjualan Berhasil',
        'text'  => 'Tabel pelanggan, kebutuhan, penjualan, penjualan_detail, dan komplain berhasil di-reset dan dibuat ulang.'
    ];
} catch (Throwable $e) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Setup Penjualan Gagal',
        'text'  => $e->getMessage()
    ];
}

header('Location: ' . halaman_sebelumnya());
exit;