<?php
include 'config.php';

$bulan = $_GET['bulan'] ?? '';
$tahun = $_GET['tahun'] ?? '';

if (!preg_match('/^(0[1-9]|1[0-2])$/', $bulan) || !preg_match('/^\d{4}$/', $tahun)) {
    echo '<div class="alert alert-warning">Filter bulan atau tahun tidak valid.</div>';
    exit;
}

// Rentang tanggal awal sampai akhir pada bulan tersebut
$tanggalMulai   = "$tahun-$bulan-01";
$tanggalSelesai = date('Y-m-t', strtotime($tanggalMulai));

$listBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$namaBulanTampil = ($listBulan[$bulan] ?? '') . ' ' . $tahun;

// Akun Pendapatan/Beban beserta mutasi periode ini.
// Baris buku besar dari jurnal penutup (tutupan = 1) tidak dihitung.
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

// Apakah periode ini sudah pernah ditutup?
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

    // Saldo menurut saldo normal akun
    $saldo = ($saldoNormal === 'Debit') ? $debit - $kredit : $kredit - $debit;

    if ($kelompok === 'Pendapatan') {
        $pendapatan[] = ['kode' => $row['kode'], 'nama' => $row['nama'], 'saldo' => $saldo];
        $totalPendapatan += $saldo;
    } elseif ($kelompok === 'Beban') {
        $beban[] = ['kode' => $row['kode'], 'nama' => $row['nama'], 'saldo' => $saldo];
        $totalBeban += $saldo;
    }
}

// Laba Bersih = Total Pendapatan - Total Beban
$labaBersih = $totalPendapatan - $totalBeban;
?>

<div class="mb-3 text-center">
    <h5 class="font-weight-bold text-gray-800">LAPORAN LABA RUGI</h5>
    <span class="text-muted">Periode Bulan: <?= h($namaBulanTampil) ?></span>
    <?php if ($pernahDitutup): ?>
        <div class="mt-1"><span class="badge badge-secondary"><i class="fas fa-lock"></i> Sudah pernah ditutup</span></div>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-12">
        <!-- Pendapatan -->
        <h6 class="font-weight-bold text-primary border-bottom pb-2">PENDAPATAN</h6>
        <table class="table table-bordered table-sm mb-4">
            <thead class="thead-light">
                <tr>
                    <th>Akun</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendapatan)): ?>
                    <tr><td colspan="2" class="text-center text-muted">Tidak ada data pendapatan pada periode ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($pendapatan as $p): ?>
                        <tr>
                            <td><?= h($p['kode'] . ' - ' . $p['nama']) ?></td>
                            <td class="text-right"><?= number_format($p['saldo'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="font-weight-bold table-light">
                <tr>
                    <td class="text-right">TOTAL PENDAPATAN:</td>
                    <td class="text-right text-primary"><?= number_format($totalPendapatan, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Beban -->
        <h6 class="font-weight-bold text-primary border-bottom pb-2">BEBAN</h6>
        <table class="table table-bordered table-sm mb-4">
            <thead class="thead-light">
                <tr>
                    <th>Akun</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($beban)): ?>
                    <tr><td colspan="2" class="text-center text-muted">Tidak ada data beban pada periode ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($beban as $b): ?>
                        <tr>
                            <td><?= h($b['kode'] . ' - ' . $b['nama']) ?></td>
                            <td class="text-right"><?= number_format($b['saldo'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="font-weight-bold table-light">
                <tr>
                    <td class="text-right">TOTAL BEBAN:</td>
                    <td class="text-right text-danger"><?= number_format($totalBeban, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Ringkasan Laba Bersih -->
        <div class="card bg-light border-left-primary shadow py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Laba / Rugi Bersih Periode Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($labaBersih, 0, ',', '.') ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tutup Periode -->
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
    </div>
</div>