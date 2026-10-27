<?php
$page_title = "Tambah Penerimaan Pembelian Langsung";
include 'config.php';

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

$tanggal_ini = date('Y-m-d');
$kode_otomatis = ppl_kode_baru($conn);

$pembelianList = $conn->query("
    SELECT id, kode, tanggal 
    FROM pembelian_langsung 
    WHERE id NOT IN (SELECT pembelian_langsung_id FROM penerimaan_pembelian_langsung)
    ORDER BY tanggal DESC
")->fetch_all(MYSQLI_ASSOC);
$gudangList = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

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
                    <label>Referensi</label>
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
                            <th class="text-right" style="width:200px">Qty</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <tr>
                            <td colspan="3" class="text-center text-muted">Pilih referensi pembelian langsung terlebih dahulu.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Valuasi -->
    <div class="card shadow">
        <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Valuasi</h6></div>
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="small text-muted">Pastikan jumlah kuantitas barang yang diterima sudah sesuai dengan fisik gudang.</span>
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
    function hitungTotal() {
        $('#tbody-detail tr').each(function () {
            var $row = $(this);
            var qty = parseFloat($row.find('.qty-input').val()) || 0;
            var harga = parseFloat($row.find('.harga-input').val()) || 0;
            var total = qty * harga;
            
            var $totalInput = $row.find('.total-input');
            $totalInput.val(total.toFixed(0));
            $totalInput.attr('value', total.toFixed(0));
        });
    }

    $('#pembelian_langsung_id').on('change', function () {
        var id = $(this).val();
        if (!id) {
            $('#tbody-detail').html('<tr><td colspan="3" class="text-center text-muted">Pilih referensi pembelian langsung terlebih dahulu.</td></tr>');
            $('#btn-simpan').prop('disabled', true);
            return;
        }

        $('#tbody-detail').html('<tr><td colspan="3" class="text-center">Memuat data...</td></tr>');

        $.ajax({
            url: 'pembelian_langsung_get_detail.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data.length > 0) {
                    let rows = '';
                    res.data.forEach((item, index) => {
                        rows += `
                            <tr>
                                <td class="text-center align-middle">${index + 1}</td>
                                <td class="align-middle">
                                    ${item.nama_produk} (${item.satuan})
                                    <input type="hidden" name="produk_id[]" value="${item.produk_id}">
                                    <input type="hidden" class="harga-input" value="${item.harga}">
                                    <input type="hidden" name="total[]" class="total-input" value="${item.total}">
                                </td>
                                <td class="text-right">
                                    <input type="number" name="qty[]" class="form-control form-control-sm text-right qty-input" value="${item.qty}" min="0" step="any" required>
                                </td>
                            </tr>
                        `;
                    });
                    $('#tbody-detail').html(rows);
                    hitungTotal();
                    $('#btn-simpan').prop('disabled', false);
                } else {
                    $('#tbody-detail').html('<tr><td colspan="3" class="text-center text-muted">Detail produk tidak ditemukan.</td></tr>');
                    $('#btn-simpan').prop('disabled', true);
                }
            },
            error: function () {
                $('#tbody-detail').html('<tr><td colspan="3" class="text-center text-danger">Gagal memuat detail produk.</td></tr>');
                $('#btn-simpan').prop('disabled', true);
            }
        });
    });

    $('#tbody-detail').on('input', '.qty-input', function () {
        hitungTotal();
    });
});
</script>