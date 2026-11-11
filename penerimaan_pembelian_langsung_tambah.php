<?php
$page_title = "Tambah Penerimaan Pembelian Langsung";
include 'config.php';
require_once 'penerimaan_pembelian_langsung_core.php';

function ppl_kode_baru(mysqli $conn): string {
    $prefix = 'PPL' . date('ymd');
    $stmt = $conn->prepare("SELECT kode FROM penerimaan_pembelian_langsung WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;
    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

$tanggal_ini   = date('Y-m-d');
$kode_otomatis = ppl_kode_baru($conn);

// Hanya faktur yang masih punya sisa qty belum diterima
$pembelianList = ambilPembelianBelumLengkapDiterima($conn);
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

<form action="penerimaan_pembelian_langsung_simpan.php" method="post" autocomplete="off" id="form-penerimaan">
    <!-- Informasi -->
    <div class="card shadow">
        <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Informasi</h6></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Kode</label>
                    <input type="text" class="form-control form-control-sm" id="kode" name="kode" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label>Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Gudang</label>
                    <select class="form-control form-control-sm select2" id="gudang_id" name="gudang_id" required>
                        <option></option>
                        <?php foreach ($gudangList as $g): ?>
                            <option value="<?= (int)$g['id'] ?>"><?= h($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label>Faktur Pembelian</label>
                    <select class="form-control form-control-sm select2" id="pembelian_langsung_id" name="pembelian_langsung_id" required>
                        <option></option>
                        <?php foreach ($pembelianList as $pl): ?>
                            <option value="<?= (int)$pl['id'] ?>"><?= h($pl['kode'] . ' (Tanggal: ' . tanggal_indo($pl['tanggal']) . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail -->
    <div class="card shadow">
        <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Detail</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive table-compact">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Produk</th>
                            <th class="text-right" style="width:110px">Dipesan</th>
                            <th class="text-right" style="width:110px">Sampai</th>
                            <th class="text-right" style="width:110px">Sisa</th>
                            <th class="text-right" style="width:160px">Diterima</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <tr><td colspan="6" class="text-center text-muted">Pilih faktur pembelian terlebih dahulu.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="card shadow">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="small text-muted">Pastikan qty yang diterima sesuai fisik gudang. Qty tidak boleh melebihi sisa pesanan.</span>
                <div>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan" disabled><i class="fas fa-save"></i> Simpan</button>
                    <a href="penerimaan_pembelian_langsung_index.php" class="btn btn-secondary btn-sm">Batal</a>
                </div>
            </div>
        </div>
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
        $('#tbody-detail').html('<tr><td colspan="6" class="text-center ' + (kelas || 'text-muted') + '">' + pesan + '</td></tr>');
        $('#btn-simpan').prop('disabled', true);
    }

    $('#pembelian_langsung_id').on('change', function () {
        var id = $(this).val();
        if (xhrSisa) { xhrSisa.abort(); xhrSisa = null; }

        if (!id) { kosong('Pilih faktur pembelian terlebih dahulu.'); return; }
        kosong('Memuat data...', '');

        xhrSisa = $.ajax({
            url: 'penerimaan_pembelian_langsung_get_sisa.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json'
        })
        .done(function (res) {
            if (!(res.success && res.data.length > 0)) {
                kosong('Semua produk pada faktur ini sudah diterima lengkap.');
                return;
            }
            var rows = '';
            res.data.forEach(function (it, i) {
                rows += '<tr>'
                    + '<td class="text-center align-middle">' + (i + 1) + '</td>'
                    + '<td class="align-middle">' + esc(it.kode + ' - ' + it.nama_produk + ' (' + it.satuan + ')')
                    +   '<input type="hidden" name="produk_id[]" value="' + parseInt(it.produk_id, 10) + '">'
                    + '</td>'
                    + '<td class="text-right align-middle">' + fmt.format(it.pesan) + '</td>'
                    + '<td class="text-right align-middle">' + fmt.format(it.terima) + '</td>'
                    + '<td class="text-right align-middle font-weight-bold">' + fmt.format(it.sisa) + '</td>'
                    + '<td><input type="number" name="qty[]" class="form-control form-control-sm text-right qty-input"'
                    +   ' value="' + it.sisa + '" min="0" max="' + it.sisa + '" step="any" required></td>'
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
    });

    // Pengaman sebelum kirim: minimal satu qty > 0
    $('#form-penerimaan').on('submit', function (e) {
        var ada = false;
        $('.qty-input').each(function () {
            if ((parseFloat($(this).val()) || 0) > 0) ada = true;
        });
        if (!ada) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Qty belum diisi', text: 'Isi qty diterima minimal untuk satu produk.' });
        }
    });
});
</script>