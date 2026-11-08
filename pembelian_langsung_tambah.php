<?php
$page_title = "Tambah Pembelian Langsung";
include 'config.php';
require_once 'form_core.php';
require_once 'pembelian_langsung_core.php';

$tanggalHariIni  = date('Y-m-d');
$kodeBaru        = buatKodeBaru($conn, 'pembelian_langsung', 'PBL', $tanggalHariIni);
$daftarPemasok   = ambilDaftarPemasok($conn);
$htmlBarisKosong = htmlBarisProdukPembelian(ambilDaftarProdukPembelian($conn));

include 'header.php';
?>

<style>
    body { overflow-x: hidden; }
    .container-fluid { padding-left: 1rem; padding-right: 1rem; }
    .card { margin-bottom: 0.75rem !important; }
    .card-body { padding: 0.75rem 1rem !important; }
    .table-compact { max-height: 200px; overflow-y: auto; display: block; }
    .table-compact table { width: 100%; margin-bottom: 0; }
    .table-compact thead th { position: sticky; top: 0; background: #f8f9fc; z-index: 1; }
    .form-group { margin-bottom: 0.5rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="h3 mb-0 text-gray-800"><?= h($page_title) ?></h1>
</div>

<form action="pembelian_langsung_simpan.php" method="post" autocomplete="off" id="form-pembelian">
    <div class="card shadow">
        <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-primary">Informasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Kode</label>
                    <input type="text" class="form-control form-control-sm" id="kode" name="kode" value="<?= h($kodeBaru) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label>Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= h($tanggalHariIni) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Pemasok</label>
                    <select class="form-control form-control-sm select2" id="pemasok_id" name="pemasok_id" required>
                        <?= htmlOpsiPemasok($daftarPemasok) ?>
                    </select>
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
                            <th>Produk</th>
                            <th style="width:140px">Qty</th>
                            <th style="width:170px">Harga</th>
                            <th style="width:180px">Total</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail"><?= $htmlBarisKosong ?></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-primary">Valuasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <?= htmlTampilanNominalValuasi('subtotal', 'Subtotal') ?>
                <?= htmlInputNominalValuasi('pajak', 'Pajak') ?>
                <?= htmlInputNominalValuasi('ongkir', 'Ongkir') ?>
            </div>
            <div class="form-row">
                <?= htmlInputNominalValuasi('biaya', 'Biaya') ?>
                <?= htmlInputNominalValuasi('diskon', 'Diskon') ?>
                <?= htmlTampilanNominalValuasi('grand_total', 'Total', 'font-weight-bold text-primary') ?>
            </div>
        </div>
    </div>

    <div class="mt-2 mb-3">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan</button>
        <a href="pembelian_langsung_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<template id="tpl-baris"><?= $htmlBarisKosong ?></template>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
$(function () {
    var $tbodyDetail  = $('#tbody-detail');
    var templateBaris = document.getElementById('tpl-baris');
    var formatRibuan  = new Intl.NumberFormat('id-ID', {maximumFractionDigits: 0});

    function angka(nilai) {
        var hasil = parseFloat(nilai);
        return isNaN(hasil) ? 0 : hasil;
    }

    function totalBaris($baris) {
        return angka($baris.find('.qty').val()) * angka($baris.find('.harga').val());
    }

    function hitungSubtotal() {
        var subtotal = 0;

        $tbodyDetail.children('tr').each(function () {
            subtotal += totalBaris($(this));
        });

        return subtotal;
    }

    function hitungGrandTotal(subtotal) {
        return subtotal
            + angka($('#pajak').val())
            + angka($('#ongkir').val())
            + angka($('#biaya').val())
            - angka($('#diskon').val());
    }

    function perbaruiTotal() {
        $tbodyDetail.children('tr').each(function () {
            $(this).find('.total-baris').val(formatRibuan.format(totalBaris($(this))));
        });

        var subtotal = hitungSubtotal();

        $('#subtotal').val(formatRibuan.format(subtotal));
        $('#grand_total').val(formatRibuan.format(hitungGrandTotal(subtotal)));
    }

    function aktifkanSelect2($konteks) {
        $konteks.find('.select2').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Pilih --',
            allowClear: true
        });
    }

    function isiHargaDariProduk($baris) {
        var $opsiTerpilih = $baris.find('.produk-select option:selected');

        if ($opsiTerpilih.length && $opsiTerpilih.val() !== '') {
            $baris.find('.harga').val($opsiTerpilih.attr('data-harga') || 0);
        }
    }

    function tambahBaris() {
        var $baris = $(templateBaris.content.firstElementChild.cloneNode(true));

        $tbodyDetail.append($baris);
        aktifkanSelect2($baris);
    }

    function hapusBaris($baris) {
        $baris.find('.produk-select.select2-hidden-accessible').select2('destroy');
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

    $tbodyDetail.on('change', '.produk-select', function () {
        isiHargaDariProduk($(this).closest('tr'));
        perbaruiTotal();
    });

    $tbodyDetail.on('input', '.qty, .harga', perbaruiTotal);
    $('.hitung-input').on('input', perbaruiTotal);

    aktifkanSelect2($tbodyDetail);
    perbaruiTotal();
});
</script>