<?php

require_once 'config.php';

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
    $conn->query("SET foreign_key_checks = 0");

    $conn->query("DROP TABLE IF EXISTS mutasi_stok");
    $conn->query("DROP TABLE IF EXISTS pengeluaran_detail");
    $conn->query("DROP TABLE IF EXISTS pengeluaran");
    $conn->query("DROP TABLE IF EXISTS penerimaan_detail");
    $conn->query("DROP TABLE IF EXISTS penerimaan");

    $conn->query("SET foreign_key_checks = 1");

    // 1. Tabel penerimaan (Header Penerimaan - Sumber: panen_id)
    $conn->query("
        CREATE TABLE penerimaan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            kode VARCHAR(30) NOT NULL UNIQUE,
            gudang_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            panen_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_penerimaan_gudang
                FOREIGN KEY (gudang_id) REFERENCES gudang (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_penerimaan_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_penerimaan_panen
                FOREIGN KEY (panen_id) REFERENCES panen (id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. Tabel penerimaan_detail
    $conn->query("
        CREATE TABLE penerimaan_detail (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            penerimaan_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_detail_penerimaan
                FOREIGN KEY (penerimaan_id) REFERENCES penerimaan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_detail_penerimaan_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 3. Tabel pengeluaran (Header Pengeluaran - Sumber: preparasi_id & ada gudang_id)
    $conn->query("
        CREATE TABLE pengeluaran (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            kode VARCHAR(30) NOT NULL UNIQUE,
            gudang_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            preparasi_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pengeluaran_gudang
                FOREIGN KEY (gudang_id) REFERENCES gudang (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_pengeluaran_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_pengeluaran_preparasi
                FOREIGN KEY (preparasi_id) REFERENCES preparasi (id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 4. Tabel pengeluaran_detail
    $conn->query("
        CREATE TABLE pengeluaran_detail (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pengeluaran_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_detail_pengeluaran
                FOREIGN KEY (pengeluaran_id) REFERENCES pengeluaran (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_detail_pengeluaran_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 5. Tabel mutasi_stok
    $conn->query("
        CREATE TABLE mutasi_stok (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gudang_id INT UNSIGNED NOT NULL,
            produk_id INT UNSIGNED NOT NULL,
            penerimaan_id INT UNSIGNED NULL,
            pengeluaran_id INT UNSIGNED NULL,
            tanggal DATE NOT NULL,
            keterangan TEXT NULL,
            masuk DECIMAL(15,2) NOT NULL DEFAULT 0,
            keluar DECIMAL(15,2) NOT NULL DEFAULT 0,
            user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_mutasi_gudang
                FOREIGN KEY (gudang_id) REFERENCES gudang (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_mutasi_produk
                FOREIGN KEY (produk_id) REFERENCES produk (id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT fk_mutasi_penerimaan
                FOREIGN KEY (penerimaan_id) REFERENCES penerimaan (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_mutasi_pengeluaran
                FOREIGN KEY (pengeluaran_id) REFERENCES pengeluaran (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_mutasi_user
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $_SESSION['flash'] = [
        'icon'  => 'success',
        'title' => 'Setup Logistik Berhasil',
        'text'  => 'Tabel penerimaan, pengeluaran (dengan gudang_id), dan mutasi_stok berhasil di-setup kembali.'
    ];
} catch (Throwable $e) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Setup Logistik Gagal',
        'text'  => $e->getMessage()
    ];
}

header('Location: ' . halaman_sebelumnya());
exit;