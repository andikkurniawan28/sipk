<?php
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

/** Barcode Code 128 (subset B) sebagai SVG inline. Mendukung karakter ASCII 32-126. */
function barcode128_svg(string $teks, int $modul = 2, int $tinggi = 50): string
{
    static $pola = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112',
    ];

    $nilai = [104];                       // 104 = Start B
    $jumlah = 104;
    $n = strlen($teks);
    for ($i = 0; $i < $n; $i++) {
        $ord = ord($teks[$i]);
        if ($ord < 32 || $ord > 126) {
            return '';                    // karakter tidak didukung
        }
        $v = $ord - 32;
        $nilai[] = $v;
        $jumlah += $v * ($i + 1);
    }
    $nilai[] = $jumlah % 103;             // checksum
    $nilai[] = 106;                       // Stop

    $quiet = 10 * $modul;                 // area kosong kiri-kanan
    $x = $quiet;
    $rect = '';
    foreach ($nilai as $v) {
        $lebar = str_split($pola[$v]);
        foreach ($lebar as $k => $w) {
            $px = (int)$w * $modul;
            if ($k % 2 === 0) {           // elemen genap = garis hitam
                $rect .= '<rect x="' . $x . '" y="0" width="' . $px . '" height="' . $tinggi . '"/>';
            }
            $x += $px;
        }
    }
    $lebarTotal = $x + $quiet;

    return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Barcode ' . h($teks) . '"'
         . ' viewBox="0 0 ' . $lebarTotal . ' ' . $tinggi . '"'
         . ' width="' . $lebarTotal . '" height="' . $tinggi . '"'
         . ' style="background:#fff;max-width:100%;height:auto" shape-rendering="crispEdges">'
         . '<g fill="#000">' . $rect . '</g></svg>';
}

