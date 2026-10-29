<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT pl.*, p.nama AS pemasok_nama, p.alamat AS pemasok_alamat, p.telepon AS pemasok_telepon, u.nama AS dibuat_oleh
    FROM pembelian_langsung pl
    JOIN pemasok p ON p.id = pl.pemasok_id
    JOIN `user` u ON u.id = pl.user_id
    WHERE pl.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data pembelian tidak ada.'];
    header('Location: pembelian_langsung_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, d.harga, d.total, pr.nama, pr.satuan, pr.kode
    FROM pembelian_langsung_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.pembelian_langsung_id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Pembelian Langsung " . $data['kode'];
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h1 class="h3 mb-0 text-gray-800">Detail Pembelian Langsung</h1>
    <div>
        <a href="pembelian_langsung_index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">BUKTI PEMBELIAN LANGSUNG</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($data['kode']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($data['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <div class="row mb-4">
            <div class="col-sm-6">
                <div class="invoice-label">Pemasok</div>
                <div class="font-weight-bold text-gray-800"><?= h($data['pemasok_nama']) ?></div>
                <?php if ($data['pemasok_alamat']): ?><div class="small text-gray-700"><?= nl2br(h($data['pemasok_alamat'])) ?></div><?php endif; ?>
                <?php if ($data['pemasok_telepon']): ?><div class="small text-gray-700">Telp. <?= h($data['pemasok_telepon']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead class="thead-light">
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Produk</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail as $i => $d): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($d['kode']) ?> - <?= h($d['nama']) ?></td>
                            <td class="text-right"><?= rp($d['qty']) . ' ' . h($d['satuan']) ?></td>
                            <td class="text-right"><?= rp($d['harga']) ?></td>
                            <td class="text-right"><?= rp($d['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row">
            <div class="col-md-6 offset-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td>Subtotal</td><td class="text-right"><?= rp($data['subtotal']) ?></td></tr>
                    <tr><td>Pajak</td><td class="text-right"><?= rp($data['pajak']) ?></td></tr>
                    <tr><td>Ongkir</td><td class="text-right"><?= rp($data['ongkir']) ?></td></tr>
                    <tr><td>Biaya</td><td class="text-right"><?= rp($data['biaya']) ?></td></tr>
                    <tr><td>Diskon</td><td class="text-right"><?= rp($data['diskon']) ?></td></tr>
                    <tr class="border-top"><td class="font-weight-bold h5 pt-3">Total</td><td class="text-right font-weight-bold h5 pt-3 text-primary"><?= rp($data['grand_total']) ?></td></tr>
                </table>
            </div>
        </div>

        <hr class="mt-4">
        <div class="d-flex justify-content-between small text-gray-600">
            <span>Admin: <?= h($data['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>