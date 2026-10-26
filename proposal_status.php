<?php
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function selesai(string $icon, string $title, string $text, int $id = 0): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . ($id > 0 ? 'proposal_lihat.php?id=' . $id : 'proposal_index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: proposal_index.php');
    exit;
}

$user_id = (int)($_SESSION['sipk-user_id'] ?? 0);
if ($user_id <= 0) {
    selesai('error', 'Gagal', 'Sesi berakhir, silakan login ulang.');
}

$id   = (int)($_POST['id'] ?? 0);
$aksi = (string)($_POST['aksi'] ?? '');
if ($id <= 0) {
    selesai('error', 'Gagal', 'ID proposal tidak valid.');
}

// dari  = status asal yang diizinkan
// ke    = status tujuan
// role  = role yang boleh melakukan aksi
// keputusan = true -> isi disetujui_oleh & disetujui_pada
$aturan = [
    'ajukan'  => ['dari' => ['Draft'],             'ke' => 'Diajukan',   'role' => ['Direktur', 'Tanaman'], 'keputusan' => false],
    'setujui' => ['dari' => ['Diajukan'],          'ke' => 'Disetujui',  'role' => ['Direktur'],            'keputusan' => true],
    'tolak'   => ['dari' => ['Diajukan'],          'ke' => 'Ditolak',    'role' => ['Direktur'],            'keputusan' => true],
    'batal'   => ['dari' => ['Draft', 'Diajukan'], 'ke' => 'Dibatalkan', 'role' => ['Direktur'],            'keputusan' => false],
];
if (!isset($aturan[$aksi])) {
    selesai('error', 'Gagal', 'Aksi tidak dikenal.', $id);
}
$a = $aturan[$aksi];

try {
    $conn->begin_transaction();

    // Role user yang sedang login
    $stmt = $conn->prepare('SELECT role FROM `user` WHERE id = ? AND aktif = 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$u) {
        $conn->rollback();
        selesai('error', 'Gagal', 'User tidak valid.');
    }
    if (!in_array($u['role'], $a['role'], true)) {
        $conn->rollback();
        selesai('error', 'Ditolak', 'Role ' . $u['role'] . ' tidak berhak melakukan aksi ini.', $id);
    }

    // Kunci baris proposal agar aman dari klik ganda
    $stmt = $conn->prepare('SELECT status, kode FROM proposal WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$p) {
        $conn->rollback();
        selesai('error', 'Gagal', 'Proposal tidak ditemukan.');
    }
    if (!in_array($p['status'], $a['dari'], true)) {
        $conn->rollback();
        selesai('error', 'Gagal', 'Status proposal saat ini (' . $p['status'] . ') tidak bisa diproses dengan aksi ini.', $id);
    }

    if ($a['keputusan']) {
        $stmt = $conn->prepare('UPDATE proposal SET status = ?, disetujui_oleh = ?, disetujui_pada = NOW() WHERE id = ?');
        $stmt->bind_param('sii', $a['ke'], $user_id, $id);
    } else {
        $stmt = $conn->prepare('UPDATE proposal SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $a['ke'], $id);
    }
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('proposal_status: ' . $e->getMessage());
    selesai('error', 'Gagal', 'Status proposal tidak dapat diubah.', $id);
}

selesai('success', 'Berhasil', 'Proposal ' . $p['kode'] . ' sekarang berstatus ' . $a['ke'] . '.', $id);