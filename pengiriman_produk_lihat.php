<?php
include 'config.php';
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT pp.*, pn.kode AS kode_penjualan, pn.tanggal AS tanggal_penjualan, 
           plg.nama AS pelanggan_nama, plg.alamat AS pelanggan_alamat, plg.telepon AS pelanggan_telepon, 
           g.nama AS gudang_nama, u.nama AS dibuat_oleh
    FROM pengiriman_produk pp
    JOIN penjualan pn ON pn.id = pp.penjualan_id
    JOIN pelanggan plg ON plg.id = pn.pelanggan_id
    JOIN gudang g ON g.id = pp.gudang_id
    JOIN `user` u ON u.id = pp.user_id
    WHERE pp.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data pengiriman tidak ada.'];
    header('Location: pengiriman_produk_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, d.harga, d.total, pr.nama, pr.satuan, pr.kode
    FROM pengiriman_produk_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.pengiriman_produk_id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Surat Jalan " . $data['kode'];
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h1 class="h3 mb-0 text-gray-800">Detail Pengiriman Produk</h1>
    <div>
        <a href="pengiriman_produk_index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary mb-1">DELIVERY ORDER</h2>
                <div class="text-gray-800 font-weight-bold">No. DO: <?= h($data['kode']) ?></div>
                <div class="small text-muted">Ref. Faktur: <?= h($data['kode_penjualan']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div>Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($data['tanggal'])) ?></div>
                <div class="small text-muted">Gudang : <?= h($data['gudang_nama']) ?></div>
            </div>
        </div>

        <hr>

        <div class="row mb-4">
            <div class="col-sm-6">
                <div class="font-weight-bold text-gray-800">Kepada Yth:</div>
                <div class="font-weight-bold"><?= h($data['pelanggan_nama']) ?></div>
                <?php if ($data['pelanggan_alamat']): ?><div class="small text-gray-700"><?= nl2br(h($data['pelanggan_alamat'])) ?></div><?php endif; ?>
                <?php if ($data['pelanggan_telepon']): ?><div class="small text-gray-700">Telp. <?= h($data['pelanggan_telepon']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Produk</th>
                        <th class="text-right">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail as $i => $d): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($d['kode'] . ' - ' . $d['nama']) ?></td>
                            <td class="text-right"><?= rp($d['qty']) . ' ' . h($d['satuan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr class="mt-5">
        <div class="row text-center small text-gray-600 mt-4">
            <div class="col-4">
                <p>Hormat Kami,</p>
                <br><br><br>
                <p>( Bagian Gudang )</p>
            </div>
            <div class="col-4">
                <p>Disetujui Oleh,</p>
                <br><br><br>
                <p>( Driver / Ekspedisi )</p>
            </div>
            <div class="col-4">
                <p>Penerima,</p>
                <br><br><br>
                <p>( <?= h($data['pelanggan_nama']) ?> )</p>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>