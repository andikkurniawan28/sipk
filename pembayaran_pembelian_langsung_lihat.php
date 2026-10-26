<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT ppbl.*, pl.kode AS kode_pembelian, pl.grand_total, 
           p.nama AS pemasok_nama, p.alamat AS pemasok_alamat, p.telepon AS pemasok_telepon, 
           a.nama AS nama_akun, u.nama AS dibuat_oleh
    FROM pembayaran_pembelian_langsung ppbl
    JOIN pembelian_langsung pl ON pl.id = ppbl.pembelian_langsung_id
    JOIN pemasok p ON p.id = pl.pemasok_id
    JOIN akun a ON a.id = ppbl.akun_id
    JOIN `user` u ON u.id = ppbl.user_id
    WHERE ppbl.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data pembayaran tidak ada.'];
    header('Location: pembayaran_pembelian_langsung_index.php');
    exit;
}

$page_title = "Bukti Pembayaran " . $data['kode'];
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
    <h1 class="h3 mb-0 text-gray-800">Detail Pembayaran Pembelian Langsung</h1>
    <div>
        <a href="pembayaran_pembelian_langsung_index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">BUKTI PEMBAYARAN</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($data['kode']) ?></div>
                <div class="small text-muted">Ref. Pembelian: <?= h($data['kode_pembelian']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal Pembayaran</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($data['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <div class="invoice-label">Kepada Pemasok</div>
                <div class="font-weight-bold text-gray-800"><?= h($data['pemasok_nama']) ?></div>
                <?php if ($data['pemasok_alamat']): ?><div class="small text-gray-700"><?= nl2br(h($data['pemasok_alamat'])) ?></div><?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-right">
                <div class="invoice-label">Dibayar Melalui Akun</div>
                <div class="font-weight-bold text-gray-800"><?= h($data['nama_akun']) ?></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead class="thead-light">
                    <tr>
                        <th>Keterangan Referensi</th>
                        <th class="text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Pembayaran atas Pembelian Langsung <strong><?= h($data['kode_pembelian']) ?></strong></td>
                        <td class="text-right"><?= rp($data['total']) ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-top font-weight-bold">
                        <td class="text-right pt-3 h6">TOTAL PEMBAYARAN:</td>
                        <td class="text-right pt-3 h6 text-primary"><?= rp($data['total']) ?></td>
                    </tr>
                </tfoot>
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