/** Form tombol perubahan status (POST ke proposal_status.php). */
function tombol_status(int $id, string $aksi, string $label, string $kelas, string $ikon, string $tanya): string
{
    return '<form method="post" action="proposal_status.php" class="d-inline"'
         . ' onsubmit="return confirm(' . h(json_encode($tanya, JSON_UNESCAPED_UNICODE)) . ');">'
         . '<input type="hidden" name="id" value="' . $id . '">'
         . '<input type="hidden" name="aksi" value="' . h($aksi) . '">'
         . '<button type="submit" class="btn ' . h($kelas) . ' btn-sm">'
         . '<i class="fas ' . h($ikon) . '"></i> ' . h($label) . '</button>'
         . '</form> ';
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.id, p.tanggal, p.kode, p.status, p.user_id, p.disetujui_pada,
           p.subtotal, p.pajak, p.ongkir, p.biaya, p.rafaksi, p.diskon, p.grand_total,
           pt.nama AS petani_nama, pt.alamat AS petani_alamat, pt.telepon AS petani_telepon,
           u.nama  AS dibuat_oleh,
           uk.nama AS disetujui_nama
    FROM proposal p
    JOIN petani pt ON pt.id = p.petani_id
    JOIN `user` u  ON u.id  = p.user_id
    LEFT JOIN `user` uk ON uk.id = p.disetujui_oleh
    WHERE p.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$proposal = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$proposal) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data proposal tidak ada.'];
    header('Location: proposal_index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT d.qty, d.harga, d.total, pr.nama, pr.satuan
    FROM proposal_detail d
    JOIN produk pr ON pr.id = d.produk_id
    WHERE d.proposal_id = ?
    ORDER BY d.id ASC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Hak akses tombol status ---
$userLogin = (int)($_SESSION['sipk-user_id'] ?? 0);
$stmt = $conn->prepare('SELECT role FROM `user` WHERE id = ? AND aktif = 1');
$stmt->bind_param('i', $userLogin);
$stmt->execute();
$uRole = $stmt->get_result()->fetch_assoc();
$stmt->close();

$role       = $uRole['role'] ?? '';
$direktur   = ($role === 'Direktur');
$bisaAjukan = in_array($role, ['Direktur', 'Tanaman'], true);
$status     = $proposal['status'];
$pid        = (int)$proposal['id'];

$warnaStatus = [
    'Draft'      => 'secondary',
    'Diajukan'   => 'warning',
    'Disetujui'  => 'success',
    'Ditolak'    => 'danger',
    'Dibatalkan' => 'dark',
];

$page_title = "Proposal " . $proposal['kode'];
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
    <h1 class="h3 mb-0 text-gray-800">Detail Proposal</h1>
    <div>
        <?php
        if ($status === 'Draft' && $bisaAjukan) {
            echo tombol_status($pid, 'ajukan', 'Ajukan', 'btn-warning', 'fa-paper-plane',
                'Ajukan proposal ini? Setelah diajukan, isi proposal tidak boleh diubah lagi.');
        }
        if ($status === 'Diajukan' && $direktur) {
            echo tombol_status($pid, 'setujui', 'Setujui', 'btn-success', 'fa-check', 'Setujui proposal ini?');
            echo tombol_status($pid, 'tolak', 'Tolak', 'btn-danger', 'fa-times', 'Tolak proposal ini?');
        }
        if (in_array($status, ['Draft', 'Diajukan'], true) && $direktur) {
            echo tombol_status($pid, 'batal', 'Batalkan', 'btn-dark', 'fa-ban',
                'Batalkan proposal ini? Tindakan ini tidak bisa diurungkan.');
        }
        ?>
        <a href="proposal_index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">

        <!-- Kepala dokumen -->
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">PROPOSAL</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($proposal['kode']) ?></div>
                <span class="badge badge-<?= h($warnaStatus[$status] ?? 'secondary') ?> mt-1"><?= h($status) ?></span>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($proposal['tanggal'])) ?></div>
                <?php if ($status === 'Disetujui'): ?>
                    <div class="mt-2"><?= barcode128_svg($proposal['kode']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <hr>

        <!-- Petani -->
        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <div class="invoice-label">Petani</div>
                <div class="font-weight-bold text-gray-800"><?= h($proposal['petani_nama']) ?></div>
                <?php if ($proposal['petani_alamat'] !== null && $proposal['petani_alamat'] !== ''): ?>
                    <div class="small text-gray-700"><?= nl2br(h($proposal['petani_alamat'])) ?></div>
                <?php endif; ?>
                <?php if ($proposal['petani_telepon'] !== null && $proposal['petani_telepon'] !== ''): ?>
                    <div class="small text-gray-700">Telp. <?= h($proposal['petani_telepon']) ?></div>
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
                        <td class="text-right"><?= rp($proposal['subtotal']) ?></td>
                    </tr>
                    <tr>
                        <td>Pajak</td>
                        <td class="text-right"><?= rp($proposal['pajak']) ?></td>
                    </tr>
                    <tr>
                        <td>Ongkir</td>
                        <td class="text-right"><?= rp($proposal['ongkir']) ?></td>
                    </tr>
                    <tr>
                        <td>Biaya</td>
                        <td class="text-right"><?= rp($proposal['biaya']) ?></td>
                    </tr>
                    <tr>
                        <td>Rafaksi</td>
                        <td class="text-right">- <?= rp($proposal['rafaksi']) ?></td>
                    </tr>
                    <tr>
                        <td>Diskon</td>
                        <td class="text-right">- <?= rp($proposal['diskon']) ?></td>
                    </tr>
                    <tr class="border-top">
                        <td class="font-weight-bold h5 pt-3">Grand Total</td>
                        <td class="text-right font-weight-bold h5 pt-3 text-primary"><?= rp($proposal['grand_total']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <hr class="mt-4">

        <div class="d-flex justify-content-between small text-gray-600">
            <span>
                Dibuat oleh: <?= h($proposal['dibuat_oleh']) ?>
                <?php if ($proposal['disetujui_nama'] !== null && $proposal['disetujui_pada'] !== null): ?>
                    | <?= h($status) ?> oleh: <?= h($proposal['disetujui_nama']) ?>
                    (<?= h(date('d-m-Y H:i', strtotime($proposal['disetujui_pada']))) ?>)
                <?php endif; ?>
            </span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>

    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>