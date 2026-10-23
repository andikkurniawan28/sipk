<?php
// Form bersama untuk pengolahan_tambah.php dan pengolahan_edit.php.
// Variabel yang harus disiapkan pemanggil:
// $conn, $form_action, $label_tombol, $pengolahan (array), $detail_in (array), $detail_out (array)

if (!isset($conn, $form_action, $label_tombol, $pengolahan, $detail_in, $detail_out)) {
    exit;
}

require_once 'pengolahan_helper.php';

$gudangList = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$produkList = $conn->query("SELECT id, nama, satuan, harga_beli, harga_jual FROM produk ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

if (!$detail_in) {
    $detail_in = [[]]; // minimal satu baris kosong
}
if (!$detail_out) {
    $detail_out = [[]];
}

/** Satu baris detail. $awalan: 'in_' atau 'out_'. Dipakai untuk baris awal dan template baris baru. */
function pg_baris_html(array $produkList, array $gudangList, string $awalan, array $row = []): string
{
    $pid   = (string)($row['produk_id'] ?? '');
    $gid   = (string)($row['gudang_id'] ?? '');
    $qty   = (string)($row['qty'] ?? '');
    $harga = (string)($row['harga'] ?? '0');

    // Option pertama dikosongkan agar placeholder select2 muncul
    $opsiProduk = '<option></option>';
    foreach ($produkList as $p) {
        $sel         = ((string)$p['id'] === $pid) ? ' selected' : '';
        $opsiProduk .= '<option value="' . (int)$p['id'] . '"'
                     . ' data-beli="' . pg_h($p['harga_beli']) . '" data-jual="' . pg_h($p['harga_jual']) . '"' . $sel . '>'
                     . pg_h($p['nama'] . ' (' . $p['satuan'] . ')') . '</option>';
    }

    $opsiGudang = '<option></option>';
    foreach ($gudangList as $g) {
        $sel         = ((string)$g['id'] === $gid) ? ' selected' : '';
        $opsiGudang .= '<option value="' . (int)$g['id'] . '"' . $sel . '>' . pg_h($g['nama']) . '</option>';
    }

    return '<tr>'
        . '<td><select name="' . $awalan . 'produk_id[]" class="form-control form-control-sm select2 produk-select">' . $opsiProduk . '</select></td>'
        . '<td><select name="' . $awalan . 'gudang_id[]" class="form-control form-control-sm select2 gudang-select">' . $opsiGudang . '</select></td>'
        . '<td><input type="number" name="' . $awalan . 'qty[]" class="form-control form-control-sm text-right qty" min="0" step="0.01" value="' . pg_h($qty) . '" placeholder="0"></td>'
        . '<td><input type="number" name="' . $awalan . 'harga[]" class="form-control form-control-sm text-right harga" min="0" step="0.01" value="' . pg_h($harga) . '"></td>'
        . '<td><input type="text" class="form-control form-control-sm text-right total-baris" value="0,00" readonly tabindex="-1"></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus baris"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}

/** Tabel detail (kepala + tbody) untuk satu bagian. */
function pg_tabel_html(string $idTbody): string
{
    return '<thead><tr>'
        . '<th>Produk</th>'
        . '<th style="width:200px">Gudang</th>'
        . '<th style="width:120px">Qty</th>'
        . '<th style="width:150px">Harga</th>'
        . '<th style="width:160px">Total</th>'
        . '<th style="width:50px"></th>'
        . '</tr></thead><tbody id="' . $idTbody . '">';
}
?>

<form action="<?= pg_h($form_action) ?>" method="post" autocomplete="off" id="form-pengolahan">
    <?php if (!empty($pengolahan['id'])): ?>
        <input type="hidden" name="id" value="<?= (int)$pengolahan['id'] ?>">
    <?php endif; ?>

    <!-- Data pengolahan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Data Pengolahan</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= pg_h($pengolahan['tanggal'] ?? '') ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="kode">Kode</label>
                    <input type="text" class="form-control" id="kode" name="kode" maxlength="30" value="<?= pg_h($pengolahan['kode'] ?? '') ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="keterangan">Keterangan</label>
                    <textarea class="form-control" id="keterangan" name="keterangan" rows="2"><?= pg_h($pengolahan['keterangan'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Input pengolahan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Input (Bahan yang Dipakai)</h6>
            <button type="button" class="btn btn-success btn-sm" id="btn-tambah-in">
                <i class="fas fa-plus"></i> Tambah Input
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <?= pg_tabel_html('tbody-in') ?>
                        <?php foreach ($detail_in as $baris): ?>
                            <?= pg_baris_html($produkList, $gudangList, 'in_', $baris) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Output pengolahan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-success">Output (Hasil Pengolahan)</h6>
            <button type="button" class="btn btn-success btn-sm" id="btn-tambah-out">
                <i class="fas fa-plus"></i> Tambah Output
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <?= pg_tabel_html('tbody-out') ?>
                        <?php foreach ($detail_out as $baris): ?>
                            <?= pg_baris_html($produkList, $gudangList, 'out_', $baris) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ringkasan (hanya tampilan, tidak disimpan) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Ringkasan</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="total_input">Total Input (Persediaan Keluar)</label>
                    <input type="text" class="form-control text-right" id="total_input" value="0,00" readonly tabindex="-1">
                </div>
                <div class="form-group col-md-4">
                    <label for="total_output">Total Output (Persediaan Masuk)</label>
                    <input type="text" class="form-control text-right" id="total_output" value="0,00" readonly tabindex="-1">
                </div>
                <div class="form-group col-md-4">
                    <label for="selisih">Selisih (Output &minus; Input)</label>
                    <input type="text" class="form-control text-right font-weight-bold" id="selisih" value="0,00" readonly tabindex="-1">
                </div>
            </div>
            <small class="form-text text-muted mb-3">
                Total di sini hanya tampilan dan tidak disimpan. Nilainya selalu dihitung dari baris input dan output.
            </small>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= pg_h($label_tombol) ?>
            </button>
            <a href="pengolahan_index.php" class="btn btn-secondary">Batal</a>
        </div>
    </div>
</form>

<!-- Template baris baru (belum aktif sebagai select2, diaktifkan saat baris ditambahkan) -->
<template id="tpl-in"><?= pg_baris_html($produkList, $gudangList, 'in_') ?></template>
<template id="tpl-out"><?= pg_baris_html($produkList, $gudangList, 'out_') ?></template>

<script>
// jQuery dan select2 dimuat di scripts.php (setelah bagian ini), jadi dijalankan saat halaman selesai dimuat.
// Select2 memicu event 'change' lewat jQuery, karena itu pemantauan select memakai jQuery.
window.addEventListener('load', function () {
    var fmt = new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    // Harga awal: input memakai harga beli produk, output memakai harga jual (tetap bisa diubah)
    var bagian = [
        { tbody: $('#tbody-in'),  tpl: document.getElementById('tpl-in'),  atribut: 'data-beli', tombol: '#btn-tambah-in' },
        { tbody: $('#tbody-out'), tpl: document.getElementById('tpl-out'), atribut: 'data-jual', tombol: '#btn-tambah-out' }
    ];

    function num(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    // Hitung total tiap baris pada satu bagian, kembalikan jumlahnya
    function hitungBagian(b) {
        var jumlah = 0;
        b.tbody.find('tr').each(function () {
            var t = num($(this).find('.qty').val()) * num($(this).find('.harga').val());
            $(this).find('.total-baris').val(fmt.format(t));
            jumlah += t;
        });
        return jumlah;
    }

    function hitung() {
        var masuk  = hitungBagian(bagian[0]); // total input
        var keluar = hitungBagian(bagian[1]); // total output
        var selisih = keluar - masuk;

        $('#total_input').val(fmt.format(masuk));
        $('#total_output').val(fmt.format(keluar));
        $('#selisih')
            .val(fmt.format(selisih))
            .toggleClass('text-success', selisih > 0)
            .toggleClass('text-danger', selisih < 0);
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

    function tambahBaris(b) {
        var $baris = $(b.tpl.content.querySelector('tr').cloneNode(true));
        b.tbody.append($baris);
        aktifkanSelect2($baris);
    }

    bagian.forEach(function (b) {
        $(b.tombol).on('click', function () {
            tambahBaris(b);
            hitung();
        });

        b.tbody.on('click', '.btn-hapus-baris', function () {
            var $baris = $(this).closest('tr');
            try {
                // destroy hanya valid jika select2 aktif; kalau tidak, melempar error dan baris tidak terhapus
                $baris.find('select.select2-hidden-accessible').select2('destroy');
            } catch (err) {
                // diabaikan, baris tetap dihapus
            }
            $baris.remove();
            if (!b.tbody.find('tr').length) { tambahBaris(b); }
            hitung();
        });

        b.tbody.on('change', '.produk-select', function () {
            var opt = this.options[this.selectedIndex];
            if (opt && opt.value !== '') {
                $(this).closest('tr').find('.harga').val(opt.getAttribute(b.atribut) || 0);
            }
            hitung();
        });

        b.tbody.on('input', '.qty, .harga', hitung);
    });

    hitung();
});
</script>