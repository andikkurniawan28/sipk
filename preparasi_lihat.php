<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.*, u.nama AS dibuat_oleh
    FROM preparasi p
    JOIN `user` u ON u.id = p.user_id
    WHERE p.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$preparasi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$preparasi) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data preparasi tidak ada.'];
    header('Location: preparasi_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, pr.nama, pr.satuan
    FROM preparasi_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.preparasi_id = ?
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

$page_title = "Preparasi " . $preparasi['kode'];
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
    <h1 class="h3 mb-0 text-gray-800">Detail Preparasi</h1>
    <div>
        <a href="preparasi_index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">PERMINTAAN PREPARASI</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($preparasi['kode']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($preparasi['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <?php if (!empty($preparasi['keterangan'])): ?>
        <div class="mb-4">
            <div class="invoice-label">Keterangan</div>
            <div class="text-gray-800"><?= nl2br(h($preparasi['keterangan'])) ?></div>
        </div>
        <?php endif; ?>

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
            <span>Dibuat oleh: <?= h($preparasi['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>