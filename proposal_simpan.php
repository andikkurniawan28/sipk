<?php
include 'config.php';
require_once 'proposal_helper.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: proposal_index.php');
    exit;
}

// Kembalikan ke form dengan isian lama agar tidak perlu mengetik ulang
function gagal_kembali(string $pesan): void
{
    $_SESSION['sipk-old-proposal'] = $_POST;
    flash_dan_pindah('error', 'Gagal', $pesan, 'proposal_tambah.php');
}

[$data, $error] = proposal_ambil_input($_POST);
if ($error !== null) {
    gagal_kembali($error);
}

$user_id = (int)($_SESSION['sipk-user_id'] ?? 0);

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        INSERT INTO proposal
            (tanggal, kode, petani_id, subtotal, pajak, ongkir, biaya, rafaksi, diskon, grand_total, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'ssidddddddi',
        $data['tanggal'], $data['kode'], $data['petani_id'],
        $data['subtotal'], $data['pajak'], $data['ongkir'], $data['biaya'],
        $data['rafaksi'], $data['diskon'], $data['grand_total'],
        $user_id
    );
    $stmt->execute();
    $proposal_id = (int)$conn->insert_id;
    $stmt->close();

    // Simpan detail produk proposal
    proposal_simpan_detail($conn, $proposal_id, $data['items']);

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ((int)$e->getCode() === 1062) {
        gagal_kembali('Kode proposal sudah dipakai, gunakan kode lain.');
    }
    if ((int)$e->getCode() === 1452) {
        gagal_kembali('Petani, atau produk yang dipilih tidak valid.');
    }
    error_log('proposal_simpan: ' . $e->getMessage());
    gagal_kembali('Data tidak dapat disimpan.');
} catch (Throwable $e) {
    $conn->rollback();
    error_log('proposal_simpan: ' . $e->getMessage());
    gagal_kembali('Data tidak dapat disimpan.');
}

flash_dan_pindah('success', 'Berhasil', 'Proposal ' . $data['kode'] . ' berhasil disimpan.', 'proposal_index.php');