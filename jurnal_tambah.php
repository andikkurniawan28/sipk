<?php
$page_title = "Tambah Jurnal";
include 'config.php';
require_once 'jurnal_core.php';

$tanggalHariIni  = date('Y-m-d');
$kodeBaru        = buatKodeBaru($conn, 'jurnal', 'JRN', $tanggalHariIni);
$daftarAkun      = ambilDaftarAkun($conn);
$htmlBarisKosong = htmlBarisDetailJurnal($daftarAkun);

include 'header.php';
?>

<style>
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
    <div class="card shadow">
        <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-primary">Informasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Kode</label>
                    <input type="text" class="form-control form-control-sm" id="kode" name="kode" maxlength="30" value="<?= h($kodeBaru) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label>Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= h($tanggalHariIni) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Keterangan</label>
                    <input type="text" class="form-control form-control-sm" id="keterangan" name="keterangan" placeholder="Keterangan transaksi..." required>
                </div>
            </div>
        </div>
    </div>

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
                    <tbody id="tbody-detail"><?= str_repeat($htmlBarisKosong, JUMLAH_BARIS_AWAL_JURNAL) ?></tbody>
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

    <div class="mt-2 mb-3">
        <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan">
            <i class="fas fa-save"></i> Simpan
        </button>
        <a href="jurnal_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<template id="tpl-baris"><?= $htmlBarisKosong ?></template>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
$(function () {
    var TOLERANSI_SELISIH = 0.005;

    var $tbodyDetail   = $('#tbody-detail');
    var $btnSimpan     = $('#btn-simpan');
    var $statusBalance = $('#status-balance');
    var templateBaris  = document.getElementById('tpl-baris');
    var formatRibuan   = new Intl.NumberFormat('id-ID', {maximumFractionDigits: 0});

    function angka(nilai) {
        var hasil = parseFloat(nilai);
        return isNaN(hasil) ? 0 : hasil;
    }

    function jumlahkan(selektorKolom) {
        var total = 0;
        $tbodyDetail.find(selektorKolom).each(function () {
            total += angka(this.value);
        });
        return total;
    }

    function jurnalSeimbang(totalDebit, totalKredit) {
        return totalDebit > 0 && Math.abs(totalDebit - totalKredit) <= TOLERANSI_SELISIH;
    }

    function perbaruiTotal() {
        var totalDebit  = jumlahkan('.debit');
        var totalKredit = jumlahkan('.kredit');
        var seimbang    = jurnalSeimbang(totalDebit, totalKredit);

        $('#total-debit').text(formatRibuan.format(totalDebit));
        $('#total-kredit').text(formatRibuan.format(totalKredit));
        $statusBalance.toggle(!seimbang);
        $btnSimpan.prop('disabled', !seimbang);
    }

    function aktifkanSelect2($konteks) {
        $konteks.find('.akun-select').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Pilih --',
            allowClear: true
        });
    }

    function tambahBaris() {
        var $baris = $(templateBaris.content.firstElementChild.cloneNode(true));

        $tbodyDetail.append($baris);
        aktifkanSelect2($baris);
    }

    function hapusBaris($baris) {
        $baris.find('.akun-select.select2-hidden-accessible').select2('destroy');
        $baris.remove();

        if (!$tbodyDetail.children().length) {
            tambahBaris();
        }
    }

    $('#btn-tambah-baris').on('click', function () {
        tambahBaris();
        perbaruiTotal();
    });

    $tbodyDetail.on('click', '.btn-hapus-baris', function () {
        hapusBaris($(this).closest('tr'));
        perbaruiTotal();
    });

    $tbodyDetail.on('input', '.debit, .kredit', function () {
        if (angka(this.value) > 0) {
            var kolomLawan = $(this).hasClass('debit') ? '.kredit' : '.debit';
            $(this).closest('tr').find(kolomLawan).val(0);
        }
        perbaruiTotal();
    });

    aktifkanSelect2($tbodyDetail);
    perbaruiTotal();
});
</script>