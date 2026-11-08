<?php
include 'config.php';

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

if (empty($bulan) || empty($tahun)) {
    echo '<div class="alert alert-warning">Filter bulan atau tahun tidak valid.</div>';
    exit;
}

// Cari tanggal terakhir dari bulan dan tahun yang dipilih (Contoh: 2026-06-30)
$tanggalSampai = date('Y-m-t', strtotime("$tahun-$bulan-01"));

// Ambil daftar nama bulan untuk ditampilkan di judul laporan
$listBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$namaBulanTampil = ($listBulan[$bulan] ?? '') . ' ' . $tahun;

// Ambil seluruh akun beserta saldo totalnya sampai tanggal tersebut berdasarkan buku_besar
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
    LEFT JOIN buku_besar bb ON bb.akun_id = a.id AND bb.tanggal <= ?
    GROUP BY a.id, a.kode, a.nama, a.kelompok, a.saldo_normal
    ORDER BY a.kode ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $tanggalSampai);
$stmt->execute();
$akunList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$aset = [];
$kewajiban = [];
$ekuitas = [];

$totalAset = 0;
$totalKewajiban = 0;
$totalEkuitas = 0;

$totalPendapatan = 0;
$totalBeban = 0;

foreach ($akunList as $row) {
    $debit = (float)$row['total_debit'];
    $kredit = (float)$row['total_kredit'];
    $saldoNormal = $row['saldo_normal'];
    $kelompok = $row['kelompok'];

    if ($saldoNormal === 'Debit') {
        $saldo = $debit - $kredit;
    } else {
        $saldo = $kredit - $debit;
    }

    if ($kelompok === 'Aset') {
        $aset[] = ['kode' => $row['kode'], 'nama' => $row['nama'], 'saldo' => $saldo];
        $totalAset += $saldo;
    } elseif ($kelompok === 'Kewajiban') {
        $kewajiban[] = ['kode' => $row['kode'], 'nama' => $row['nama'], 'saldo' => $saldo];
        $totalKewajiban += $saldo;
    } elseif ($kelompok === 'Ekuitas') {
        $ekuitas[] = ['kode' => $row['kode'], 'nama' => $row['nama'], 'saldo' => $saldo];
        $totalEkuitas += $saldo;
    } elseif ($kelompok === 'Pendapatan') {
        $totalPendapatan += $saldo;
    } elseif ($kelompok === 'Beban') {
        $totalBeban += $saldo;
    }
}

// Laba Berjalan = Pendapatan - Beban
$labaBerjalan = $totalPendapatan - $totalBeban;
if ($labaBerjalan != 0) {
    $ekuitas[] = ['kode' => 'L/R', 'nama' => 'Laba / Rugi Berjalan', 'saldo' => $labaBerjalan];
    $totalEkuitas += $labaBerjalan;
}

$totalKewajibanDanEkuitas = $totalKewajiban + $totalEkuitas;
?>

<div class="mb-3 text-center">
    <h5 class="font-weight-bold text-gray-800">LAPORAN NERACA</h5>
    <span class="text-muted">Periode s/d Bulan: <?= h($namaBulanTampil) ?></span>
</div>

<div class="row">
    <!-- Kolom Aset / Aktiva -->
    <div class="col-md-6">
        <h6 class="font-weight-bold text-primary border-bottom pb-2">AKTIVA (ASET)</h6>
        <table class="table table-bordered table-sm">
            <thead class="thead-light">
                <tr>
                    <th>Akun</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($aset)): ?>
                    <tr><td colspan="2" class="text-center text-muted">Tidak ada data aset.</td></tr>
                <?php else: ?>
                    <?php foreach ($aset as $as): ?>
                        <tr>
                            <td><?= h($as['kode'] . ' - ' . $as['nama']) ?></td>
                            <td class="text-right"><?= number_format($as['saldo'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="font-weight-bold table-light">
                <tr>
                    <td class="text-right">TOTAL ASET:</td>
                    <td class="text-right text-primary"><?= number_format($totalAset, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Kolom Kewajiban & Ekuitas (Pasiva) -->
    <div class="col-md-6">
        <h6 class="font-weight-bold text-primary border-bottom pb-2">KEWAJIBAN & EKUITAS (PASIVA)</h6>
        <table class="table table-bordered table-sm">
            <thead class="thead-light">
                <tr>
                    <th>Akun</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <!-- Kewajiban -->
                <tr class="table-secondary font-weight-bold"><td colspan="2">KEWAJIBAN (UTANG)</td></tr>
                <?php if (empty($kewajiban)): ?>
                    <tr><td colspan="2" class="text-center text-muted">Tidak ada data kewajiban.</td></tr>
                <?php else: ?>
                    <?php foreach ($kewajiban as $kw): ?>
                        <tr>
                            <td class="pl-3"><?= h($kw['kode'] . ' - ' . $kw['nama']) ?></td>
                            <td class="text-right"><?= number_format($kw['saldo'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Ekuitas -->
                <tr class="table-secondary font-weight-bold"><td colspan="2">EKUITAS (MODAL)</td></tr>
                <?php if (empty($ekuitas)): ?>
                    <tr><td colspan="2" class="text-center text-muted">Tidak ada data ekuitas.</td></tr>
                <?php else: ?>
                    <?php foreach ($ekuitas as $ek): ?>
                        <tr>
                            <td class="pl-3"><?= h($ek['kode'] . ' - ' . $ek['nama']) ?></td>
                            <td class="text-right"><?= number_format($ek['saldo'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="font-weight-bold table-light">
                <tr>
                    <td class="text-right">TOTAL KEWAJIBAN & EKUITAS:</td>
                    <td class="text-right text-primary"><?= number_format($totalKewajibanDanEkuitas, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>