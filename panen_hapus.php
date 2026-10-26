<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'ID panen tidak valid.'];
    header('Location: panen_index.php');
    exit;
}

try {
    $conn->begin_transaction();

    // 1. Ambil informasi jurnal_id yang terkait dengan panen ini dari tabel buku_besar atau jurnal
    // (Opsional jika foreign key cascade sudah aktif, tetapi manual delete jauh lebih aman untuk data keuangan)
    $stmtCariJurnal = $conn->prepare("SELECT DISTINCT jurnal_id FROM buku_besar WHERE panen_id = ? AND jurnal_id IS NOT NULL");
    $stmtCariJurnal->bind_param('i', $id);
    $stmtCariJurnal->execute();
    $resJurnal = $stmtCariJurnal->get_result();
    $jurnalIds = [];
    while ($row = $resJurnal->fetch_assoc()) {
        $jurnalIds[] = (int)$row['jurnal_id'];
    }
    $stmtCariJurnal->close();

    // 2. Hapus data dari buku_besar yang terikat dengan panen_id ini
    $stmtBB = $conn->prepare("DELETE FROM buku_besar WHERE panen_id = ?");
    $stmtBB->bind_param('i', $id);
    $stmtBB->execute();
    $stmtBB->close();

    // 3. Jika ada jurnal umum terkait, hapus jurnal_detail dan jurnal-nya (akan cascade ke jurnal_detail jika diset, tapi aman dihapus manual)
    if (!empty($jurnalIds)) {
        $idsPlaceholder = implode(',', array_fill(0, count($jurnalIds), '?'));
        $types = str_repeat('i', count($jurnalIds));

        // Hapus jurnal detail
        $stmtJD = $conn->prepare("DELETE FROM jurnal_detail WHERE jurnal_id IN ($idsPlaceholder)");
        $stmtJD->bind_param($types, ...$jurnalIds);
        $stmtJD->execute();
        $stmtJD->close();

        // Hapus jurnal utama
        $stmtJ = $conn->prepare("DELETE FROM jurnal WHERE id IN ($idsPlaceholder)");
        $stmtJ->bind_param($types, ...$jurnalIds);
        $stmtJ->execute();
        $stmtJ->close();
    }

    // 4. Hapus detail panen
    $stmtDetail = $conn->prepare("DELETE FROM panen_detail WHERE panen_id = ?");
    $stmtDetail->bind_param('i', $id);
    $stmtDetail->execute();
    $stmtDetail->close();

    // 5. Hapus header panen
    $stmtPanen = $conn->prepare("DELETE FROM panen WHERE id = ?");
    $stmtPanen->bind_param('i', $id);
    $stmtPanen->execute();
    $stmtPanen->close();

    $conn->commit();

    $_SESSION['flash'] = [
        'icon' => 'success',
        'title' => 'Berhasil',
        'text' => 'Data panen beserta rujukan jurnal dan buku besarnya berhasil dihapus.'
    ];
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = [
        'icon' => 'error',
        'title' => 'Gagal',
        'text' => 'Gagal menghapus data panen: ' . $e->getMessage()
    ];
}

header('Location: panen_index.php');
exit;