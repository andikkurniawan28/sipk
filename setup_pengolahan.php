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
    // Hindari berputar ke setup_pengolahan.php sendiri
    if (basename($path) === basename(__FILE__)) {
        return $fallback;
    }

    return $path . (isset($bagian['query']) ? '?' . $bagian['query'] : '');
}

try {
    // Tabel master (user, gudang, produk) harus sudah ada: jalankan setup_master.php lebih dulu.

    // 0. Hapus tabel lama lebih dulu. Urutan: anak dulu (yang memegang foreign key), baru induk.
    //    PERHATIAN: semua data pengolahan, input, dan output ikut hilang setiap file ini dijalankan.
    $conn->query("DROP TABLE IF EXISTS output_pengolahan");
    $conn->query("DROP TABLE IF EXISTS input_pengolahan");
    $conn->query("DROP TABLE IF EXISTS pengolahan");

    // 1. Tabel pengolahan
    // Sengaja tanpa subtotal/pajak/ongkir/biaya/diskon/rafaksi/grand total:
    // nilai persediaan keluar (input) dan masuk (output) dihitung dari SUM(total) tabel detail.
    $conn->query("
        CREATE TABLE pengolahan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            kode VARCHAR(30) NOT NULL UNIQUE,
            keterangan TEXT NULL,
            user_id INT UNSIGNED NOT NULL,
            CONSTRAINT fk_pengolahan_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. Tabel input_pengolahan (bahan yang dipakai)
    // Hapus pengolahan -> semua input pengolahan tersebut ikut terhapus (CASCADE)
    $conn->query("
        CREATE TABLE input_pengolahan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pengolahan_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            gudang_id INT UNSIGNED NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 0,
            harga DECIMAL(15,2) NOT NULL DEFAULT 0,
            total DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_inputpg_pengolahan
                FOREIGN KEY (pengolahan_id) REFERENCES pengolahan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_inputpg_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_inputpg_gudang
                FOREIGN KEY (gudang_id) REFERENCES gudang (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 3. Tabel output_pengolahan (hasil pengolahan)
    // Hapus pengolahan -> semua output pengolahan tersebut ikut terhapus (CASCADE)
    $conn->query("
        CREATE TABLE output_pengolahan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pengolahan_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            gudang_id INT UNSIGNED NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 0,
            harga DECIMAL(15,2) NOT NULL DEFAULT 0,
            total DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_outputpg_pengolahan
                FOREIGN KEY (pengolahan_id) REFERENCES pengolahan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_outputpg_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_outputpg_gudang
                FOREIGN KEY (gudang_id) REFERENCES gudang (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Verifikasi: pastikan aturan ON DELETE CASCADE benar-benar terpasang.
    $cek = $conn->query("
        SELECT COUNT(*) AS jumlah
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND CONSTRAINT_NAME IN ('fk_inputpg_pengolahan', 'fk_outputpg_pengolahan')
          AND DELETE_RULE = 'CASCADE'
    ")->fetch_assoc();

    if ((int)$cek['jumlah'] !== 2) {
        throw new RuntimeException(
            'Aturan ON DELETE CASCADE belum terpasang pada tabel input_pengolahan/output_pengolahan.'
        );
    }

    $_SESSION['flash'] = [
        'icon'  => 'success',
        'title' => 'Setup pengolahan berhasil',
        'text'  => 'Tabel pengolahan, input_pengolahan, dan output_pengolahan dibuat ulang (data lama dihapus), ON DELETE CASCADE sudah aktif.'
    ];
} catch (Throwable $e) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Setup pengolahan gagal',
        'text'  => $e->getMessage()
    ];
}

header('Location: ' . halaman_sebelumnya());
exit;