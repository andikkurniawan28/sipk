<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: laporan_laba_rugi_index.php');
    exit;
}

$bulan   = $_POST['bulan'] ?? '';
$tahun   = $_POST['tahun'] ?? '';
$user_id = $_SESSION['sipk-user_id'];

if (!preg_match('/^(0[1-9]|1[0-2])$/', $bulan) || !preg_match('/^\d{4}$/', $tahun)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Periode tidak valid.'];
    header('Location: laporan_laba_rugi_index.php');
    exit;
}

$tanggalMulai   = "$tahun-$bulan-01";
$tanggalSelesai = date('Y-m-t', strtotime($tanggalMulai));

$conn->begin_transaction();

try {
    // 1. Akun Laba Ditahan (dikunci agar dua penutupan tidak berjalan paralel)
    $kodeLaba = '301.02';
    $stmtLaba = $conn->prepare("SELECT id FROM akun WHERE kode = ? FOR UPDATE");
    $stmtLaba->bind_param('s', $kodeLaba);
    $stmtLaba->execute();
    $akunLaba = $stmtLaba->get_result()->fetch_assoc();
    $stmtLaba->close();

    if (!$akunLaba) {
        throw new Exception('Akun Laba Ditahan (301.02) tidak ditemukan.');
    }
    $labaDitahanId = (int)$akunLaba['id'];

    // 2. Hapus jurnal penutup lama pada periode ini agar tidak double entry
    $stmtOld = $conn->prepare("SELECT id FROM jurnal WHERE tutupan = 1 AND tanggal BETWEEN ? AND ? FOR UPDATE");
    $stmtOld->bind_param('ss', $tanggalMulai, $tanggalSelesai);
    $stmtOld->execute();
    $oldIds = array_column($stmtOld->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $stmtOld->close();

    $jumlahDihapus = count($oldIds);
    if ($jumlahDihapus > 0) {
        $in = implode(',', array_map('intval', $oldIds));
        $conn->query("DELETE FROM buku_besar WHERE jurnal_id IN ($in)");
        $conn->query("DELETE FROM jurnal_detail WHERE jurnal_id IN ($in)");
        $conn->query("DELETE FROM jurnal WHERE id IN ($in)");
    }

    // 3. Saldo bersih tiap akun Pendapatan/Beban pada periode ini
    $stmt = $conn->prepare("
        SELECT a.id,
               COALESCE(SUM(bb.debit), 0)  AS d,
               COALESCE(SUM(bb.kredit), 0) AS k
        FROM akun a
        JOIN buku_besar bb
              ON bb.akun_id = a.id
             AND bb.tanggal BETWEEN ? AND ?
             AND NOT EXISTS (
                    SELECT 1 FROM jurnal j
                    WHERE j.id = bb.jurnal_id AND j.tutupan = 1
             )
        WHERE a.kelompok IN ('Pendapatan', 'Beban')
        GROUP BY a.id
        HAVING ROUND(SUM(bb.debit) - SUM(bb.kredit), 2) <> 0
    ");
    $stmt->bind_param('ss', $tanggalMulai, $tanggalSelesai);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) {
        throw new Exception('Tidak ada mutasi Pendapatan/Beban yang perlu ditutup pada periode ini.');
    }

    // 4. Counter (negasi) tiap akun yang bernilai
    $lines     = [];
    $sumDebit  = 0.0;
    $sumKredit = 0.0;
    foreach ($rows as $r) {
        $net = round((float)$r['d'] - (float)$r['k'], 2);
        if ($net > 0) {
            // Saldo debit (Beban) -> counter di kredit
            $lines[] = ['akun_id' => (int)$r['id'], 'debit' => 0.0, 'kredit' => $net];
            $sumKredit += $net;
        } else {
            // Saldo kredit (Pendapatan) -> counter di debit
            $lines[] = ['akun_id' => (int)$r['id'], 'debit' => -$net, 'kredit' => 0.0];
            $sumDebit += -$net;
        }
    }

    // Penyeimbang ke Laba Ditahan: laba -> kredit, rugi -> debit
    $selisih = round($sumDebit - $sumKredit, 2);
    if ($selisih > 0) {
        $lines[] = ['akun_id' => $labaDitahanId, 'debit' => 0.0, 'kredit' => $selisih];
    } elseif ($selisih < 0) {
        $lines[] = ['akun_id' => $labaDitahanId, 'debit' => -$selisih, 'kredit' => 0.0];
    }

    // 5. Kode jurnal: TUP + ymd + urut 5 digit
    $prefix = 'TUP' . date('ymd');
    $stmtKode = $conn->prepare("SELECT kode FROM jurnal WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmtKode->bind_param('s', $like);
    $stmtKode->execute();
    $last = $stmtKode->get_result()->fetch_assoc();
    $stmtKode->close();
    $noUrut = $last ? (int)substr($last['kode'], -5) + 1 : 1;
    $kode = $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);

    // 6. Header jurnal (tutupan = 1)
    $ket = "Jurnal Penutup Laba Rugi Periode $bulan/$tahun";
    $stmtJ = $conn->prepare("INSERT INTO jurnal (tanggal, kode, user_id, keterangan, tutupan) VALUES (?, ?, ?, ?, 1)");
    $stmtJ->bind_param('ssis', $tanggalSelesai, $kode, $user_id, $ket);
    $stmtJ->execute();
    $jurnal_id = $stmtJ->insert_id;
    $stmtJ->close();

    // 7. Detail jurnal + buku besar
    $stmtD = $conn->prepare("INSERT INTO jurnal_detail (jurnal_id, akun_id, debit, kredit) VALUES (?, ?, ?, ?)");
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, jurnal_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($lines as $ln) {
        $stmtD->bind_param('iidd', $jurnal_id, $ln['akun_id'], $ln['debit'], $ln['kredit']);
        $stmtD->execute();

        $stmtBB->bind_param('siiisdd', $tanggalSelesai, $jurnal_id, $ln['akun_id'], $user_id, $ket, $ln['debit'], $ln['kredit']);
        $stmtBB->execute();
    }
    $stmtD->close();
    $stmtBB->close();

    $conn->commit();

    $pesan = "Periode $bulan/$tahun berhasil ditutup (jurnal $kode).";
    if ($jumlahDihapus > 0) {
        $pesan .= " $jumlahDihapus jurnal penutup lama diganti.";
    }
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => $pesan];
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
}

header('Location: laporan_laba_rugi_index.php?bulan=' . urlencode($bulan) . '&tahun=' . urlencode($tahun));
exit;