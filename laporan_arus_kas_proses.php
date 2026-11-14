<?php
include 'config.php';

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

if (empty($bulan) || empty($tahun)) {
    echo '<div class="alert alert-warning">Filter bulan atau tahun tidak valid.</div>';
    exit;
}

$tanggalMulai = "$tahun-$bulan-01";
$tanggalSelesai = date('Y-m-t', strtotime($tanggalMulai));

$listBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$namaBulanTampil = ($listBulan[$bulan] ?? '') . ' ' . $tahun;

// 1. Hitung Saldo Awal Kas & Bank (Sebelum tanggal mulai)
$querySaldoAwal = "
    SELECT COALESCE(SUM(bb.debit - bb.kredit), 0) AS saldo_awal
    FROM buku_besar bb
    JOIN akun a ON a.id = bb.akun_id
    WHERE (a.nama LIKE '%Kas%' OR a.nama LIKE '%Bank%') AND bb.tanggal < ?
";
$stmtAwal = $conn->prepare($querySaldoAwal);
$stmtAwal->bind_param('s', $tanggalMulai);
$stmtAwal->execute();
$saldoAwal = (float)$stmtAwal->get_result()->fetch_assoc()['saldo_awal'];
$stmtAwal->close();

// 2. Ambil Mutasi Buku Besar Kas/Bank pada periode tersebut beserta informasi akun pasangannya jika ada
$queryMutasi = "
    SELECT bb.tanggal, bb.keterangan, bb.debit, bb.kredit, a.nama AS nama_akun
    FROM buku_besar bb
    JOIN akun a ON a.id = bb.akun_id
    WHERE (a.nama LIKE '%Kas%' OR a.nama LIKE '%Bank%') AND bb.tanggal BETWEEN ? AND ?
    ORDER BY bb.tanggal ASC, bb.id ASC
";
$stmtMut = $conn->prepare($queryMutasi);
$stmtMut->bind_param('ss', $tanggalMulai, $tanggalSelesai);
$stmtMut->execute();
$mutasiKas = $stmtMut->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMut->close();

$arusOperasional = [];
$arusInvestasi = [];
$arusPendanaan = [];

$totOperasional = 0;
$totInvestasi = 0;
$totPendanaan = 0;

foreach ($mutasiKas as $m) {
    // Nilai kas masuk (debit) bernilai positif, kas keluar (kredit) bernilai negatif
    $nilaiKas = (float)$m['debit'] - (float)$m['kredit'];
    $ket = strtolower($m['keterangan']);

    // Klasifikasi Berdasarkan Kata Kunci Transaksi Sistem Anda
    if (strpos($ket, 'pembelian') !== false || strpos($ket, 'penjualan') !== false || strpos($ket, 'piutang') !== false || strpos($ket, 'beban') !== false || strpos($ket, 'gaji') !== false || strpos($ket, 'operasional') !== false || strpos($ket, 'pembayaran') !== false) {
        $arusOperasional[] = $m;
        $totOperasional += $nilaiKas;
    } elseif (strpos($ket, 'aset tetap') !== false || strpos($ket, 'inventaris') !== false || strpos($ket, 'peralatan') !== false || strpos($ket, 'mesin') !== false) {
        $arusInvestasi[] = $m;
        $totInvestasi += $nilaiKas;
    } elseif (strpos($ket, 'modal') !== false || strpos($ket, 'prive') !== false || strpos($ket, 'pinjaman') !== false || strpos($ket, 'utang bank') !== false) {
        $arusPendanaan[] = $m;
        $totPendanaan += $nilaiKas;
    } else {
        // Default masuk ke operasional (misal penerimaan/pengeluaran umum)
        $arusOperasional[] = $m;
        $totOperasional += $nilaiKas;
    }
}

$kenaikanPenurunanKas = $totOperasional + $totInvestasi + $totPendanaan;
$saldoAkhir = $saldoAwal + $kenaikanPenurunanKas;
?>

