<?php
include 'config.php';
require_once 'penjualan_helper.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: penjualan_index.php');
    exit;
}

// Kembalikan ke form dengan isian lama agar tidak perlu mengetik ulang
function gagal_kembali(string $pesan): void
{
    $_SESSION['sipk-old-penjualan'] = $_POST;
    flash_dan_pindah('error', 'Gagal', $pesan, 'penjualan_tambah.php');
}

[$data, $error] = penjualan_ambil_input($_POST);
if ($error !== null) {
    gagal_kembali($error);
}

$user_id = (int)($_SESSION['sipk-user_id'] ?? 0);

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        INSERT INTO penjualan
            (tanggal, kode, pelanggan_id, subtotal, pajak, ongkir, biaya, rafaksi, diskon, grand_total, gudang_id, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'ssidddddddii',
        $data['tanggal'], $data['kode'], $data['pelanggan_id'],
        $data['subtotal'], $data['pajak'], $data['ongkir'], $data['biaya'],
        $data['rafaksi'], $data['diskon'], $data['grand_total'],
        $data['gudang_id'], $user_id
    );
    $stmt->execute();
    $penjualan_id = (int)$conn->insert_id;
    $stmt->close();

    penjualan_simpan_detail($conn, $penjualan_id, $data['items']);

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ((int)$e->getCode() === 1062) {
        gagal_kembali('Kode penjualan sudah dipakai, gunakan kode lain.');
    }
    if ((int)$e->getCode() === 1452) {
        gagal_kembali('Pelanggan, gudang, atau produk yang dipilih tidak valid.');
    }
    gagal_kembali('Data tidak dapat disimpan: ' . $e->getMessage());
} catch (Throwable $e) {
    $conn->rollback();
    gagal_kembali('Data tidak dapat disimpan: ' . $e->getMessage());
}

flash_dan_pindah('success', 'Berhasil', 'Panen ' . $data['kode'] . ' berhasil disimpan.', 'penjualan_index.php');