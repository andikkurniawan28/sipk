<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.id, p.tanggal, p.kode, p.panen_id,
           g.nama AS gudang_nama, g.alamat AS gudang_alamat,
           pn.kode AS kode_panen, pt.nama AS petani_nama, pt.alamat AS petani_alamat, pt.telepon AS petani_telepon,
           u.nama AS dibuat_oleh
    FROM penerimaan p
    JOIN gudang g ON g.id = p.gudang_id
    JOIN `user` u ON u.id = p.user_id
    LEFT JOIN panen pn ON pn.id = p.panen_id
    LEFT JOIN petani pt ON pt.id = pn.petani_id
    WHERE p.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$penerimaan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$penerimaan) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data penerimaan tidak ada.'];
    header('Location: penerimaan_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, pr.nama, pr.satuan
    FROM penerimaan_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.penerimaan_id = ?
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

$page_title = "Penerimaan " . $penerimaan['kode'];
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
    <h1 class="h3 mb-0 text-gray-800">Detail Penerimaan</h1>
    <div>
        <a href="penerimaan_index.php" class="btn btn-secondary btn-sm">
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
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">BUKTI PENERIMAAN</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($penerimaan['kode']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($penerimaan['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <!-- Faktur referensi dan gudang -->
        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <div class="invoice-label">Faktur Referensi</div>
                <?php if (!empty($penerimaan['panen_id'])): ?>
                    <div class="font-weight-bold text-gray-800">Panen (<?= h($penerimaan['kode_panen']) ?>)</div>
                    <div class="small text-gray-700">Petani: <?= h($penerimaan['petani_nama']) ?></div>
                    <?php if (!empty($penerimaan['petani_alamat'])): ?>
                        <div class="small text-gray-700"><?= nl2br(h($penerimaan['petani_alamat'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($penerimaan['petani_telepon'])): ?>
                        <div class="small text-gray-700">Telp. <?= h($penerimaan['petani_telepon']) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="font-weight-bold text-gray-800">-</div>
                <?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-right">
                <div class="invoice-label">Diterima di Gudang</div>
                <div class="font-weight-bold text-gray-800"><?= h($penerimaan['gudang_nama']) ?></div>
                <?php if (!empty($penerimaan['gudang_alamat'])): ?>
                    <div class="small text-gray-700"><?= nl2br(h($penerimaan['gudang_alamat'])) ?></div>
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
            <span>Dibuat oleh: <?= h($penerimaan['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>

    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>