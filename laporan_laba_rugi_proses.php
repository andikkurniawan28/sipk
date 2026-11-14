<?php
include 'config.php';

$bulan = $_GET['bulan'] ?? '';
$tahun = $_GET['tahun'] ?? '';

if (!preg_match('/^(0[1-9]|1[0-2])$/', $bulan) || !preg_match('/^\d{4}$/', $tahun)) {
    echo '<div class="alert alert-warning">Filter bulan atau tahun tidak valid.</div>';
    exit;
}

$tanggalMulai   = "$tahun-$bulan-01";
$tanggalSelesai = date('Y-m-t', strtotime($tanggalMulai));

$listBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$namaBulanTampil = ($listBulan[$bulan] ?? '') . ' ' . $tahun;

$query = "
    SELECT 
        a.id, 
        a.kode, 
        a.nama, 
        a.kelompok, 
        a.saldo_normal,
        COALESCE(SUM(bb.debit), 0) AS total_debit,
        COALESCE(SUM(bb.kredit), 0) AS total_kredit
    FROM akun a
    LEFT JOIN buku_besar bb 
            ON bb.akun_id = a.id 
           AND bb.tanggal BETWEEN ? AND ?
           AND NOT EXISTS (
                SELECT 1 FROM jurnal j 
                WHERE j.id = bb.jurnal_id AND j.tutupan = 1
         )
    WHERE a.kelompok IN ('Pendapatan', 'Beban')
    GROUP BY a.id, a.kode, a.nama, a.kelompok, a.saldo_normal
    ORDER BY a.kode ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $tanggalMulai, $tanggalSelesai);
$stmt->execute();
$akunList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmtTutup = $conn->prepare("SELECT COUNT(*) AS n FROM jurnal WHERE tutupan = 1 AND tanggal BETWEEN ? AND ?");
$stmtTutup->bind_param('ss', $tanggalMulai, $tanggalSelesai);
$stmtTutup->execute();
$pernahDitutup = (int)$stmtTutup->get_result()->fetch_assoc()['n'] > 0;
$stmtTutup->close();

$pendapatan = [];
$beban = [];
$totalPendapatan = 0;
$totalBeban = 0;
$adaMutasi = false;

foreach ($akunList as $row) {
    $debit       = (float)$row['total_debit'];
    $kredit      = (float)$row['total_kredit'];
    $saldoNormal = $row['saldo_normal'];
    $kelompok    = $row['kelompok'];

    if (round($debit - $kredit, 2) != 0) {
        $adaMutasi = true;
    }

    $saldo = ($saldoNormal === 'Debit') ? ($debit - $kredit) : ($kredit - $debit);

    if ($kelompok === 'Pendapatan') {
        $pendapatan[] = ['kode' => $row['kode'], 'nama' => $row['nama'], 'saldo' => $saldo];
        $totalPendapatan += $saldo;
    } elseif ($kelompok === 'Beban') {
        $beban[] = ['kode' => $row['kode'], 'nama' => $row['nama'], 'saldo' => $saldo];
        $totalBeban += $saldo;
    }
}

$labaBersih = $totalPendapatan - $totalBeban;
?>

<div class="text-center mb-4">
    <h5 class="font-weight-bold text-gray-800 text-uppercase mb-1">LAPORAN LABA RUGI</h5>
    <span class="text-muted">Periode Bulan: <?= h($namaBulanTampil) ?></span>
    <?php if ($pernahDitutup): ?>
        <div class="mt-2"><span class="badge badge-secondary"><i class="fas fa-lock"></i> Sudah pernah ditutup</span></div>
    <?php endif; ?>
</div>

<!-- Tabel Pendapatan -->
<h6 class="font-weight-bold text-primary border-bottom pb-2 mb-3">PENDAPATAN</h6>
<div class="table-responsive mb-4">
    <table class="table table-bordered table-sm table-striped mb-0 w-100" style="table-layout: fixed;">
        <thead class="thead-light">
            <tr>
                <th>Akun</th>
                <th class="text-right" style="width: 140px;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pendapatan)): ?>
                <tr><td colspan="2" class="text-center text-muted">Tidak ada data pendapatan pada periode ini.</td></tr>
            <?php else: ?>
                <?php foreach ($pendapatan as $p): ?>
                    <tr>
                        <td class="text-break"><?= h($p['kode'] . ' - ' . $p['nama']) ?></td>
                        <td class="text-right text-nowrap"><?= number_format($p['saldo'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot class="font-weight-bold table-light">
            <tr>
                <td class="text-right">TOTAL PENDAPATAN:</td>
                <td class="text-right text-primary text-nowrap"><?= number_format($totalPendapatan, 0, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Tabel Beban -->
<h6 class="font-weight-bold text-primary border-bottom pb-2 mb-3">BEBAN</h6>
<div class="table-responsive mb-4">
    <table class="table table-bordered table-sm table-striped mb-0 w-100" style="table-layout: fixed;">
        <thead class="thead-light">
            <tr>
                <th>Akun</th>
                <th class="text-right" style="width: 140px;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($beban)): ?>
                <tr><td colspan="2" class="text-center text-muted">Tidak ada data beban pada periode ini.</td></tr>
            <?php else: ?>
                <?php foreach ($beban as $b): ?>
                    <tr>
                        <td class="text-break"><?= h($b['kode'] . ' - ' . $b['nama']) ?></td>
                        <td class="text-right text-nowrap"><?= number_format($b['saldo'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot class="font-weight-bold table-light">
            <tr>
                <td class="text-right">TOTAL BEBAN:</td>
                <td class="text-right text-danger text-nowrap"><?= number_format($totalBeban, 0, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Ringkasan Laba Bersih -->
<div class="card bg-light border-left-primary shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row align-items-center no-gutters">
            <div class="col mr-2">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Laba / Rugi Bersih Periode Ini</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800 text-break"><?= number_format($labaBersih, 0, ',', '.') ?></div>
            </div>
            <div class="col-auto">
                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Tutup Periode -->
<?php if ($adaMutasi): ?>
    <form action="laporan_laba_rugi_tutup.php" method="post" id="form-tutup" class="mt-3 no-print">
        <input type="hidden" name="bulan" value="<?= h($bulan) ?>">
        <input type="hidden" name="tahun" value="<?= h($tahun) ?>">
        <button type="submit" class="btn btn-danger btn-sm">
            <i class="fas fa-lock"></i> Tutup Periode <?= h($namaBulanTampil) ?>
        </button>
    </form>
<?php else: ?>
    <div class="text-muted small mt-3 no-print">Tidak ada mutasi yang perlu ditutup pada periode ini.</div>
<?php endif; ?>