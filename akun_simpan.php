<?php
include 'config.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: akun_index.php');
    exit;
}

$kode         = trim($_POST['kode'] ?? '');
$nama         = trim($_POST['nama'] ?? '');
$saldo_normal = $_POST['saldo_normal'] ?? '';
$saldo_awal   = $_POST['saldo_awal'] ?? '';

if ($kode === '' || $nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Kode dan nama akun wajib diisi.', 'akun_tambah.php');
}
if (mb_strlen($kode) > 20 || mb_strlen($nama) > 100) {
    flash_dan_pindah('error', 'Gagal', 'Kode maksimal 20 karakter dan nama maksimal 100 karakter.', 'akun_tambah.php');
}
if (!in_array($saldo_normal, ['Debit', 'Kredit'], true)) {
    flash_dan_pindah('error', 'Gagal', 'Saldo normal harus Debit atau Kredit.', 'akun_tambah.php');
}
if (!is_numeric($saldo_awal)) {
    flash_dan_pindah('error', 'Gagal', 'Saldo awal harus berupa angka.', 'akun_tambah.php');
}

$saldo_awal = (float)$saldo_awal;

try {
    $stmt = $conn->prepare("INSERT INTO akun (kode, nama, saldo_normal, saldo_awal) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssd', $kode, $nama, $saldo_normal, $saldo_awal);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) {
        flash_dan_pindah('error', 'Gagal', 'Kode akun sudah dipakai, gunakan kode lain.', 'akun_tambah.php');
    }
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'akun_tambah.php');
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat disimpan: ' . $e->getMessage(), 'akun_tambah.php');
}

flash_dan_pindah('success', 'Berhasil', 'Akun berhasil ditambahkan.', 'akun_index.php');