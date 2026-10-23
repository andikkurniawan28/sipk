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

// Kembalikan ke form dengan isian lama agar tidak perlu mengetik ulang
function gagal_kembali(string $pesan): void
{
    $_SESSION['sipk-old-pengolahan'] = $_POST;
    flash_dan_pindah('error', 'Gagal', $pesan, 'pengolahan_tambah.php');
}

[$data, $error] = pg_ambil_input($_POST);
if ($error !== null) {
    gagal_kembali($error);
}

$user_id = (int)($_SESSION['sipk-user_id'] ?? 0);

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("INSERT INTO pengolahan (tanggal, kode, keterangan, user_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssi', $data['tanggal'], $data['kode'], $data['keterangan'], $user_id);
    $stmt->execute();
    $pengolahan_id = (int)$conn->insert_id;
    $stmt->close();

    pg_simpan_detail($conn, 'input_pengolahan', $pengolahan_id, $data['input']);
    pg_simpan_detail($conn, 'output_pengolahan', $pengolahan_id, $data['output']);

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ((int)$e->getCode() === 1062) {
        gagal_kembali('Kode pengolahan sudah dipakai, gunakan kode lain.');
    }
    if ((int)$e->getCode() === 1452) {
        gagal_kembali('Produk atau gudang yang dipilih tidak valid.');
    }
    gagal_kembali('Data tidak dapat disimpan: ' . $e->getMessage());
} catch (Throwable $e) {
    $conn->rollback();
    gagal_kembali('Data tidak dapat disimpan: ' . $e->getMessage());
}

flash_dan_pindah('success', 'Berhasil', 'Pengolahan ' . $data['kode'] . ' berhasil disimpan.', 'pengolahan_index.php');