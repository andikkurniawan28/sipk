<?php
$page_title = "Tambah Serah Terima Produksi";
include 'config.php';

function stp_kode_baru(mysqli $conn): string {
    $prefix = 'STP' . date('ymd');
    $stmt = $conn->prepare("SELECT kode FROM serah_terima_produksi WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;
    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

$tanggal_ini   = date('Y-m-d');
$kode_otomatis = stp_kode_baru($conn);

$gudangList = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$produkList = $conn->query("SELECT id, kode, nama, satuan FROM produk WHERE id NOT IN (1, 2) ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

function stp_baris_html(array $produkList): string {
    $opsi = '<option></option>';
    foreach ($produkList as $p) {
        $opsi .= '<option value="' . (int)$p['id'] . '">' . h($p['kode'] . ' - ' . $p['nama'] . ' (' . $p['satuan'] . ')') . '</option>';
    }
    return '<tr>'
        . '<td><select name="produk_id[]" class="form-control form-control-sm select2 produk-select" required>' . $opsi . '</select></td>'
        . '<td><input type="number" name="qty[]" class="form-control form-control-sm text-right qty" min="0" step="any" value="1" required></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}

include 'header.php';
?>

<style>
    body { overflow-x: hidden; }
    .container-fluid { padding-left: 1rem; padding-right: 1rem; }
    .card { margin-bottom: 0.75rem !important; }
    .card-body { padding: 0.75rem 1rem !important; }
    .table-compact { max-height: 260px; overflow-y: auto; display: block; }
    .table-compact table { width: 100%; margin-bottom: 0; }
    .table-compact thead th { position: sticky; top: 0; background: #f8f9fc; z-index: 1; }
    .form-group { margin-bottom: 0.5rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="h3 mb-0 text-gray-800"><?= h($page_title) ?></h1>
</div>

<form action="serah_terima_produksi_simpan.php" method="post" autocomplete="off" id="form-serah-terima">
    <!-- Informasi -->
    <div class="card shadow">
        <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-primary">Informasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Kode</label>
                    <input type="text" class="form-control form-control-sm" name="kode" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-3">
                    <label>Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Gudang</label>
                    <select class="form-control form-control-sm select2" name="gudang_id" required>
                        <option></option>
                        <?php foreach ($gudangList as $g): ?>
                            <option value="<?= (int)$g['id'] ?>"><?= h($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Pengambilan Bahan</label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="pengambilan_bahan_kode" name="pengambilan_bahan_kode"
                           maxlength="30" placeholder="PBH..." required>
                    <small id="pb_status" class="form-text text-muted">Ketik nomor pengambilan bahan.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail -->
    <div class="card shadow">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Detail Barang Jadi</h6>
            <button type="button" class="btn btn-success btn-sm py-0 px-2" id="btn-tambah-baris">
                <i class="fas fa-plus"></i> Tambah
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive table-compact">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th style="width:160px">Qty</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <?= stp_baris_html($produkList) ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="mt-2 mb-3">
        <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan" disabled><i class="fas fa-save"></i> Simpan</button>
        <a href="serah_terima_produksi_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<template id="tpl-baris"><?= stp_baris_html($produkList) ?></template>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
$(function () {
    var PANJANG_KODE = 14;          // PBH + yymmdd + 5 digit
    var $tbody = $('#tbody-detail');
    var tpl = document.getElementById('tpl-baris');
    var $kode = $('#pengambilan_bahan_kode');
    var $status = $('#pb_status');
    var xhrCek = null;
    var pbValid = false;

    function setStatus(teks, kelas) {
        $status.removeClass('text-muted text-success text-danger').addClass(kelas).text(teks);
    }

    function setValid(ok) {
        pbValid = ok;
        $('#btn-simpan').prop('disabled', !ok);
    }

    $kode.on('input', function () {
        var v = $.trim(this.value).toUpperCase();
        this.value = v;

        if (xhrCek) { xhrCek.abort(); xhrCek = null; }
        setValid(false);

        if (v.length === 0) { setStatus('Ketik nomor pengambilan bahan.', 'text-muted'); return; }
        if (v.length < PANJANG_KODE) { setStatus('Nomor belum lengkap...', 'text-muted'); return; }

        setStatus('Memeriksa...', 'text-muted');

        xhrCek = $.ajax({
            url: 'pengambilan_bahan_cek.php',
            type: 'GET',
            data: { kode: v },
            dataType: 'json'
        })
        .done(function (res) {
            if (res.success) {
                setValid(true);
                setStatus('Valid (tanggal ' + res.tanggal + ')', 'text-success');
            } else {
                setStatus(res.pesan || 'Nomor tidak valid.', 'text-danger');
            }
        })
        .fail(function (xhr, status) {
            if (status === 'abort') return;
            setStatus('Gagal memeriksa nomor.', 'text-danger');
        })
        .always(function () { xhrCek = null; });
    });

    $('#btn-tambah-baris').on('click', function () {
        var $baris = $(tpl.content.querySelector('tr').cloneNode(true));
        $baris.find('.select2').select2({ theme: 'bootstrap4', width: '100%', placeholder: '-- Pilih --', allowClear: true });
        $tbody.append($baris);
    });

    $tbody.on('click', '.btn-hapus-baris', function () {
        var $baris = $(this).closest('tr');
        var $sel = $baris.find('select.produk-select');
        if ($sel.hasClass('select2-hidden-accessible')) { $sel.select2('destroy'); }
        $baris.remove();
        if (!$tbody.find('tr').length) { $('#btn-tambah-baris').click(); }
    });

    $('#form-serah-terima').on('submit', function (e) {
        if (!pbValid) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Nomor belum valid', text: 'Isi nomor pengambilan bahan yang valid terlebih dahulu.' });
        }
    });
});
</script>