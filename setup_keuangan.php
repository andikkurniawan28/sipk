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
    // Hindari berputar ke setup_keuangan.php sendiri
    if (basename($path) === basename(__FILE__)) {
        return $fallback;
    }

    return $path . (isset($bagian['query']) ? '?' . $bagian['query'] : '');
}

try {
    // Matikan sementara foreign key checks untuk proses drop tabel
    $conn->query("SET foreign_key_checks = 0");

    // Hapus tabel jika sudah ada (dari tabel anak/turunan ke tabel induk)
    $conn->query("DROP TABLE IF EXISTS buku_besar");
    $conn->query("DROP TABLE IF EXISTS jurnal_detail");
    $conn->query("DROP TABLE IF EXISTS jurnal");

    // Aktifkan kembali foreign key checks
    $conn->query("SET foreign_key_checks = 1");

    // 1. Tabel jurnal
    $conn->query("
        CREATE TABLE jurnal (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            kode VARCHAR(30) NOT NULL,
            keterangan TEXT NOT NULL,
            debit DECIMAL(15,2) NOT NULL DEFAULT 0,
            kredit DECIMAL(15,2) NOT NULL DEFAULT 0,
            user_id INT UNSIGNED NOT NULL,
            tutupan TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_jurnal_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. Tabel jurnal_detail
    // Hapus jurnal -> semua baris detail jurnal tersebut ikut terhapus (CASCADE)
    $conn->query("
        CREATE TABLE jurnal_detail (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            jurnal_id INT UNSIGNED NOT NULL,
            akun_id INT UNSIGNED NOT NULL,
            debit DECIMAL(15,2) NOT NULL DEFAULT 0,
            kredit DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_jurdetail_jurnal
                FOREIGN KEY (jurnal_id) REFERENCES jurnal (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_jurdetail_akun
                FOREIGN KEY (akun_id) REFERENCES akun (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 3. Tabel buku_besar
    // Menghubungkan transaksi operasional utama (panen, penerimaan, pengeluaran, penjualan) langsung dengan akun dan buku besar
    $conn->query("
        CREATE TABLE buku_besar (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            akun_id INT UNSIGNED NOT NULL,
            panen_id INT UNSIGNED NULL,
            penerimaan_id INT UNSIGNED NULL,
            pengeluaran_id INT UNSIGNED NULL,
            penjualan_id INT UNSIGNED NULL,
            jurnal_id INT UNSIGNED NULL,
            keterangan TEXT NOT NULL,
            debit DECIMAL(15,2) NOT NULL DEFAULT 0,
            kredit DECIMAL(15,2) NOT NULL DEFAULT 0,
            user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_bukubesar_akun
                FOREIGN KEY (akun_id) REFERENCES akun (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_bukubesar_panen
                FOREIGN KEY (panen_id) REFERENCES panen (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_bukubesar_penerimaan
                FOREIGN KEY (penerimaan_id) REFERENCES penerimaan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_bukubesar_pengeluaran
                FOREIGN KEY (pengeluaran_id) REFERENCES pengeluaran (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_bukubesar_penjualan
                FOREIGN KEY (penjualan_id) REFERENCES penjualan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_bukubesar_jurnal
                FOREIGN KEY (jurnal_id) REFERENCES jurnal (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_bukubesar_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $_SESSION['flash'] = [
        'icon'  => 'success',
        'title' => 'Setup Keuangan Berhasil',
        'text'  => 'Tabel jurnal, jurnal_detail, dan buku_besar berhasil di-reset dan dibuat ulang.'
    ];
} catch (Throwable $e) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Setup Keuangan Gagal',
        'text'  => $e->getMessage()
    ];
}

header('Location: ' . halaman_sebelumnya());
exit;