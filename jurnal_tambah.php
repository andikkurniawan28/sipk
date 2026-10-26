<?php
$page_title = "Tambah Jurnal";
include 'config.php';

// ==========================================
// 1. FUNGSI BANTU & HELPER
// ==========================================
function jurnal_h($teks): string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

/**
 * Kode otomatis: JRN + yymmdd + "-" + nomor urut 3 digit, mis. JRN261023-001.
 */
function jurnal_kode_baru(mysqli $conn, string $tanggal): string
{
    $prefix = 'JRN' . date('ymd');
    
    $stmt = $conn->prepare("SELECT kode FROM jurnal WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;

    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

// ==========================================
// 2. PROSES FORM SUBMIT (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = trim((string)($_POST['tanggal'] ?? ''));
    $kode = trim((string)($_POST['kode'] ?? ''));
    $keterangan = trim((string)($_POST['keterangan'] ?? ''));
    $user_id = 1; // Sesuaikan dengan user yang sedang login (Session user_id)

    // Validasi Tanggal
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$d || $d->format('Y-m-d') !== $tanggal) {
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Tanggal tidak valid.'];
        header('Location: jurnal_tambah.php');
        exit;
    }

    // Validasi Kode
    if ($kode === '') {
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Kode jurnal wajib diisi.'];
        header('Location: jurnal_tambah.php');
        exit;
    }

    // Validasi Detail Jurnal
    $akun_ids = $_POST['akun_id'] ?? [];
    $debets = $_POST['debit'] ?? [];
    $kredits = $_POST['kredit'] ?? [];

    $items = [];
    $totalDebit = 0.0;
    $totalKredit = 0.0;

    foreach ($akun_ids as $i => $akun_id) {
        $aid = (int)$akun_id;
        if ($aid <= 0) continue;

        $deb = (float)($debets[$i] ?? 0);
        $kre = (float)($kredits[$i] ?? 0);

        if ($deb < 0 || $kre < 0) {
            $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Nilai debit atau kredit tidak boleh negatif.'];
            header('Location: jurnal_tambah.php');
            exit;
        }

        $items[] = [
            'akun_id' => $aid,
            'debit' => $deb,
            'kredit' => $kre
        ];

        $totalDebit += $deb;
        $totalKredit += $kre;
    }

    if (empty($items)) {
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Minimal harus ada satu baris detail jurnal.'];
        header('Location: jurnal_tambah.php');
        exit;
    }

    // Validasi Balance (Debit harus sama dengan Kredit)
    if (round($totalDebit, 2) !== round($totalKredit, 2)) {
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Jurnal Tidak Balance', 'text' => 'Total Debit (' . number_format($totalDebit, 2) . ') harus sama dengan Total Kredit (' . number_format($totalKredit, 2) . ').'];
        header('Location: jurnal_tambah.php');
        exit;
    }

    // Simpan ke Database menggunakan Transaksi
    $conn->begin_transaction();
    try {
        // Insert Header Jurnal
        $stmtJurnal = $conn->prepare("INSERT INTO jurnal (tanggal, kode, user_id, keterangan) VALUES (?, ?, ?, ?)");
        $stmtJurnal->bind_param('ssis', $tanggal, $kode, $user_id, $keterangan);
        $stmtJurnal->execute();
        $jurnal_id = $conn->insert_id;
        $stmtJurnal->close();

        // Insert Detail Jurnal (Multi-row insert)
        $n = count($items);
        $sqlDetail = 'INSERT INTO jurnal_detail (jurnal_id, akun_id, debit, kredit) VALUES ' . implode(',', array_fill(0, $n, '(?,?,?,?)'));
        $params = [];
        foreach ($items as $it) {
            array_push($params, $jurnal_id, $it['akun_id'], $it['debit'], $it['kredit']);
        }

        $stmtDetail = $conn->prepare($sqlDetail);
        $stmtDetail->bind_param(str_repeat('iidd', $n), ...$params);
        $stmtDetail->execute();
        $stmtDetail->close();

        // (Opsional) Posting ke Buku Besar jika diperlukan sesuai rancangan Anda
        foreach ($items as $it) {
            $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, jurnal_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtBB->bind_param('siiisdd', $tanggal, $jurnal_id, $it['akun_id'], $user_id, $keterangan, $it['debit'], $it['kredit']);
            $stmtBB->execute();
            $stmtBB->close();
        }

        $conn->commit();
        $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data jurnal berhasil disimpan.'];
        header('Location: jurnal_index.php');
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal Database', 'text' => $e->getMessage()];
        header('Location: jurnal_tambah.php');
        exit;
    }
}

// ==========================================
// 3. INISIALISASI DATA FORM
// ==========================================
$tanggal_ini = date('Y-m-d');
$kode_otomatis = jurnal_kode_baru($conn, $tanggal_ini);

// Ambil daftar akun untuk dropdown select2
$akunList = $conn->query("SELECT id, kode, nama FROM akun ORDER BY kode ASC")->fetch_all(MYSQLI_ASSOC);

