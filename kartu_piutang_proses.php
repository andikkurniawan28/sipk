<?php
include 'config.php';

$pelanggan_id = (int)($_GET['pelanggan_id'] ?? 0);
$dari         = $_GET['dari'] ?? date('Y-m-01');
$sampai       = $_GET['sampai'] ?? date('Y-m-t');

if ($pelanggan_id <= 0) {
    echo '<div class="alert alert-warning">Pelanggan harus dipilih secara valid.</div>';
    exit;
}

// 1. Ambil informasi pelanggan
$stmtPelanggan = $conn->prepare("SELECT nama, telepon, alamat FROM pelanggan WHERE id = ?");
$stmtPelanggan->bind_param('i', $pelanggan_id);
$stmtPelanggan->execute();
$pelanggan = $stmtPelanggan->get_result()->fetch_assoc();
$stmtPelanggan->close();

if (!$pelanggan) {
    echo '<div class="alert alert-danger">Pelanggan tidak ditemukan.</div>';
    exit;
}

// 2. Hitung Saldo Awal Piutang sebelum tanggal 'dari'
// Piutang bersaldo normal Debit: Penjualan menambah piutang (Debit), Pembayaran mengurangi piutang (Kredit)
$stmtSaldoAwal = $conn->prepare("
    SELECT 
        COALESCE(SUM(p.debit), 0) - COALESCE(SUM(p.kredit), 0) AS saldo_awal
    FROM (
        SELECT tanggal, grand_total AS debit, 0 AS kredit 
        FROM penjualan 
        WHERE pelanggan_id = ? AND tanggal < ?
        
        UNION ALL
        
        SELECT pp.tanggal, 0 AS debit, pp.total AS kredit 
        FROM pembayaran_penjualan pp
        JOIN penjualan pn ON pn.id = pp.penjualan_id
        WHERE pn.pelanggan_id = ? AND pp.tanggal < ?
    ) p
");
$stmtSaldoAwal->bind_param('isis', $pelanggan_id, $dari, $pelanggan_id, $dari);
$stmtSaldoAwal->execute();
$saldoAwal = (float)$stmtSaldoAwal->get_result()->fetch_assoc()['saldo_awal'];
$stmtSaldoAwal->close();

// 3. Ambil data mutasi piutang dalam rentang tanggal
$stmtMutasi = $conn->prepare("
    SELECT m.* FROM (
        SELECT pn.tanggal, pn.timestamp, pn.id, pn.kode AS referensi, 'Penjualan' AS jenis, 
               pn.grand_total AS debit, 0 AS kredit, u.nama AS nama_admin
        FROM penjualan pn
        JOIN `user` u ON u.id = pn.user_id
        WHERE pn.pelanggan_id = ? AND pn.tanggal BETWEEN ? AND ?

        UNION ALL

        SELECT pp.tanggal, pp.timestamp, pp.id, pp.kode AS referensi, 'Pembayaran Piutang' AS jenis, 
               0 AS debit, pp.total AS kredit, u.nama AS nama_admin
        FROM pembayaran_penjualan pp
        JOIN penjualan pn ON pn.id = pp.penjualan_id
        JOIN `user` u ON u.id = pp.user_id
        WHERE pn.pelanggan_id = ? AND pp.tanggal BETWEEN ? AND ?
    ) m
    ORDER BY m.tanggal ASC, m.timestamp ASC, m.id ASC
");
$stmtMutasi->bind_param('ississ', $pelanggan_id, $dari, $sampai, $pelanggan_id, $dari, $sampai);
$stmtMutasi->execute();
$mutasiList = $stmtMutasi->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMutasi->close();
?>

<div class="mb-3">
    <strong>Pelanggan:</strong> <?= h($pelanggan['nama']) ?><br>
    <strong>Kontak:</strong> <?= h($pelanggan['telepon'] ?? '-') ?><br>
    <strong>Alamat:</strong> <?= h($pelanggan['alamat'] ?? '-') ?><br>
    <strong>Periode:</strong> <?= h(tanggal_indo($dari)) ?> s/d <?= h(tanggal_indo($sampai)) ?>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-sm">
        <thead class="thead-light">
            <tr>
                <th>Timestamp</th>
                <th>Referensi</th>
                <th>Jenis</th>
                <th>Admin</th>
                <th class="text-right">Pertambahan</th>
                <th class="text-right">Pengurangan</th>
                <th class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <!-- Baris Saldo Awal -->
            <tr class="table-secondary font-weight-bold">
                <td colspan="6" class="text-right">SALDO AWAL PIUTANG PER <?= h(tanggal_indo($dari)) ?> :</td>
                <td class="text-right"><?= number_format($saldoAwal, 0, ',', '.') ?></td>
            </tr>

            <?php 
            $saldoBerjalan = $saldoAwal;
            $totalDebit = 0;
            $totalKredit = 0;

            if (empty($mutasiList)): 
            ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">Tidak ada mutasi piutang pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($mutasiList as $row): 
                    $debit = (float)$row['debit'];
                    $kredit = (float)$row['kredit'];
                    
                    // Saldo debit: Bertambah di Debit, Berkurang di Kredit
                    $saldoBerjalan += ($debit - $kredit);
                    
                    $totalDebit += $debit;
                    $totalKredit += $kredit;
                ?>
                    <tr>
                        <td><?= h($row['timestamp']) ?></td>
                        <td><strong><?= h($row['referensi']) ?></strong></td>
                        <td><?= h($row['jenis']) ?></td>
                        <td><?= h($row['nama_admin'] ?? '-') ?></td>
                        <td class="text-right text-success"><?= $debit > 0 ? number_format($debit, 0, ',', '.') : '-' ?></td>
                        <td class="text-right text-danger"><?= $kredit > 0 ? number_format($kredit, 0, ',', '.') : '-' ?></td>
                        <td class="text-right font-weight-bold"><?= number_format($saldoBerjalan, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot class="font-weight-bold table-light">
            <tr>
                <td colspan="4" class="text-right">TOTAL PERIODE INI:</td>
                <td class="text-right text-success"><?= number_format($totalDebit, 0, ',', '.') ?></td>
                <td class="text-right text-danger"><?= number_format($totalKredit, 0, ',', '.') ?></td>
                <td class="text-right"><?= number_format($saldoBerjalan, 0, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>