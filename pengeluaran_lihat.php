<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.id, p.tanggal, p.kode, p.preparasi_id, p.penjualan_id,
           g.nama AS gudang_nama, g.alamat AS gudang_alamat,
           pn.kode AS kode_preparasi, 
           pj.kode AS kode_penjualan, pl.nama AS nama_pelanggan,
           u.nama AS dibuat_oleh
    FROM pengeluaran p
    JOIN gudang g ON g.id = p.gudang_id
    JOIN `user` u ON u.id = p.user_id
    LEFT JOIN preparasi pn ON pn.id = p.preparasi_id
    LEFT JOIN penjualan pj ON pj.id = p.penjualan_id
    LEFT JOIN pelanggan pl ON pl.id = pj.pelanggan_id
    WHERE p.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$pengeluaran = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$pengeluaran) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data pengeluaran tidak ada.'];
    header('Location: pengeluaran_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, pr.nama, pr.satuan
    FROM pengeluaran_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.pengeluaran_id = ?
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

function tanggal_indo(string $tanggal): string
{
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = strtotime($tanggal);
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

$page_title = "Pengeluaran " . $pengeluaran['kode'];
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
    <h1 class="h3 mb-0 text-gray-800">Detail Pengeluaran</h1>
    <div>
        <a href="pengeluaran_index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
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
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">BUKTI PENGELUARAN</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($pengeluaran['kode']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($pengeluaran['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <!-- Faktur referensi dan gudang -->
        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <div class="invoice-label">Faktur Referensi</div>
                <?php if (!empty($pengeluaran['preparasi_id'])): ?>
                    <div class="font-weight-bold text-gray-800">Preparasi (<?= h($pengeluaran['kode_preparasi']) ?>)</div>
                <?php elseif (!empty($pengeluaran['penjualan_id'])): ?>
                    <div class="font-weight-bold text-gray-800">Penjualan (<?= h($pengeluaran['kode_penjualan']) ?> - <?= h($pengeluaran['nama_pelanggan']) ?>)</div>
                <?php else: ?>
                    <div class="font-weight-bold text-gray-800">-</div>
                <?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-right">
                <div class="invoice-label">Ambil dari Gudang</div>
                <div class="font-weight-bold text-gray-800"><?= h($pengeluaran['gudang_nama']) ?></div>
                <?php if (!empty($pengeluaran['gudang_alamat'])): ?>
                    <div class="small text-gray-700"><?= nl2br(h($pengeluaran['gudang_alamat'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rincian item barang -->
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
                            <td class="text-right"><?= number_format((float)$d['qty'], 2, ',', '.') ?> <?= h($d['satuan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr class="mt-4">

        <div class="d-flex justify-content-between small text-gray-600">
            <span>Dibuat oleh: <?= h($pengeluaran['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>

    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>