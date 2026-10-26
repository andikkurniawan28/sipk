<?php
$page_title = "Tambah Pembelian Langsung";
include 'config.php';

function pl_h($teks): string {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function pl_kode_baru(mysqli $conn): string {
    $prefix = 'PBL' . date('ymd');
    $stmt = $conn->prepare("SELECT kode FROM pembelian_langsung WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;
    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

$tanggal_ini = date('Y-m-d');
$kode_otomatis = pl_kode_baru($conn);

$pemasokList = $conn->query("SELECT id, kode, nama FROM pemasok ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$produkList = $conn->query("SELECT id, nama, satuan, harga_beli FROM produk ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

function pl_baris_html(array $produkList): string {
    $opsi = '<option></option>';
    foreach ($produkList as $p) {
        $opsi .= '<option value="' . (int)$p['id'] . '" data-harga="' . pl_h($p['harga_beli']) . '">' . pl_h($p['nama'] . ' (' . $p['satuan'] . ')') . '</option>';
    }
    return '<tr>'
        . '<td><select name="produk_id[]" class="form-control form-control-sm select2 produk-select" required>' . $opsi . '</select></td>'
        . '<td><input type="number" name="qty[]" class="form-control form-control-sm text-right qty" min="0" step="0.01" value="1" required></td>'
        . '<td><input type="number" name="harga[]" class="form-control form-control-sm text-right harga" min="0" step="0.01" value="0" required></td>'
        . '<td><input type="text" class="form-control form-control-sm text-right total-baris" value="0,00" readonly tabindex="-1"></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= pl_h($page_title) ?></h1>

<form action="pembelian_langsung_simpan.php" method="post" autocomplete="off" id="form-pembelian">
    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Informasi Pembelian</h6></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="kode">Kode</label>
                    <input type="text" class="form-control" id="kode" name="kode" value="<?= pl_h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= pl_h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="pemasok_id">Pemasok</label>
                    <select class="form-control select2" id="pemasok_id" name="pemasok_id" required>
                        <option></option>
                        <?php foreach ($pemasokList as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= pl_h($p['kode'] . ' - ' . $p['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Detail Produk</h6>
            <button type="button" class="btn btn-success btn-sm" id="btn-tambah-baris"><i class="fas fa-plus"></i> Tambah Item</button>
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
                        <?= pl_baris_html($produkList) ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Ringkasan & Biaya</h6></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4"><label>Subtotal</label><input type="text" class="form-control text-right" id="subtotal" value="0,00" readonly></div>
                <div class="form-group col-md-4"><label>Pajak</label><input type="number" class="form-control text-right hitung-input" id="pajak" name="pajak" min="0" step="0.01" value="0"></div>
                <div class="form-group col-md-4"><label>Ongkir</label><input type="number" class="form-control text-right hitung-input" id="ongkir" name="ongkir" min="0" step="0.01" value="0"></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4"><label>Biaya Lain</label><input type="number" class="form-control text-right hitung-input" id="biaya" name="biaya" min="0" step="0.01" value="0"></div>
                <div class="form-group col-md-4"><label>Diskon</label><input type="number" class="form-control text-right hitung-input" id="diskon" name="diskon" min="0" step="0.01" value="0"></div>
                <div class="form-group col-md-4"><label>Grand Total</label><input type="text" class="form-control text-right font-weight-bold text-primary" id="grand_total" value="0,00" readonly></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save"></i> Simpan</button>
            <a href="pembelian_langsung_index.php" class="btn btn-secondary mt-3">Batal</a>
        </div>
    </div>
</form>

<template id="tpl-baris"><?= pl_baris_html($produkList) ?></template>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
window.addEventListener('load', function () {
    var $tbody = $('#tbody-detail');
    var tpl = document.getElementById('tpl-baris');
    var fmt = new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }

    function hitung() {
        var sub = 0;
        $tbody.find('tr').each(function () {
            var t = num($(this).find('.qty').val()) * num($(this).find('.harga').val());
            $(this).find('.total-baris').val(fmt.format(t));
            sub += t;
        });
        var grand = sub + num($('#pajak').val()) + num($('#ongkir').val()) + num($('#biaya').val()) - num($('#diskon').val());
        $('#subtotal').val(fmt.format(sub));
        $('#grand_total').val(fmt.format(grand));
    }

    function aktifkanSelect2($baris) {
        $baris.find('.select2').select2({ theme: 'bootstrap4', width: '100%', placeholder: '-- Pilih --', allowClear: true });
    }

    $tbody.find('tr').each(function() { aktifkanSelect2($(this)); });
    $('#pemasok_id').select2({ theme: 'bootstrap4', width: '100%', placeholder: '-- Pilih Pemasok --', allowClear: true });

    $('#btn-tambah-baris').on('click', function () {
        var $baris = $(tpl.content.querySelector('tr').cloneNode(true));
        $tbody.append($baris);
        aktifkanSelect2($baris);
        hitung();
    });

    $tbody.on('click', '.btn-hapus-baris', function () {
        var $baris = $(this).closest('tr');
        var $sel = $baris.find('select.produk-select');
        if ($sel.hasClass('select2-hidden-accessible')) { $sel.select2('destroy'); }
        $baris.remove();
        if (!$tbody.find('tr').length) { $('#btn-tambah-baris').click(); }
        hitung();
    });

    $tbody.on('change', '.produk-select', function () {
        var opt = this.options[this.selectedIndex];
        if (opt && opt.value !== '') {
            $(this).closest('tr').find('.harga').val(opt.getAttribute('data-harga') || 0);
        }
        hitung();
    });

    $tbody.on('input', '.qty, .harga', hitung);
    $('.hitung-input').on('input', hitung);
    hitung();
});
</script>