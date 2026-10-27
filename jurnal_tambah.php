<?php
$page_title = "Tambah Jurnal";
include 'config.php';

/**
 * Kode otomatis: JRN + yymmdd + "-" + nomor urut 5 digit, mis. JRN261023-00001.
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
// INISIALISASI DATA FORM
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
        $opsi .= '<option value="' . (int)$a['id'] . '"' . $sel . '>' . h($a['kode'] . ' - ' . $a['nama']) . '</option>';
    }

    return '<tr>'
        . '<td><select name="akun_id[]" class="form-control form-control-sm select2 akun-select" required>' . $opsi . '</select></td>'
        . '<td><input type="number" name="debit[]" class="form-control form-control-sm text-right debit" min="0" step="any" value="' . h($debit) . '" placeholder="0"></td>'
        . '<td><input type="number" name="kredit[]" class="form-control form-control-sm text-right kredit" min="0" step="any" value="' . h($kredit) . '" placeholder="0"></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}

include 'header.php';
?>

<style>
    /* Layout Kompak 1 Layar / Tanpa Scroll Berlebih */
    body { overflow-x: hidden; }
    .container-fluid { padding-left: 1rem; padding-right: 1rem; }
    .card { margin-bottom: 0.75rem !important; }
    .card-body { padding: 0.75rem 1rem !important; }
    .table-compact { max-height: 220px; overflow-y: auto; display: block; }
    .table-compact table { width: 100%; margin-bottom: 0; }
    .table-compact thead th { position: sticky; top: 0; background: #f8f9fc; z-index: 1; }
    .form-group { margin-bottom: 0.5rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="h3 mb-0 text-gray-800"><?= h($page_title) ?></h1>
</div>

<form action="jurnal_simpan.php" method="post" autocomplete="off" id="form-jurnal">
    <!-- Informasi Jurnal -->
    <div class="card shadow">
        <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-primary">Informasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Kode</label>
                    <input type="text" class="form-control form-control-sm" id="kode" name="kode" maxlength="30" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label>Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Keterangan</label>
                    <input type="text" class="form-control form-control-sm" id="keterangan" name="keterangan" placeholder="Keterangan transaksi..." required>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Akun Jurnal -->
    <div class="card shadow">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Detail</h6>
            <button type="button" class="btn btn-success btn-sm py-0 px-2" id="btn-tambah-baris">
                <i class="fas fa-plus"></i> Tambah
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive table-compact">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th style="width:200px">Debit</th>
                            <th style="width:200px">Kredit</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <?= jurnal_baris_html($akunList) ?>
                        <?= jurnal_baris_html($akunList) ?>
                    </tbody>
                </table>
            </div>
            <div class="p-2 bg-light border-top d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Total:</span>
                <div>
                    <span class="mr-4">Debit: <strong id="total-debit">0</strong></span>
                    <span>Kredit: <strong id="total-kredit">0</strong></span>
                </div>
            </div>
            <div id="status-balance" class="alert alert-warning m-2 py-1 text-center font-weight-bold small" style="display: none;">
                ⚠️ Total Debit dan Kredit belum balance!
            </div>
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="mt-2 mb-3">
        <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan">
            <i class="fas fa-save"></i> Simpan
        </button>
        <a href="jurnal_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<!-- Template baris baru -->
<template id="tpl-baris"><?= jurnal_baris_html($akunList) ?></template>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
$(function () {
    var $tbody = $('#tbody-detail');
    var tpl = document.getElementById('tpl-baris');
    var fmt = new Intl.NumberFormat('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0});

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

    function tambahBaris() {
        var $baris = $(tpl.content.querySelector('tr').cloneNode(true));
        
        $baris.find('.select2').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Pilih --',
            allowClear: true
        });

        $tbody.append($baris);
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