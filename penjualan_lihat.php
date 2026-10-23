<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.id, p.tanggal, p.kode, p.subtotal, p.pajak, p.ongkir, p.biaya, p.rafaksi, p.diskon, p.grand_total,
           pt.nama AS pelanggan_nama, pt.alamat AS pelanggan_alamat, pt.telepon AS pelanggan_telepon,
           g.nama AS gudang_nama, g.alamat AS gudang_alamat,
           u.nama AS dibuat_oleh
    FROM penjualan p
    JOIN pelanggan pt ON pt.id = p.pelanggan_id
    JOIN gudang g ON g.id = p.gudang_id
    JOIN `user` u ON u.id = p.user_id
    WHERE p.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$penjualan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$penjualan) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data penjualan tidak ada.'];
    header('Location: penjualan_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, d.harga, d.total, pr.nama, pr.satuan
    FROM penjualan_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.penjualan_id = ?
    ORDER BY d.id ASC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h($teks): string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function rp($angka): string
{
    return 'Rp ' . number_format((float)$angka, 2, ',', '.');
}

function tanggal_indo(string $tanggal): string
{
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = strtotime($tanggal);
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

$page_title = "Penjualan " . $penjualan['kode'];
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

<!-- Tombol aksi (tidak ikut tercetak) -->
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h1 class="h3 mb-0 text-gray-800">Detail Penjualan</h1>
    <div>
        <a href="penjualan_index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <a href="penjualan_edit.php?id=<?= (int)$penjualan['id'] ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-edit"></i> Edit
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">

        <!-- Kepala invoice -->
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">BUKTI PENJUALAN</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($penjualan['kode']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($penjualan['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <!-- Pelanggan dan gudang -->
        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <div class="invoice-label">Pelanggan</div>
                <div class="font-weight-bold text-gray-800"><?= h($penjualan['pelanggan_nama']) ?></div>
                <?php if ($penjualan['pelanggan_alamat'] !== null && $penjualan['pelanggan_alamat'] !== ''): ?>
                    <div class="small text-gray-700"><?= nl2br(h($penjualan['pelanggan_alamat'])) ?></div>
                <?php endif; ?>
                <?php if ($penjualan['pelanggan_telepon'] !== null && $penjualan['pelanggan_telepon'] !== ''): ?>
                    <div class="small text-gray-700">Telp. <?= h($penjualan['pelanggan_telepon']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-right">
                <div class="invoice-label">Ambil dari Gudang</div>
                <div class="font-weight-bold text-gray-800"><?= h($penjualan['gudang_nama']) ?></div>
                <?php if ($penjualan['gudang_alamat'] !== null && $penjualan['gudang_alamat'] !== ''): ?>
                    <div class="small text-gray-700"><?= nl2br(h($penjualan['gudang_alamat'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rincian item -->
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
                            <td><?= h($d['nama']) ?></td>
                            <td class="text-right"><?= number_format((float)$d['qty'], 2, ',', '.') ?> <?= h($d['satuan']) ?></td>
                            <td class="text-right"><?= rp($d['harga']) ?></td>
                            <td class="text-right"><?= rp($d['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Ringkasan biaya -->
        <div class="row">
            <div class="col-md-6 offset-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td>Subtotal</td>
                        <td class="text-right"><?= rp($penjualan['subtotal']) ?></td>
                    </tr>
                    <tr>
                        <td>Pajak</td>
                        <td class="text-right"><?= rp($penjualan['pajak']) ?></td>
                    </tr>
                    <tr>
                        <td>Ongkir</td>
                        <td class="text-right"><?= rp($penjualan['ongkir']) ?></td>
                    </tr>
                    <tr>
                        <td>Biaya</td>
                        <td class="text-right"><?= rp($penjualan['biaya']) ?></td>
                    </tr>
                    <tr>
                        <td>Rafaksi</td>
                        <td class="text-right">- <?= rp($penjualan['rafaksi']) ?></td>
                    </tr>
                    <tr>
                        <td>Diskon</td>
                        <td class="text-right">- <?= rp($penjualan['diskon']) ?></td>
                    </tr>
                    <tr class="border-top">
                        <td class="font-weight-bold h5 pt-3">Grand Total</td>
                        <td class="text-right font-weight-bold h5 pt-3 text-primary"><?= rp($penjualan['grand_total']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <hr class="mt-4">

        <div class="d-flex justify-content-between small text-gray-600">
            <span>Dibuat oleh: <?= h($penjualan['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>

    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>