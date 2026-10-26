<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

// Ambil data header jurnal beserta nama user pembuat
$stmt = $conn->prepare("
    SELECT j.id, j.tanggal, j.kode, j.keterangan,
           u.nama AS dibuat_oleh
    FROM jurnal j
    JOIN `user` u ON u.id = j.user_id
    WHERE j.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$jurnal = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect jika data jurnal tidak ditemukan
if (!$jurnal) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data jurnal tidak ada.'];
    header('Location: jurnal_index.php');
    exit;
}

// Ambil rincian detail akun jurnal (debit/kredit)
$stmt = $conn->prepare("
    SELECT d.debit, d.kredit, a.kode AS akun_kode, a.nama AS akun_nama
    FROM jurnal_detail d
    JOIN akun a ON a.id = d.akun_id
    WHERE d.jurnal_id = ?
    ORDER BY d.id ASC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$page_title = "Jurnal " . $jurnal['kode'];
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
    <h1 class="h3 mb-0 text-gray-800">Detail Jurnal</h1>
    <div>
        <a href="jurnal_index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">

        <!-- Kepala lembar jurnal -->
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">JURNAL UMUM</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($jurnal['kode']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($jurnal['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <!-- Keterangan Jurnal -->
        <div class="row mb-4">
            <div class="col-sm-12">
                <div class="invoice-label">Keterangan</div>
                <div class="text-gray-800 font-weight-bold"><?= h($jurnal['keterangan'] ?? '-') ?></div>
            </div>
        </div>

        <!-- Rincian item akun (debit / kredit) -->
        <div class="table-responsive">
            <table class="table table-sm">
                <thead class="thead-light">
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Akun</th>
                        <th class="text-right" style="width:180px">Debit</th>
                        <th class="text-right" style="width:180px">Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalDebit = 0;
                    $totalKredit = 0;
                    foreach ($detail as $i => $d): 
                        $totalDebit += (float)$d['debit'];
                        $totalKredit += (float)$d['kredit'];
                        $isKredit = (float)$d['kredit'] > 0;
                        // Indentasi baris nama akun jika posisi di Kredit
                        $styleIndent = $isKredit ? 'style="padding-left: 30px;"' : '';
                    ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td <?= $styleIndent ?>><?= h($d['akun_kode'] . ' - ' . $d['akun_nama']) ?></td>
                            <td class="text-right"><?= ((float)$d['debit'] > 0) ? rp($d['debit']) : '-' ?></td>
                            <td class="text-right"><?= ((float)$d['kredit'] > 0) ? rp($d['kredit']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="border-top font-weight-bold">
                        <th colspan="2" class="text-right">TOTAL :</th>
                        <th class="text-right"><?= rp($totalDebit) ?></th>
                        <th class="text-right"><?= rp($totalKredit) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <hr class="mt-4">

        <div class="d-flex justify-content-between small text-gray-600">
            <span>Dibuat oleh: <?= h($jurnal['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>

    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>