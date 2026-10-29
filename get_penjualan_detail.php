<?php
include 'config.php';
$penjualan_id = (int)($_GET['penjualan_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT d.produk_id, d.qty, d.harga, d.total, pr.kode, pr.nama, pr.satuan
    FROM penjualan_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.penjualan_id = ?
");
$stmt->bind_param('i', $penjualan_id);
$stmt->execute();
$details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($details)) {
    echo '<div class="alert alert-warning">Tidak ada produk pada faktur ini.</div>';
    exit;
}
?>
<table class="table table-bordered table-sm">
    <thead class="thead-light">
        <tr>
            <th>No</th>
            <th>Produk</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Harga</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($details as $i => $d): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td>
                    <?= h($d['kode'] . ' - ' . $d['nama']) ?>
                    <input type="hidden" name="produk_id[]" value="<?= (int)$d['produk_id'] ?>">
                    <input type="hidden" name="harga[]" value="<?= (float)$d['harga'] ?>">
                </td>
                <td class="text-right">
                    <input type="number" name="qty[]" class="form-control form-control-sm text-right" value="<?= (float)$d['qty'] ?>" readonly style="max-width: 120px; margin-left: auto;">
                    <small class="text-muted"><?= h($d['satuan']) ?></small>
                </td>
                <td class="text-right"><?= rp($d['harga']) ?></td>
                <td class="text-right"><?= rp($d['total']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>