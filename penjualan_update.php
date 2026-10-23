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

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID penjualan tidak valid.', 'penjualan_index.php');
}

$kembali = 'penjualan_edit.php?id=' . $id;

// Kembalikan ke form dengan isian lama agar tidak perlu mengetik ulang
function gagal_kembali(string $pesan, string $kembali): void
{
    $_SESSION['sipk-old-penjualan'] = $_POST;
    flash_dan_pindah('error', 'Gagal', $pesan, $kembali);
}

// Pastikan penjualan ada
$cek = $conn->prepare("SELECT id FROM penjualan WHERE id = ? LIMIT 1");
$cek->bind_param('i', $id);
$cek->execute();
$ada = $cek->get_result()->fetch_assoc();
$cek->close();
if (!$ada) {
    flash_dan_pindah('error', 'Tidak ditemukan', 'Data penjualan tidak ada.', 'penjualan_index.php');
}

[$data, $error] = penjualan_ambil_input($_POST);
if ($error !== null) {
    gagal_kembali($error, $kembali);
}

try {
    $conn->begin_transaction();

    // user_id (pembuat) sengaja tidak diubah
    $stmt = $conn->prepare("
        UPDATE penjualan
        SET tanggal = ?, kode = ?, pelanggan_id = ?, subtotal = ?, pajak = ?, ongkir = ?, biaya = ?,
            rafaksi = ?, diskon = ?, grand_total = ?, gudang_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'ssidddddddii',
        $data['tanggal'], $data['kode'], $data['pelanggan_id'],
        $data['subtotal'], $data['pajak'], $data['ongkir'], $data['biaya'],
        $data['rafaksi'], $data['diskon'], $data['grand_total'],
        $data['gudang_id'], $id
    );
    $stmt->execute();
    $stmt->close();

    // Detail lama diganti seluruhnya dengan isi form
    $hapus = $conn->prepare("DELETE FROM penjualan_detail WHERE penjualan_id = ?");
    $hapus->bind_param('i', $id);
    $hapus->execute();
    $hapus->close();

    penjualan_simpan_detail($conn, $id, $data['items']);

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ((int)$e->getCode() === 1062) {
        gagal_kembali('Kode penjualan sudah dipakai penjualan lain.', $kembali);
    }
    if ((int)$e->getCode() === 1452) {
        gagal_kembali('Pelanggan, gudang, atau produk yang dipilih tidak valid.', $kembali);
    }
    gagal_kembali('Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
} catch (Throwable $e) {
    $conn->rollback();
    gagal_kembali('Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Penjualan ' . $data['kode'] . ' berhasil diperbarui.', 'penjualan_index.php');