<?php
// Form bersama untuk penjualan_tambah.php dan penjualan_edit.php.
// Variabel yang harus disiapkan pemanggil: $conn, $form_action, $label_tombol, $penjualan (array), $detail (array)

if (!isset($conn, $form_action, $label_tombol, $penjualan, $detail)) {
    exit;
}

require_once 'penjualan_helper.php';

$pelangganList = $conn->query("SELECT id, nama FROM pelanggan ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$gudangList = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$produkList = $conn->query("SELECT id, nama, satuan, harga_jual FROM produk ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

if (!$detail) {
    $detail = [[]]; // minimal satu baris kosong
}

/** Satu baris detail (dipakai untuk baris awal dan untuk template baris baru). */
function penjualan_baris_html(array $produkList, array $row = []): string
{
    $pid   = (string)($row['produk_id'] ?? '');
    $qty   = (string)($row['qty'] ?? '');
    $harga = (string)($row['harga'] ?? '0');

    // Option pertama dikosongkan agar placeholder select2 muncul
    $opsi = '<option></option>';
    foreach ($produkList as $p) {
        $sel   = ((string)$p['id'] === $pid) ? ' selected' : '';
        $opsi .= '<option value="' . (int)$p['id'] . '" data-harga="' . penjualan_h($p['harga_jual']) . '"' . $sel . '>'
               . penjualan_h($p['nama'] . ' (' . $p['satuan'] . ')') . '</option>';
    }

    return '<tr>'
        . '<td><select name="produk_id[]" class="form-control form-control-sm select2 produk-select">' . $opsi . '</select></td>'
        . '<td><input type="number" name="qty[]" class="form-control form-control-sm text-right qty" min="0" step="0.01" value="' . penjualan_h($qty) . '" placeholder="0"></td>'
        . '<td><input type="number" name="harga[]" class="form-control form-control-sm text-right harga" min="0" step="0.01" value="' . penjualan_h($harga) . '"></td>'
        . '<td><input type="text" class="form-control form-control-sm text-right total-baris" value="0,00" readonly tabindex="-1"></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus baris"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}
?>

<form action="<?= penjualan_h($form_action) ?>" method="post" autocomplete="off" id="form-penjualan">
    <?php if (!empty($penjualan['id'])): ?>
        <input type="hidden" name="id" value="<?= (int)$penjualan['id'] ?>">
    <?php endif; ?>

    <!-- Data penjualan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Data Panen</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= penjualan_h($penjualan['tanggal'] ?? '') ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="kode">Kode</label>
                    <input type="text" class="form-control" id="kode" name="kode" maxlength="30" value="<?= penjualan_h($penjualan['kode'] ?? '') ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="pelanggan_id">Pelanggan</label>
                    <select class="form-control select2" id="pelanggan_id" name="pelanggan_id">
                        <option></option>
                        <?php foreach ($pelangganList as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (string)($penjualan['pelanggan_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>>
                                <?= penjualan_h($p['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="gudang_id">Gudang</label>
                    <select class="form-control select2" id="gudang_id" name="gudang_id">
                        <option></option>
                        <?php foreach ($gudangList as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" <?= (string)($penjualan['gudang_id'] ?? '') === (string)$g['id'] ? 'selected' : '' ?>>
                                <?= penjualan_h($g['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail penjualan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Detail Produk</h6>
            <button type="button" class="btn btn-success btn-sm" id="btn-tambah-baris">
                <i class="fas fa-plus"></i> Tambah Item
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th style="width:140px">Qty</th>
                            <th style="width:170px">Harga</th>
                            <th style="width:180px">Total</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <?php foreach ($detail as $baris): ?>
                            <?= penjualan_baris_html($produkList, $baris) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ringkasan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Ringkasan</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="subtotal">Subtotal</label>
                    <input type="text" class="form-control text-right" id="subtotal" value="0,00" readonly tabindex="-1">
                </div>
                <div class="form-group col-md-4">
                    <label for="pajak">Pajak</label>
                    <input type="number" class="form-control text-right hitung-input" id="pajak" name="pajak" min="0" step="0.01" value="<?= penjualan_h($penjualan['pajak'] ?? '0') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="ongkir">Ongkir</label>
                    <input type="number" class="form-control text-right hitung-input" id="ongkir" name="ongkir" min="0" step="0.01" value="<?= penjualan_h($penjualan['ongkir'] ?? '0') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="biaya">Biaya</label>
                    <input type="number" class="form-control text-right hitung-input" id="biaya" name="biaya" min="0" step="0.01" value="<?= penjualan_h($penjualan['biaya'] ?? '0') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="rafaksi">Rafaksi</label>
                    <input type="number" class="form-control text-right hitung-input" id="rafaksi" name="rafaksi" min="0" step="0.01" value="<?= penjualan_h($penjualan['rafaksi'] ?? '0') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="diskon">Diskon</label>
                    <input type="number" class="form-control text-right hitung-input" id="diskon" name="diskon" min="0" step="0.01" value="<?= penjualan_h($penjualan['diskon'] ?? '0') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="grand_total">Grand Total</label>
                    <input type="text" class="form-control text-right font-weight-bold" id="grand_total" value="0,00" readonly tabindex="-1">
                </div>
            </div>
            <small class="form-text text-muted mb-3">
                Grand total = subtotal + pajak + ongkir + biaya &minus; rafaksi &minus; diskon. Total dihitung ulang oleh server saat disimpan.
            </small>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= penjualan_h($label_tombol) ?>
            </button>
            <a href="penjualan_index.php" class="btn btn-secondary">Batal</a>
        </div>
    </div>
</form>

<!-- Template baris baru (isinya belum aktif sebagai select2, diaktifkan saat baris ditambahkan) -->
<template id="tpl-baris"><?= penjualan_baris_html($produkList) ?></template>

<script>
// jQuery dan select2 dimuat di scripts.php (setelah bagian ini), jadi dijalankan saat halaman selesai dimuat.
// Select2 memicu event 'change' lewat jQuery, karena itu pemantauan select produk memakai jQuery.
window.addEventListener('load', function () {
    var $tbody = $('#tbody-detail');
    var tpl    = document.getElementById('tpl-baris');
    var fmt    = new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function num(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function hitung() {
        var sub = 0;
        $tbody.find('tr').each(function () {
            var t = num($(this).find('.qty').val()) * num($(this).find('.harga').val());
            $(this).find('.total-baris').val(fmt.format(t));
            sub += t;
        });

        var grand = sub;
        ['pajak', 'ongkir', 'biaya'].forEach(function (id) {
            grand += num($('#' + id).val());
        });
        ['rafaksi', 'diskon'].forEach(function (id) {
            grand -= num($('#' + id).val());
        });

        $('#subtotal').val(fmt.format(sub));
        $('#grand_total').val(fmt.format(grand));
    }

    // Opsi sama dengan inisialisasi global .select2 di scripts.php
    function aktifkanSelect2($baris) {
        $baris.find('.select2').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Pilih --',
            allowClear: true
        });
    }

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
        var $sel   = $baris.find('select.produk-select');
        try {
            // destroy hanya valid jika select2 aktif; kalau tidak, melempar error dan baris tidak terhapus
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }
        } catch (err) {
            // diabaikan, baris tetap dihapus
        }
        $baris.remove();
        if (!$tbody.find('tr').length) { tambahBaris(); }
        hitung();
    });

    $tbody.on('change', '.produk-select', function () {
        var opt = this.options[this.selectedIndex];
        if (opt && opt.value !== '') {
            // Harga awal diambil dari harga beli produk, tetap bisa diubah
            $(this).closest('tr').find('.harga').val(opt.getAttribute('data-harga') || 0);
        }
        hitung();
    });

    $tbody.on('input', '.qty, .harga', hitung);
    $('.hitung-input').on('input', hitung);

    hitung();
});
</script>