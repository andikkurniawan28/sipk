<?php
include 'config.php';

$produk_id = (int)($_GET['produk_id'] ?? 0);
$gudang_id = (int)($_GET['gudang_id'] ?? 0);
$dari      = $_GET['dari'] ?? date('Y-m-01');
$sampai    = $_GET['sampai'] ?? date('Y-m-t');

if ($produk_id <= 0) {
    echo '<div class="alert alert-warning">Produk tidak valid.</div>';
    exit;
}

// 1. Ambil informasi produk
$stmtProd = $conn->prepare("SELECT nama, satuan FROM produk WHERE id = ?");
$stmtProd->bind_param('i', $produk_id);
$stmtProd->execute();
$produk = $stmtProd->get_result()->fetch_assoc();
$stmtProd->close();

if (!$produk) {
    echo '<div class="alert alert-danger">Produk tidak ditemukan.</div>';
    exit;
}

// 2. Ambil informasi nama gudang jika gudang spesifik dipilih
$namaGudangHeader = "Semua Gudang";
if ($gudang_id > 0) {
    $stmtGudang = $conn->prepare("SELECT nama FROM gudang WHERE id = ?");
    $stmtGudang->bind_param('i', $gudang_id);
    $stmtGudang->execute();
    $resGudang = $stmtGudang->get_result()->fetch_assoc();
    $stmtGudang->close();
    if ($resGudang) {
        $namaGudangHeader = $resGudang['nama'];
    }
}

// 3. Hitung Saldo Awal (kumulatif sebelum tanggal 'dari') dengan dukungan filter gudang opsional
if ($gudang_id > 0) {
    $stmtSaldoAwal = $conn->prepare("
        SELECT COALESCE(SUM(masuk), 0) - COALESCE(SUM(keluar), 0) AS saldo_awal
        FROM mutasi_stok 
        WHERE produk_id = ? AND gudang_id = ? AND tanggal < ?
    ");
    $stmtSaldoAwal->bind_param('iis', $produk_id, $gudang_id, $dari);
} else {
    $stmtSaldoAwal = $conn->prepare("
        SELECT COALESCE(SUM(masuk), 0) - COALESCE(SUM(keluar), 0) AS saldo_awal
        FROM mutasi_stok 
        WHERE produk_id = ? AND tanggal < ?
    ");
    $stmtSaldoAwal->bind_param('is', $produk_id, $dari);
}

$stmtSaldoAwal->execute();
$saldoAwal = (float)$stmtSaldoAwal->get_result()->fetch_assoc()['saldo_awal'];
$stmtSaldoAwal->close();

// 4. Ambil data mutasi dalam rentang tanggal dengan dukungan filter gudang opsional
if ($gudang_id > 0) {
    $stmtMutasi = $conn->prepare("
        SELECT m.*, u.nama AS nama_user
        FROM mutasi_stok m
        JOIN `user` u ON u.id = m.user_id
        WHERE m.produk_id = ? AND m.gudang_id = ? AND m.tanggal BETWEEN ? AND ?
        ORDER BY m.tanggal ASC, m.created_at ASC, m.id ASC
    ");
    $stmtMutasi->bind_param('iiss', $produk_id, $gudang_id, $dari, $sampai);
} else {
    $stmtMutasi = $conn->prepare("
        SELECT m.*, g.nama AS nama_gudang, u.nama AS nama_user
        FROM mutasi_stok m
        JOIN gudang g ON g.id = m.gudang_id
        JOIN `user` u ON u.id = m.user_id
        WHERE m.produk_id = ? AND m.tanggal BETWEEN ? AND ?
        ORDER BY m.tanggal ASC, m.created_at ASC, m.id ASC
    ");
    $stmtMutasi->bind_param('iss', $produk_id, $dari, $sampai);
}

$stmtMutasi->execute();
$mutasiList = $stmtMutasi->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMutasi->close();
?>

<div class="mb-3">
    <strong>Produk:</strong> <?= htmlspecialchars($produk['nama'] . ' (' . $produk['satuan'] . ')') ?><br>
    <strong>Gudang:</strong> <?= htmlspecialchars($namaGudangHeader) ?><br>
    <strong>Periode:</strong> <?= date('d-m-Y', strtotime($dari)) ?> s/d <?= date('d-m-Y', strtotime($sampai)) ?>
</div>

<table class="table table-bordered table-sm">
    <thead class="thead-light">
        <tr>
            <th style="width:110px">Tanggal</th>
            <th>Keterangan</th>
            <th class="text-right" style="width:110px">Masuk</th>
            <th class="text-right" style="width:110px">Keluar</th>
            <th class="text-right" style="width:130px">Saldo</th>
        </tr>
    </thead>
    <tbody>
        <!-- Baris Saldo Awal -->
        <tr class="table-secondary font-weight-bold">
            <td colspan="4" class="text-right">SALDO AWAL PER <?= date('d-m-Y', strtotime($dari)) ?> :</td>
            <td class="text-right"><?= number_format($saldoAwal, 2, ',', '.') ?></td>
        </tr>

        <?php 
        $saldoBerjalan = $saldoAwal;
        $totalMasuk = 0;
        $totalKeluar = 0;

        if (empty($mutasiList)): 
        ?>
            <tr>
                <td colspan="5" class="text-center text-muted">Tidak ada mutasi stok pada periode ini.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($mutasiList as $row): 
                $masuk = (float)$row['masuk'];
                $keluar = (float)$row['keluar'];
                $saldoBerjalan += ($masuk - $keluar);
                
                $totalMasuk += $masuk;
                $totalKeluar += $keluar;
            ?>
                <tr>
                    <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                    <td class="text-right text-success"><?= $masuk > 0 ? number_format($masuk, 2, ',', '.') : '-' ?></td>
                    <td class="text-right text-danger"><?= $keluar > 0 ? number_format($keluar, 2, ',', '.') : '-' ?></td>
                    <td class="text-right font-weight-bold"><?= number_format($saldoBerjalan, 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <tfoot class="font-weight-bold table-light">
        <tr>
            <td colspan="2" class="text-right">TOTAL PERIODE INI:</td>
            <td class="text-right text-success"><?= number_format($totalMasuk, 2, ',', '.') ?></td>
            <td class="text-right text-danger"><?= number_format($totalKeluar, 2, ',', '.') ?></td>
            <td class="text-right"><?= number_format($saldoBerjalan, 2, ',', '.') ?></td>
        </tr>
    </tfoot>
</table>