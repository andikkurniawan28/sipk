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

$id           = (int)($_POST['id'] ?? 0);
$kode         = trim($_POST['kode'] ?? '');
$nama         = trim($_POST['nama'] ?? '');
$kelompok     = $_POST['kelompok'] ?? '';
$saldo_normal = $_POST['saldo_normal'] ?? '';

$kembali = 'akun_edit.php?id=' . $id;

if ($id <= 0) {
    flash_dan_pindah('error', 'Gagal', 'ID akun tidak valid.', 'akun_index.php');
}
if ($kode === '' || $nama === '') {
    flash_dan_pindah('error', 'Gagal', 'Kode dan nama akun wajib diisi.', $kembali);
}
if (mb_strlen($kode) > 20 || mb_strlen($nama) > 100) {
    flash_dan_pindah('error', 'Gagal', 'Kode maksimal 20 karakter dan nama maksimal 100 karakter.', $kembali);
}
if (!in_array($kelompok, ['Aset', 'Kewajiban', 'Ekuitas', 'Pendapatan', 'Beban'], true)) {
    flash_dan_pindah('error', 'Gagal', 'Kelompok akun tidak valid.', $kembali);
}
if (!in_array($saldo_normal, ['Debit', 'Kredit'], true)) {
    flash_dan_pindah('error', 'Gagal', 'Saldo normal harus Debit atau Kredit.', $kembali);
}

try {
    $stmt = $conn->prepare("UPDATE akun SET kode = ?, nama = ?, kelompok = ?, saldo_normal = ? WHERE id = ?");
    $stmt->bind_param('ssssi', $kode, $nama, $kelompok, $saldo_normal, $id);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) {
        flash_dan_pindah('error', 'Gagal', 'Kode akun sudah dipakai akun lain.', $kembali);
    }
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
} catch (Throwable $e) {
    flash_dan_pindah('error', 'Gagal', 'Data tidak dapat diperbarui: ' . $e->getMessage(), $kembali);
}

flash_dan_pindah('success', 'Berhasil', 'Akun berhasil diperbarui.', 'akun_index.php');