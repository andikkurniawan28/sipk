<?php
$page_title = "Tambah Penyesuaian Stok";
include 'config.php';

function pb_kode_baru(mysqli $conn): string {
    $prefix = 'STA' . date('ymd');
    $stmt = $conn->prepare("SELECT kode FROM penyesuaian_stok WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;
    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

$tanggal_ini   = date('Y-m-d');
$kode_otomatis = pb_kode_baru($conn);

$gudangList = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$produkList = $conn->query("SELECT id, kode, nama, satuan FROM produk ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
// Akun persediaan yang bergerak lewat mutasi stok: 103.02 Bahan Baku, 103.04 Barang Jadi
$akunPersediaanList = $conn->query("
    SELECT id, kode, nama
    FROM akun
    WHERE kelompok = 'Aset' AND kode IN ('103.02', '103.04')
    ORDER BY kode ASC
")->fetch_all(MYSQLI_ASSOC);

function pb_baris_html(array $produkList): string {
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

<form action="penyesuaian_stok_simpan.php" method="post" autocomplete="off" id="form-penyesuaian">
    <!-- Informasi -->
    <div class="card shadow">
        <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-primary">Informasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Kode</label>
                    <input type="text" class="form-control form-control-sm" id="kode" name="kode" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-3">
                    <label>Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Gudang</label>
                    <select class="form-control form-control-sm select2" id="gudang_id" name="gudang_id" required>
                        <option></option>
                        <?php foreach ($gudangList as $g): ?>
                            <option value="<?= (int)$g['id'] ?>"><?= h($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Jenis</label>
                    <select class="form-control form-control-sm select2" id="jenis" name="jenis" required>
                        <option></option>
                        <option value="tambah">Tambah</option>
                        <option value="kurang">Kurang</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Akun Persediaan</label>
                    <select class="form-control form-control-sm select2" id="akun_persediaan_id" name="akun_persediaan_id" required>
                        <option></option>
                        <?php foreach ($akunPersediaanList as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= h($a['kode'] . ' - ' . $a['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-8">
                    <label>Keterangan</label>
                    <textarea class="form-control form-control-sm" id="keterangan" name="keterangan" rows="2" required></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail -->
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
                            <th style="width:160px">Qty</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <?= pb_baris_html($produkList) ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="mt-2 mb-3">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan</button>
        <a href="penyesuaian_stok_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<template id="tpl-baris"><?= pb_baris_html($produkList) ?></template>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
$(function () {
    var $tbody = $('#tbody-detail');
    var tpl = document.getElementById('tpl-baris');

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
});
</script>