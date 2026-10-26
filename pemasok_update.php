<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pemasok_index.php');
    exit;
}

$id                 = (int)($_POST['id'] ?? 0);
$wilayah_id         = (int)($_POST['wilayah_id'] ?? 0);
$nama               = trim($_POST['nama'] ?? '');
$alamat             = trim($_POST['alamat'] ?? '');
$telepon            = trim($_POST['telepon'] ?? '');
$bank               = trim($_POST['bank'] ?? '');
$nomor_rekening     = trim($_POST['nomor_rekening'] ?? '');
$atas_nama_rekening = trim($_POST['atas_nama_rekening'] ?? '');

$kembali = 'pemasok_edit.php?id=' . $id;

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID pemasok tidak valid.', 'pemasok_index.php');
}
if ($wilayah_id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'Wilayah wajib dipilih.', $kembali);
}
if ($nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama pemasok wajib diisi.', $kembali);
}
if (mb_strlen($nama) > 100 || mb_strlen($telepon) > 30) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter dan telepon maksimal 30 karakter.', $kembali);
}
if (mb_strlen($bank) > 50 || mb_strlen($nomor_rekening) > 30 || mb_strlen($atas_nama_rekening) > 100) {
    flash_dan_pindah('error', 'Gagal', 'Bank maksimal 50 karakter, nomor rekening maksimal 30 karakter, dan atas nama rekening maksimal 100 karakter.', $kembali);
}

// Kolom opsional: simpan NULL jika kosong
$alamat             = $alamat === '' ? null : $alamat;
$telepon            = $telepon === '' ? null : $telepon;
$bank               = $bank === '' ? null : $bank;
$nomor_rekening     = $nomor_rekening === '' ? null : $nomor_rekening;
$atas_nama_rekening = $atas_nama_rekening === '' ? null : $atas_nama_rekening;

try {
    // 1. Cek apakah wilayah berubah dari data sebelumnya untuk menentukan apakah perlu generate ulang kode
    $stmtCekWil = $conn->prepare("SELECT wilayah_id, kode FROM pemasok WHERE id = ?");
    $stmtCekWil->bind_param('i', $id);
    $stmtCekWil->execute();
    $dataLama = $stmtCekWil->get_result()->fetch_assoc();
    $stmtCekWil->close();

    if (!$dataLama) {
        throw new Exception("Data pemasok tidak ditemukan.");
    }

    $kodePemasok = $dataLama['kode'];

    // Jika wilayah_id berubah, generate ulang kode pemasok berdasarkan wilayah baru
    if ((int)$dataLama['wilayah_id'] !== $wilayah_id) {
        $stmtWil = $conn->prepare("SELECT kode FROM wilayah WHERE id = ?");
        $stmtWil->bind_param('i', $wilayah_id);
        $stmtWil->execute();
        $resWil = $stmtWil->get_result()->fetch_assoc();
        $stmtWil->close();

        if (!$resWil) {
            throw new Exception("Wilayah yang dipilih tidak valid.");
        }
        $kodeWilayah = $resWil['kode'];

        $prefix = $kodeWilayah . '-';
        $stmtGen = $conn->prepare("SELECT kode FROM pemasok WHERE kode LIKE ? AND id != ? ORDER BY id DESC LIMIT 1");
        $like = $prefix . '%';
        $stmtGen->bind_param('si', $like, $id);
        $stmtGen->execute();
        $resGen = $stmtGen->get_result()->fetch_assoc();
        $stmtGen->close();

        if ($resGen) {
            $noUrut = (int)substr($resGen['kode'], -5) + 1;
        } else {
            $noUrut = 1;
        }

        $kodePemasok = $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
    }

    // 2. Update data pemasok ke database
    $stmt = $conn->prepare("
        UPDATE pemasok
        SET kode = ?, wilayah_id = ?, nama = ?, alamat = ?, telepon = ?,
            bank = ?, nomor_rekening = ?, atas_nama_rekening = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'sissssssi',
        $kodePemasok, $wilayah_id, $nama, $alamat, $telepon,
        $bank, $nomor_rekening, $atas_nama_rekening, $id
    );
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Pemasok berhasil diperbarui.', 'pemasok_index.php');