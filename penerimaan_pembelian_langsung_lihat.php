<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT ppl.*, pl.kode AS kode_pembelian, g.nama AS nama_gudang, g.alamat AS alamat_gudang, 
           p.nama AS pemasok_nama, p.alamat AS pemasok_alamat, p.telepon AS pemasok_telepon, u.nama AS dibuat_oleh
    FROM penerimaan_pembelian_langsung ppl
    JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
    JOIN pemasok p ON p.id = pl.pemasok_id
    JOIN gudang g ON g.id = ppl.gudang_id
    JOIN `user` u ON u.id = ppl.user_id
    WHERE ppl.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data penerimaan tidak ada.'];
    header('Location: penerimaan_pembelian_langsung_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, d.harga, d.total, pr.nama, pr.satuan
    FROM penerimaan_pembelian_langsung_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.penerimaan_pembelian_langsung_id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Penerimaan " . $data['kode'];
include 'header.php';
?>

<style>
    .invoice-judul { letter-spacing: 2px; }
    .invoice-label { font-size: .75rem; text-transform: uppercase; letter-spacing: 1px; color: #858796; margin-bottom: .25rem; }
    @media print {
        #accordionSidebar, .topbar, .sticky-footer, .scroll-to-top, .no-print { display: none !important; }
        body, #content-wrapper { background: #fff !important; }
        .card { box-shadow: none !important; border: 0 !important; }
        .container-fluid { padding: 0 !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h1 class="h3 mb-0 text-gray-800">Detail Penerimaan Pembelian Langsung</h1>
    <div>
        <a href="penerimaan_pembelian_langsung_index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">BUKTI PENERIMAAN</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($data['kode']) ?></div>
                <div class="small text-muted">Ref. Pembelian: <?= h($data['kode_pembelian']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($data['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <div class="invoice-label">Pemasok</div>
                <div class="font-weight-bold text-gray-800"><?= h($data['pemasok_nama']) ?></div>
                <?php if ($data['pemasok_alamat']): ?><div class="small text-gray-700"><?= nl2br(h($data['pemasok_alamat'])) ?></div><?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-right">
                <div class="invoice-label">Diterima di Gudang</div>
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
                        <th class="text-right">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail as $i => $d): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($d['nama']) ?></td>
                            <td class="text-right"><?= rp($d['qty']) . ' ' . h($d['satuan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr class="mt-4">
        <div class="d-flex justify-content-between small text-gray-600">
            <span>Dibuat oleh: <?= h($data['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>