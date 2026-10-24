<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: wilayah_index.php');
    exit;
}

// Kode selalu disimpan huruf besar (kolom tidak membedakan huruf besar/kecil, jadi "aa" dan "AA" dianggap sama)
$kode = strtoupper(trim($_POST['kode'] ?? ''));
$nama = trim($_POST['nama'] ?? '');

if ($kode === '' || $nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Kode dan nama wilayah wajib diisi.', 'wilayah_tambah.php');
}
if (!preg_match('/^[A-Z0-9]{2}$/', $kode)) {
    flash_dan_pindah('error', 'Gagal', 'Kode harus tepat 2 karakter berupa huruf atau angka.', 'wilayah_tambah.php');
}
if (mb_strlen($nama) > 100) {
    flash_dan_pindah('error', 'Gagal', 'Nama maksimal 100 karakter.', 'wilayah_tambah.php');
}

try {
    $stmt = $conn->prepare("INSERT INTO wilayah (kode, nama) VALUES (?, ?)");
    $stmt->bind_param('ss', $kode, $nama);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) {
        flash_dan_pindah('error', 'Gagal', 'Kode wilayah sudah dipakai, gunakan kode lain.', 'wilayah_tambah.php');
    }
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'wilayah_tambah.php');
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'wilayah_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Wilayah berhasil ditambahkan.', 'wilayah_index.php');