<?php
include 'config.php';

$pemasok_id = (int)($_GET['pemasok_id'] ?? 0);
$dari       = $_GET['dari'] ?? date('Y-m-01');
$sampai     = $_GET['sampai'] ?? date('Y-m-t');

if ($pemasok_id <= 0) {
    echo '<div class="alert alert-warning">Pemasok harus dipilih secara valid.</div>';
    exit;
}

// 1. Ambil informasi pemasok
$stmtPemasok = $conn->prepare("SELECT kode, nama, telepon, alamat FROM pemasok WHERE id = ?");
$stmtPemasok->bind_param('i', $pemasok_id);
$stmtPemasok->execute();
$pemasok = $stmtPemasok->get_result()->fetch_assoc();
$stmtPemasok->close();

if (!$pemasok) {
    echo '<div class="alert alert-danger">Pemasok tidak ditemukan.</div>';
    exit;
}

// 2. Hitung Saldo Awal Hutang sebelum tanggal 'dari'
// Hutang bersaldo normal Kredit: Pembelian menambah hutang (Kredit), Pembayaran mengurangi hutang (Debit)
$stmtSaldoAwal = $conn->prepare("
    SELECT 
        COALESCE(SUM(h.kredit), 0) - COALESCE(SUM(h.debit), 0) AS saldo_awal
    FROM (
        SELECT tanggal, grand_total AS kredit, 0 AS debit 
        FROM pembelian_langsung 
        WHERE pemasok_id = ? AND tanggal < ?
        
        UNION ALL
        
        SELECT ppbl.tanggal, 0 AS kredit, ppbl.total AS debit 
        FROM pembayaran_pembelian_langsung ppbl
        JOIN penerimaan_pembelian_langsung ppl ON ppl.id = ppbl.penerimaan_pembelian_langsung_id
        JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
        WHERE pl.pemasok_id = ? AND ppbl.tanggal < ?
    ) h
");
$stmtSaldoAwal->bind_param('isis', $pemasok_id, $dari, $pemasok_id, $dari);
$stmtSaldoAwal->execute();
$saldoAwal = (float)$stmtSaldoAwal->get_result()->fetch_assoc()['saldo_awal'];
$stmtSaldoAwal->close();

// 3. Ambil data mutasi hutang dalam rentang tanggal
$stmtMutasi = $conn->prepare("
    SELECT m.* FROM (
        SELECT pl.tanggal, pl.timestamp, pl.id, pl.kode AS referensi, 'Pembelian Langsung' AS jenis, 
               pl.grand_total AS kredit, 0 AS debit, u.nama AS nama_admin
        FROM pembelian_langsung pl
        JOIN `user` u ON u.id = pl.user_id
        WHERE pl.pemasok_id = ? AND pl.tanggal BETWEEN ? AND ?

        UNION ALL

        SELECT ppbl.tanggal, ppbl.timestamp, ppbl.id, ppbl.kode AS referensi, 'Pembayaran Hutang' AS jenis, 
               0 AS kredit, ppbl.total AS debit, u.nama AS nama_admin
        FROM pembayaran_pembelian_langsung ppbl
        JOIN penerimaan_pembelian_langsung ppl ON ppl.id = ppbl.penerimaan_pembelian_langsung_id
        JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
        JOIN `user` u ON u.id = ppbl.user_id
        WHERE pl.pemasok_id = ? AND ppbl.tanggal BETWEEN ? AND ?
    ) m
    ORDER BY m.tanggal ASC, m.timestamp ASC, m.id ASC
");
$stmtMutasi->bind_param('ississ', $pemasok_id, $dari, $sampai, $pemasok_id, $dari, $sampai);
$stmtMutasi->execute();
$mutasiList = $stmtMutasi->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMutasi->close();
?>

<div class="mb-3">
    <strong>Pemasok:</strong> <?= h($pemasok['kode'] . ' - ' . $pemasok['nama']) ?><br>
    <strong>Kontak:</strong> <?= h($pemasok['telepon']) ?><br>
    <strong>Alamat:</strong> <?= h($pemasok['alamat']) ?><br>
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
                <td colspan="6" class="text-right">SALDO AWAL HUTANG PER <?= h(tanggal_indo($dari)) ?> :</td>
                <td class="text-right"><?= number_format($saldoAwal, 0, ',', '.') ?></td>
            </tr>

            <?php 
            $saldoBerjalan = $saldoAwal;
            $totalKredit = 0;
            $totalDebit = 0;

            if (empty($mutasiList)): 
            ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">Tidak ada mutasi hutang pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($mutasiList as $row): 
                    $kredit = (float)$row['kredit'];
                    $debit = (float)$row['debit'];
                    
                    // Saldo kredit: Bertambah di Kredit, Berkurang di Debit
                    $saldoBerjalan += ($kredit - $debit);
                    
                    $totalKredit += $kredit;
                    $totalDebit += $debit;
                ?>
                    <tr>
                        <td><?= h($row['timestamp']) ?></td>
                        <td><strong><?= h($row['referensi']) ?></strong></td>
                        <td><?= h($row['jenis']) ?></td>
                        <td><?= h($row['nama_admin'] ?? '-') ?></td>
                        <td class="text-right text-danger"><?= $kredit > 0 ? number_format($kredit, 0, ',', '.') : '-' ?></td>
                        <td class="text-right text-success"><?= $debit > 0 ? number_format($debit, 0, ',', '.') : '-' ?></td>
                        <td class="text-right font-weight-bold"><?= number_format($saldoBerjalan, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot class="font-weight-bold table-light">
            <tr>
                <td colspan="4" class="text-right">TOTAL PERIODE INI:</td>
                <td class="text-right text-danger"><?= number_format($totalKredit, 0, ',', '.') ?></td>
                <td class="text-right text-success"><?= number_format($totalDebit, 0, ',', '.') ?></td>
                <td class="text-right"><?= number_format($saldoBerjalan, 0, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>