<div class="mb-3 text-center">
    <h5 class="font-weight-bold text-gray-800">LAPORAN ARUS KAS (CASH FLOW)</h5>
    <span class="text-muted">Periode Bulan: <?= h($namaBulanTampil) ?></span>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-sm table-hover table-striped" width="100%" cellspacing="0">
        <thead class="thead-light">
            <tr>
                <th>Keterangan / Rincian Transaksi Kas</th>
                <th class="text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <!-- SALDO AWAL -->
            <tr class="font-weight-bold table-secondary">
                <td>Saldo Awal Kas & Bank</td>
                <td class="text-right"><?= number_format($saldoAwal, 0, ',', '.') ?></td>
            </tr>

            <!-- 1. AKTIVITAS OPERASIONAL -->
            <tr class="font-weight-bold table-light text-primary">
                <td colspan="2"><i class="fas fa-briefcase"></i> Arus Kas dari Aktivitas Operasional</td>
            </tr>
            <?php if (empty($arusOperasional)): ?>
                <tr><td class="pl-4 text-muted font-italic">Tidak ada transaksi operasional.</td><td class="text-right">0</td></tr>
            <?php else: ?>
                <?php foreach ($arusOperasional as $op): ?>
                    <?php $valOp = (float)$op['debit'] - (float)$op['kredit']; ?>
                    <tr>
                        <td class="pl-4"><?= h(tanggal_indo($op['tanggal']) . ' - ' . $op['keterangan']) ?></td>
                        <td class="text-right <?= $valOp < 0 ? 'text-danger' : '' ?>"><?= number_format($valOp, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="font-weight-bold">
                <td class="text-right text-muted">Kas Bersih dari Aktivitas Operasional:</td>
                <td class="text-right <?= $totOperasional < 0 ? 'text-danger' : 'text-primary' ?>"><?= number_format($totOperasional, 0, ',', '.') ?></td>
            </tr>

            <!-- 2. AKTIVITAS INVESTASI -->
            <tr class="font-weight-bold table-light text-primary">
                <td colspan="2"><i class="fas fa-chart-line"></i> Arus Kas dari Aktivitas Investasi</td>
            </tr>
            <?php if (empty($arusInvestasi)): ?>
                <tr><td class="pl-4 text-muted font-italic">Tidak ada transaksi investasi.</td><td class="text-right">0</td></tr>
            <?php else: ?>
                <?php foreach ($arusInvestasi as $inv): ?>
                    <?php $valInv = (float)$inv['debit'] - (float)$inv['kredit']; ?>
                    <tr>
                        <td class="pl-4"><?= h(tanggal_indo($inv['tanggal']) . ' - ' . $inv['keterangan']) ?></td>
                        <td class="text-right <?= $valInv < 0 ? 'text-danger' : '' ?>"><?= number_format($valInv, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="font-weight-bold">
                <td class="text-right text-muted">Kas Bersih dari Aktivitas Investasi:</td>
                <td class="text-right <?= $totInvestasi < 0 ? 'text-danger' : 'text-primary' ?>"><?= number_format($totInvestasi, 0, ',', '.') ?></td>
            </tr>

            <!-- 3. AKTIVITAS PENDANAAN -->
            <tr class="font-weight-bold table-light text-primary">
                <td colspan="2"><i class="fas fa-hand-holding-usd"></i> Arus Kas dari Aktivitas Pendanaan</td>
            </tr>
            <?php if (empty($arusPendanaan)): ?>
                <tr><td class="pl-4 text-muted font-italic">Tidak ada transaksi pendanaan.</td><td class="text-right">0</td></tr>
            <?php else: ?>
                <?php foreach ($arusPendanaan as $dan): ?>
                    <?php $valDan = (float)$dan['debit'] - (float)$dan['kredit']; ?>
                    <tr>
                        <td class="pl-4"><?= h(tanggal_indo($dan['tanggal']) . ' - ' . $dan['keterangan']) ?></td>
                        <td class="text-right <?= $valDan < 0 ? 'text-danger' : '' ?>"><?= number_format($valDan, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="font-weight-bold">
                <td class="text-right text-muted">Kas Bersih dari Aktivitas Pendanaan:</td>
                <td class="text-right <?= $totPendanaan < 0 ? 'text-danger' : 'text-primary' ?>"><?= number_format($totPendanaan, 0, ',', '.') ?></td>
            </tr>
        </tbody>
        <tfoot class="font-weight-bold table-light">
            <tr>
                <td class="text-right">KENAIKAN / PENURUNAN KAS BERSIH:</td>
                <td class="text-right <?= $kenaikanPenurunanKas < 0 ? 'text-danger' : 'text-primary' ?>"><?= number_format($kenaikanPenurunanKas, 0, ',', '.') ?></td>
            </tr>
            <tr class="table-active">
                <td class="text-right text-success">SALDO AKHIR KAS & BANK:</td>
                <td class="text-right text-success"><?= number_format($saldoAkhir, 0, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>