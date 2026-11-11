<?php
$page_title = "Tambah Pengiriman Produk";
include 'config.php';
require_once 'pengiriman_produk_core.php';

function do_kode_baru(mysqli $conn): string {
    $prefix = 'DO' . date('ymd');
    $stmt = $conn->prepare("SELECT kode FROM pengiriman_produk WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;
    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

$tanggal_ini   = date('Y-m-d');
$kode_otomatis = do_kode_baru($conn);

// Hanya penjualan yang masih punya sisa qty belum dikirim
$penjualanList = ambilPenjualanBelumLengkapDikirim($conn);
$gudangList    = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

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

<form action="pengiriman_produk_simpan.php" method="post" autocomplete="off" id="form-pengiriman">
    <div class="card shadow">
        <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Informasi</h6></div>
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
                    <select class="form-control form-control-sm select2" id="gudang_id" name="gudang_id" required>
                        <option></option>
                        <?php foreach ($gudangList as $g): ?>
                            <option value="<?= (int)$g['id'] ?>"><?= h($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Faktur Penjualan</label>
                    <select class="form-control form-control-sm select2" id="penjualan_id" name="penjualan_id" required>
                        <option></option>
                        <?php foreach ($penjualanList as $pn): ?>
                            <option value="<?= (int)$pn['id'] ?>">
                                <?= h($pn['kode'] . ' - ' . $pn['nama_pelanggan'] . ' (' . tanggal_indo($pn['tanggal']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Detail</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive table-compact">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Produk</th>
                            <th class="text-right" style="width:100px">Stok</th>
                            <th class="text-right" style="width:100px">Dipesan</th>
                            <th class="text-right" style="width:100px">Terkirim</th>
                            <th class="text-right" style="width:100px">Sisa</th>
                            <th class="text-right" style="width:150px">Dikirim</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <tr><td colspan="7" class="text-center text-muted">Pilih gudang dan faktur penjualan terlebih dahulu.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-2 mb-3">
        <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan" disabled><i class="fas fa-save"></i> Simpan</button>
        <a href="pengiriman_produk_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
$(function () {
    var fmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    var xhrSisa = null;

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    function kosong(pesan, kelas) {
        $('#tbody-detail').html('<tr><td colspan="7" class="text-center ' + (kelas || 'text-muted') + '">' + pesan + '</td></tr>');
        $('#btn-simpan').prop('disabled', true);
    }

    function muat() {
        var id = $('#penjualan_id').val();
        var gudang = $('#gudang_id').val();

        if (xhrSisa) { xhrSisa.abort(); xhrSisa = null; }
        if (!id || !gudang) { kosong('Pilih gudang dan faktur penjualan terlebih dahulu.'); return; }
        kosong('Memuat data...', '');

        xhrSisa = $.ajax({
            url: 'pengiriman_produk_get_sisa.php',
            type: 'GET',
            data: { id: id, gudang_id: gudang },
            dataType: 'json'
        })
        .done(function (res) {
            if (!(res.success && res.data.length > 0)) {
                kosong('Semua produk pada faktur ini sudah dikirim lengkap.');
                return;
            }
            var rows = '';
            res.data.forEach(function (it, i) {
                var maks = Math.max(0, Math.min(it.sisa, it.stok == null ? it.sisa : it.stok));
                rows += '<tr>'
                    + '<td class="text-center align-middle">' + (i + 1) + '</td>'
                    + '<td class="align-middle">' + esc(it.kode + ' - ' + it.nama_produk + ' (' + it.satuan + ')')
                    +   '<input type="hidden" name="produk_id[]" value="' + parseInt(it.produk_id, 10) + '">'
                    + '</td>'
                    + '<td class="text-right align-middle ' + (it.stok < it.sisa ? 'text-danger' : '') + '">' + fmt.format(it.stok) + '</td>'
                    + '<td class="text-right align-middle">' + fmt.format(it.pesan) + '</td>'
                    + '<td class="text-right align-middle">' + fmt.format(it.kirim) + '</td>'
                    + '<td class="text-right align-middle font-weight-bold">' + fmt.format(it.sisa) + '</td>'
                    + '<td><input type="number" name="qty[]" class="form-control form-control-sm text-right qty-input"'
                    +   ' value="' + maks + '" min="0" max="' + it.sisa + '" step="any" required></td>'
                    + '</tr>';
            });
            $('#tbody-detail').html(rows);
            $('#btn-simpan').prop('disabled', false);
        })
        .fail(function (xhr, status) {
            if (status === 'abort') return;
            kosong('Gagal memuat detail produk.', 'text-danger');
        })
        .always(function () { xhrSisa = null; });
    }

    $('#penjualan_id, #gudang_id').on('change', muat);

    $('#form-pengiriman').on('submit', function (e) {
        var ada = false;
        $('.qty-input').each(function () {
            if ((parseFloat($(this).val()) || 0) > 0) ada = true;
        });
        if (!ada) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Qty belum diisi', text: 'Isi qty kirim minimal untuk satu produk.' });
        }
    });
});
</script>