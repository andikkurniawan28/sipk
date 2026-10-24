<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: petani_index.php');
    exit;
}

$wilayah_id = (int)($_POST['wilayah_id'] ?? 0);
$nama       = trim($_POST['nama'] ?? '');
$alamat     = trim($_POST['alamat'] ?? '');
$telepon    = trim($_POST['telepon'] ?? '');
$user_id    = (int)($_SESSION['sipk-user_id'] ?? 0);

if ($wilayah_id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'Wilayah wajib dipilih.', 'petani_tambah.php');
}
if ($nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Nama petani wajib diisi.', 'petani_tambah.php');
}
if (mb_strlen($nama) > 100 || mb_strlen($telepon) > 30) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter dan telepon maksimal 30 karakter.', 'petani_tambah.php');
}

// Kolom opsional: simpan NULL jika kosong
$alamat  = $alamat === '' ? null : $alamat;
$telepon = $telepon === '' ? null : $telepon;

try {
    // 1. Ambil kode dari tabel wilayah berdasarkan wilayah_id
    $stmtWil = $conn->prepare("SELECT kode FROM wilayah WHERE id = ?");
    $stmtWil->bind_param('i', $wilayah_id);
    $stmtWil->execute();
    $resWil = $stmtWil->get_result()->fetch_assoc();
    $stmtWil->close();

    if (!$resWil) {
        throw new Exception("Wilayah yang dipilih tidak valid.");
    }
    $kodeWilayah = $resWil['kode'];

    // 2. Generate kode petani otomatis berdasarkan wilayah: KodeWilayah-00001
    $prefix = $kodeWilayah . '-';
    $stmtCek = $conn->prepare("SELECT kode FROM petani WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmtCek->bind_param('s', $like);
    $stmtCek->execute();
    $resCek = $stmtCek->get_result()->fetch_assoc();
    $stmtCek->close();

    if ($resCek) {
        $noUrut = (int)substr($resCek['kode'], -5) + 1;
    } else {
        $noUrut = 1;
    }

    $kodePetani = $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);

    // 3. Simpan data petani ke database
    $stmt = $conn->prepare("INSERT INTO petani (kode, wilayah_id, nama, alamat, telepon, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sisssi', $kodePetani, $wilayah_id, $nama, $alamat, $telepon, $user_id);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'petani_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Petani berhasil ditambahkan dengan kode: ' . $kodePetani, 'petani_index.php');