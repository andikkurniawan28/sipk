<?php
include 'config.php';
require_once 'panen_helper.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panen_index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID panen tidak valid.', 'panen_index.php');
}

$kembali = 'panen_edit.php?id=' . $id;

// Kembalikan ke form dengan isian lama agar tidak perlu mengetik ulang
function gagal_kembali(string $pesan, string $kembali): void
{
    $_SESSION['sipk-old-panen'] = $_POST;
    flash_dan_pindah('error', 'Gagal', $pesan, $kembali);
}

// Pastikan panen ada
$cek = $conn->prepare("SELECT id FROM panen WHERE id = ? LIMIT 1");
$cek->bind_param('i', $id);
$cek->execute();
$ada = $cek->get_result()->fetch_assoc();
$cek->close();
if (!$ada) {
    flash_dan_pindah('error', 'Tidak ditemukan', 'Data panen tidak ada.', 'panen_index.php');
}

[$data, $error] = panen_ambil_input($_POST);
if ($error !== null) {
    gagal_kembali($error, $kembali);
}

try {
    $conn->begin_transaction();

    // user_id (pembuat) sengaja tidak diubah
    $stmt = $conn->prepare("
        UPDATE panen
        SET tanggal = ?, kode = ?, petani_id = ?, subtotal = ?, pajak = ?, ongkir = ?, biaya = ?,
            rafaksi = ?, diskon = ?, grand_total = ?, gudang_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'ssidddddddii',
        $data['tanggal'], $data['kode'], $data['petani_id'],
        $data['subtotal'], $data['pajak'], $data['ongkir'], $data['biaya'],
        $data['rafaksi'], $data['diskon'], $data['grand_total'],
        $data['gudang_id'], $id
    );
    $stmt->execute();
    $stmt->close();

    // Detail lama diganti seluruhnya dengan isi form
    $hapus = $conn->prepare("DELETE FROM panen_detail WHERE panen_id = ?");
    $hapus->bind_param('i', $id);
    $hapus->execute();
    $hapus->close();

    panen_simpan_detail($conn, $id, $data['items']);

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ((int)$e->getCode() === 1062) {
        gagal_kembali('Kode panen sudah dipakai panen lain.', $kembali);
    }
    if ((int)$e->getCode() === 1452) {
        gagal_kembali('Petani, gudang, atau produk yang dipilih tidak valid.', $kembali);
    }
    gagal_kembali('Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
} catch (Throwable $e) {
    $conn->rollback();
    gagal_kembali('Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Panen ' . $data['kode'] . ' berhasil diperbarui.', 'panen_index.php');