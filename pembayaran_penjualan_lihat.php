<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT pp.*, pn.kode AS kode_penjualan, pn.grand_total, 
           plg.nama AS pelanggan_nama, plg.alamat AS pelanggan_alamat, plg.telepon AS pelanggan_telepon, 
           a.nama AS akun_nama, u.nama AS dibuat_oleh
    FROM pembayaran_penjualan pp
    JOIN penjualan pn ON pn.id = pp.penjualan_id
    JOIN pelanggan plg ON plg.id = pn.pelanggan_id
    JOIN akun a ON a.id = pp.akun_id
    JOIN `user` u ON u.id = pp.user_id
    WHERE pp.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data pembayaran tidak ada.'];
    header('Location: pembayaran_penjualan_index.php');
    exit;
}

$page_title = "pembayaran_penjualan";
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h1 class="h3 mb-0 text-gray-800">Detail Pembayaran Penjualan</h1>
    <div>
        <a href="pembayaran_penjualan_index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary mb-1">BUKTI PENERIMAAN PEMBAYARAN</h2>
                <div class="text-gray-800 font-weight-bold">No. Bukti: <?= h($data['kode']) ?></div>
                <div class="small text-muted">Ref. Faktur: <?= h($data['kode_penjualan']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div>Tanggal Bayar</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($data['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <div class="row mb-4">
            <div class="col-sm-6">
                <div class="font-weight-bold text-gray-800">Diterima Dari:</div>
                <div class="font-weight-bold"><?= h($data['pelanggan_nama']) ?></div>
                <?php if ($data['pelanggan_alamat']): ?><div class="small text-gray-700"><?= nl2br(h($data['pelanggan_alamat'])) ?></div><?php endif; ?>
                <?php if ($data['pelanggan_telepon']): ?><div class="small text-gray-700">Telp. <?= h($data['pelanggan_telepon']) ?></div><?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-right">
                <div class="font-weight-bold text-gray-800">Pembayaran Melalui:</div>
                <div class="text-gray-700"><?= h($data['akun_nama']) ?></div>
            </div>
        </div>

        <div class="table-responsive my-4">
            <table class="table table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Keterangan</th>
                        <th class="text-right" style="width:250px">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Pembayaran piutang untuk Faktur Penjualan <strong><?= h($data['kode_penjualan']) ?></strong></td>
                        <td class="text-right font-weight-bold text-primary h5"><?= rp($data['total']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <hr class="mt-5">
        <div class="d-flex justify-content-between small text-gray-600 mt-4">
            <span>Admin: <?= h($data['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>