<?php
include 'config.php';

$akun_id = (int)($_GET['akun_id'] ?? 0);
$dari    = $_GET['dari'] ?? date('Y-m-01');
$sampai  = $_GET['sampai'] ?? date('Y-m-t');

if ($akun_id <= 0) {
    echo '<div class="alert alert-warning">Akun tidak valid.</div>';
    exit;
}

// 1. Ambil informasi akun & saldo normalnya
$stmtAkun = $conn->prepare("SELECT kode, nama, saldo_normal FROM akun WHERE id = ?");
$stmtAkun->bind_param('i', $akun_id);
$stmtAkun->execute();
$akun = $stmtAkun->get_result()->fetch_assoc();
$stmtAkun->close();

if (!$akun) {
    echo '<div class="alert alert-danger">Akun tidak ditemukan.</div>';
    exit;
}

$saldoNormal = strtolower($akun['saldo_normal']) === 'kredit' ? 'Kredit' : 'Debit';

// 2. Hitung Saldo Awal (kumulatif mutasi langsung dari tabel buku_besar sebelum tanggal 'dari')
$stmtSaldoAwal = $conn->prepare("
    SELECT COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(kredit), 0) AS total_kredit
    FROM buku_besar
    WHERE akun_id = ? AND tanggal < ?
");
$stmtSaldoAwal->bind_param('is', $akun_id, $dari);
$stmtSaldoAwal->execute();
$resSaldo = $stmtSaldoAwal->get_result()->fetch_assoc();
$stmtSaldoAwal->close();

$debitAwal  = (float)$resSaldo['total_debit'];
$kreditAwal = (float)$resSaldo['total_kredit'];

if ($saldoNormal === 'Debit') {
    $saldoAwal = $debitAwal - $kreditAwal;
} else {
    $saldoAwal = $kreditAwal - $debitAwal;
}

// 3. Ambil data mutasi langsung dari tabel buku_besar beserta relasi ke tabel user (tanpa panen/faktur)
$stmtMutasi = $conn->prepare("
    SELECT bb.*, u.nama AS nama_user
    FROM buku_besar bb
    JOIN `user` u ON u.id = bb.user_id
    WHERE bb.akun_id = ? AND bb.tanggal BETWEEN ? AND ?
    ORDER BY bb.tanggal ASC, bb.timestamp ASC, bb.id ASC
");
$stmtMutasi->bind_param('iss', $akun_id, $dari, $sampai);
$stmtMutasi->execute();
$mutasiList = $stmtMutasi->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMutasi->close();
?>

<div class="mb-3">
    <strong>Akun:</strong> <?= htmlspecialchars($akun['kode'] . ' - ' . $akun['nama']) ?><br>
    <strong>Saldo Normal:</strong> <?= $saldoNormal ?><br>
    <strong>Periode:</strong> <?= date('d-m-Y', strtotime($dari)) ?> s/d <?= date('d-m-Y', strtotime($sampai)) ?>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-sm">
        <thead class="thead-light">
            <tr>
                <th>Tanggal</th>
                <th>Keterangan</th>
                <th>User</th>
                <th>Timestamp</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Kredit</th>
                <th class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <!-- Baris Saldo Awal -->
            <tr class="table-secondary font-weight-bold">
                <td colspan="6" class="text-right">SALDO AWAL PER <?= date('d-m-Y', strtotime($dari)) ?> :</td>
                <td class="text-right"><?= number_format($saldoAwal, 2, ',', '.') ?></td>
            </tr>

            <?php 
            $saldoBerjalan = $saldoAwal;
            $totalDebit = 0;
            $totalKredit = 0;

            if (empty($mutasiList)): 
            ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">Tidak ada mutasi transaksi pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($mutasiList as $row): 
                    $debit = (float)$row['debit'];
                    $kredit = (float)$row['kredit'];

                    if ($saldoNormal === 'Debit') {
                        $saldoBerjalan += ($debit - $kredit);
                    } else {
                        $saldoBerjalan += ($kredit - $debit);
                    }
                    
                    $totalDebit += $debit;
                    $totalKredit += $kredit;
                ?>
                    <tr>
                        <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                        <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['timestamp'] ?? '-') ?></td>
                        <td class="text-right"><?= $debit > 0 ? number_format($debit, 2, ',', '.') : '-' ?></td>
                        <td class="text-right"><?= $kredit > 0 ? number_format($kredit, 2, ',', '.') : '-' ?></td>
                        <td class="text-right font-weight-bold"><?= number_format($saldoBerjalan, 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot class="font-weight-bold table-light">
            <tr>
                <td colspan="4" class="text-right">TOTAL PERIODE INI:</td>
                <td class="text-right"><?= number_format($totalDebit, 2, ',', '.') ?></td>
                <td class="text-right"><?= number_format($totalKredit, 2, ',', '.') ?></td>
                <td class="text-right"><?= number_format($saldoBerjalan, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>