// Template baris detail untuk HTML & JS Clone
function jurnal_baris_html(array $akunList, array $row = []): string
{
    $akun_id = (string)($row['akun_id'] ?? '');
    $debit = (string)($row['debit'] ?? '0');
    $kredit = (string)($row['kredit'] ?? '0');

    $opsi = '<option></option>';
    foreach ($akunList as $a) {
        $sel = ((string)$a['id'] === $akun_id) ? ' selected' : '';
        $opsi .= '<option value="' . (int)$a['id'] . '"' . $sel . '>' . jurnal_h($a['kode'] . ' - ' . $a['nama']) . '</option>';
    }

    return '<tr>'
        . '<td><select name="akun_id[]" class="form-control form-control-sm select2 akun-select" required>' . $opsi . '</select></td>'
        . '<td><input type="number" name="debit[]" class="form-control form-control-sm text-right debit" min="0" step="0.01" value="' . jurnal_h($debit) . '" placeholder="0"></td>'
        . '<td><input type="number" name="kredit[]" class="form-control form-control-sm text-right kredit" min="0" step="0.01" value="' . jurnal_h($kredit) . '" placeholder="0"></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus baris"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<form action="jurnal_simpan.php" method="post" autocomplete="off" id="form-jurnal">
    <!-- Data Header Jurnal -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Informasi Jurnal</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="kode">Kode Jurnal</label>
                    <input type="text" class="form-control" id="kode" name="kode" maxlength="30" value="<?= jurnal_h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= jurnal_h($tanggal_ini) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="keterangan">Keterangan</label>
                    <textarea class="form-control" id="keterangan" name="keterangan" rows="2" placeholder="Keterangan transaksi jurnal..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Jurnal (Akun Debit & Kredit) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Detail Akun Jurnal</h6>
            <button type="button" class="btn btn-success btn-sm" id="btn-tambah-baris">
                <i class="fas fa-plus"></i> Tambah Baris
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th style="width:200px">Debit</th>
                            <th style="width:200px">Kredit</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <!-- Default 2 baris awal (1 debit, 1 kredit) -->
                        <?= jurnal_baris_html($akunList) ?>
                        <?= jurnal_baris_html($akunList) ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="text-right">Total:</th>
                            <th class="text-right" id="total-debit">0,00</th>
                            <th class="text-right" id="total-kredit">0,00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div id="status-balance" class="alert alert-warning mt-2 py-2 text-center font-weight-bold" style="display: none;">
                ⚠️ Total Debit dan Kredit belum balance!
            </div>
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="mb-4">
        <button type="submit" class="btn btn-primary" id="btn-simpan">
            <i class="fas fa-save"></i> Simpan
        </button>
        <a href="jurnal_index.php" class="btn btn-secondary">Batal</a>
    </div>
</form>

<!-- Template baris baru -->
<template id="tpl-baris"><?= jurnal_baris_html($akunList) ?></template>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
window.addEventListener('load', function () {
    var $tbody = $('#tbody-detail');
    var tpl = document.getElementById('tpl-baris');
    var fmt = new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function num(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function hitung() {
        var totalDeb = 0;
        var totalKre = 0;

        $tbody.find('tr').each(function () {
            totalDeb += num($(this).find('.debit').val());
            totalKre += num($(this).find('.kredit').val());
        });

        $('#total-debit').text(fmt.format(totalDeb));
        $('#total-kredit').text(fmt.format(totalKre));

        // Cek Balance
        if (Math.abs(totalDeb - totalKre) > 0.01 || totalDeb === 0) {
            $('#status-balance').show();
            $('#btn-simpan').prop('disabled', true);
        } else {
            $('#status-balance').hide();
            $('#btn-simpan').prop('disabled', false);
        }
    }

    function aktifkanSelect2($baris) {
        $baris.find('.select2').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Pilih Akun --',
            allowClear: true
        });
    }

    // Aktifkan select2 untuk baris yang ada saat load
    $tbody.find('tr').each(function() {
        aktifkanSelect2($(this));
    });

    function tambahBaris() {
        var $baris = $(tpl.content.querySelector('tr').cloneNode(true));
        $tbody.append($baris);
        aktifkanSelect2($baris);
    }

    $('#btn-tambah-baris').on('click', function () {
        tambahBaris();
        hitung();
    });

    $tbody.on('click', '.btn-hapus-baris', function () {
        var $baris = $(this).closest('tr');
        var $sel = $baris.find('select.akun-select');
        try {
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }
        } catch (err) {}
        $baris.remove();
        if (!$tbody.find('tr').length) { tambahBaris(); }
        hitung();
    });

    // Cegah isi debit dan kredit di baris yang sama secara bersamaan (opsional, tapi standar akuntansi)
    $tbody.on('input', '.debit', function () {
        if (num($(this).val()) > 0) {
            $(this).closest('tr').find('.kredit').val(0);
        }
        hitung();
    });

    $tbody.on('input', '.kredit', function () {
        if (num($(this).val()) > 0) {
            $(this).closest('tr').find('.debit').val(0);
        }
        hitung();
    });

    hitung();
});
</script>