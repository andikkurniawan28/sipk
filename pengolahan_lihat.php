<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.id, p.tanggal, p.kode, p.keterangan, u.nama AS dibuat_oleh
    FROM pengolahan p
    JOIN `user` u ON u.id = p.user_id
    WHERE p.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$pengolahan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$pengolahan) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data pengolahan tidak ada.'];
    header('Location: pengolahan_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, d.harga, d.total, pr.nama, pr.satuan, g.nama AS gudang
    FROM input_pengolahan d
    JOIN produk pr ON pr.id = d.produk_id
    JOIN gudang g ON g.id = d.gudang_id
    WHERE d.pengolahan_id = ?
    ORDER BY d.id ASC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$input = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT d.qty, d.harga, d.total, pr.nama, pr.satuan, g.nama AS gudang
    FROM output_pengolahan d
    JOIN produk pr ON pr.id = d.produk_id
    JOIN gudang g ON g.id = d.gudang_id
    WHERE d.pengolahan_id = ?
    ORDER BY d.id ASC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$output = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Total dihitung dari baris detail (tidak disimpan di header)
$totalInput = 0.0;
foreach ($input as $d) {
    $totalInput += (float)$d['total'];
}
$totalOutput = 0.0;
foreach ($output as $d) {
    $totalOutput += (float)$d['total'];
}
$selisih = $totalOutput - $totalInput;

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

/** Tabel rincian item (dipakai untuk input dan output). */
function tabel_item(array $baris): void
{
    ?>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead class="thead-light">
                <tr>
                    <th style="width:50px">No</th>
                    <th>Produk</th>
                    <th>Gudang</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($baris as $i => $d): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= h($d['nama']) ?></td>
                        <td><?= h($d['gudang']) ?></td>
                        <td class="text-right"><?= number_format((float)$d['qty'], 2, ',', '.') ?> <?= h($d['satuan']) ?></td>
                        <td class="text-right"><?= rp($d['harga']) ?></td>
                        <td class="text-right"><?= rp($d['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

$page_title = "Pengolahan " . $pengolahan['kode'];
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
    <h1 class="h3 mb-0 text-gray-800">Detail Pengolahan</h1>
    <div>
        <a href="pengolahan_index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <a href="pengolahan_edit.php?id=<?= (int)$pengolahan['id'] ?>" class="btn btn-warning btn-sm">
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
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">BUKTI PENGOLAHAN</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($pengolahan['kode']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($pengolahan['tanggal'])) ?></div>
            </div>
        </div>

        <?php if ($pengolahan['keterangan'] !== null && $pengolahan['keterangan'] !== ''): ?>
            <div class="mb-4">
                <div class="invoice-label">Keterangan</div>
                <div class="text-gray-800"><?= nl2br(h($pengolahan['keterangan'])) ?></div>
            </div>
        <?php endif; ?>

        <hr>

        <!-- Input -->
        <div class="invoice-label mt-4">Input (Bahan yang Dipakai)</div>
        <?php tabel_item($input); ?>

        <!-- Output -->
        <div class="invoice-label mt-4">Output (Hasil Pengolahan)</div>
        <?php tabel_item($output); ?>

        <!-- Ringkasan nilai -->
        <div class="row">
            <div class="col-md-6 offset-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td>Total Input (Persediaan Keluar)</td>
                        <td class="text-right"><?= rp($totalInput) ?></td>
                    </tr>
                    <tr>
                        <td>Total Output (Persediaan Masuk)</td>
                        <td class="text-right"><?= rp($totalOutput) ?></td>
                    </tr>
                    <tr class="border-top">
                        <td class="font-weight-bold h5 pt-3">Selisih (Output &minus; Input)</td>
                        <td class="text-right font-weight-bold h5 pt-3 <?= $selisih > 0 ? 'text-success' : ($selisih < 0 ? 'text-danger' : 'text-gray-800') ?>">
                            <?= $selisih < 0 ? '- ' : '' ?><?= rp(abs($selisih)) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <hr class="mt-4">

        <div class="d-flex justify-content-between small text-gray-600">
            <span>Dibuat oleh: <?= h($pengolahan['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>

    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>