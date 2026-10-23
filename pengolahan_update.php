<?php
include 'config.php';
require_once 'pengolahan_helper.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pengolahan_index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID pengolahan tidak valid.', 'pengolahan_index.php');
}

$kembali = 'pengolahan_edit.php?id=' . $id;

// Kembalikan ke form dengan isian lama agar tidak perlu mengetik ulang
function gagal_kembali(string $pesan, string $kembali): void
{
    $_SESSION['sipk-old-pengolahan'] = $_POST;
    flash_dan_pindah('error', 'Gagal', $pesan, $kembali);
}

// Pastikan pengolahan ada
$cek = $conn->prepare("SELECT id FROM pengolahan WHERE id = ? LIMIT 1");
$cek->bind_param('i', $id);
$cek->execute();
$ada = $cek->get_result()->fetch_assoc();
$cek->close();
if (!$ada) {
    flash_dan_pindah('error', 'Tidak ditemukan', 'Data pengolahan tidak ada.', 'pengolahan_index.php');
}

[$data, $error] = pg_ambil_input($_POST);
if ($error !== null) {
    gagal_kembali($error, $kembali);
}

try {
    $conn->begin_transaction();

    // user_id (pembuat) sengaja tidak diubah
    $stmt = $conn->prepare("UPDATE pengolahan SET tanggal = ?, kode = ?, keterangan = ? WHERE id = ?");
    $stmt->bind_param('sssi', $data['tanggal'], $data['kode'], $data['keterangan'], $id);
    $stmt->execute();
    $stmt->close();

    // Input dan output lama diganti seluruhnya dengan isi form
    $hapusIn = $conn->prepare("DELETE FROM input_pengolahan WHERE pengolahan_id = ?");
    $hapusIn->bind_param('i', $id);
    $hapusIn->execute();
    $hapusIn->close();

    $hapusOut = $conn->prepare("DELETE FROM output_pengolahan WHERE pengolahan_id = ?");
    $hapusOut->bind_param('i', $id);
    $hapusOut->execute();
    $hapusOut->close();

    pg_simpan_detail($conn, 'input_pengolahan', $id, $data['input']);
    pg_simpan_detail($conn, 'output_pengolahan', $id, $data['output']);

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ((int)$e->getCode() === 1062) {
        gagal_kembali('Kode pengolahan sudah dipakai pengolahan lain.', $kembali);
    }
    if ((int)$e->getCode() === 1452) {
        gagal_kembali('Produk atau gudang yang dipilih tidak valid.', $kembali);
    }
    gagal_kembali('Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
} catch (Throwable $e) {
    $conn->rollback();
    gagal_kembali('Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Pengolahan ' . $data['kode'] . ' berhasil diperbarui.', 'pengolahan_index.php');