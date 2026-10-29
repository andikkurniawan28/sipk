<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT pb.*, g.nama AS nama_gudang, g.alamat AS alamat_gudang, u.nama AS dibuat_oleh
    FROM pengambilan_bahan pb
    JOIN gudang g ON g.id = pb.gudang_id
    JOIN `user` u ON u.id = pb.user_id
    WHERE pb.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data pengambilan bahan tidak ada.'];
    header('Location: pengambilan_bahan_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, d.harga, d.total, pr.nama, pr.satuan, pr.kode
    FROM pengambilan_bahan_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.pengambilan_bahan_id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Pengambilan Bahan " . $data['kode'];
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h1 class="h3 mb-0 text-gray-800">Detail Pengambilan Bahan</h1>
    <div>
        <a href="pengambilan_bahan_index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">PENGAMBILAN BAHAN</h2>
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
                <div class="invoice-label">Gudang Asal</div>
                <div class="font-weight-bold text-gray-800"><?= h($data['nama_gudang']) ?></div>
                <?php if ($data['alamat_gudang']): ?><div class="small text-gray-700"><?= nl2br(h($data['alamat_gudang'])) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead class="thead-light">
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Produk</th>
                        <th class="text-right" style="width:150px">Qty</th>
                        <th class="text-right" style="width:180px">Harga</th>
                        <th class="text-right" style="width:180px">Total</th>
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
                <tfoot>
                    <tr class="border-top font-weight-bold">
                        <td colspan="4" class="text-right">TOTAL:</td>
                        <td class="text-right text-primary"><?= rp($data['total']) ?></td>
                    </tr>
                </tfoot>
            </table>
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