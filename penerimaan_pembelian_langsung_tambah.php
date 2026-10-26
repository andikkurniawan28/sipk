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

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<form action="penerimaan_pembelian_langsung_simpan.php" method="post" autocomplete="off" id="form-penerimaan">
    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Informasi Penerimaan</h6></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="kode">Kode</label>
                    <input type="text" class="form-control" id="kode" name="kode" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="gudang_id">Gudang</label>
                    <select class="form-control select2" id="gudang_id" name="gudang_id" required>
                        <option></option>
                        <?php foreach ($gudangList as $g): ?>
                            <option value="<?= (int)$g['id'] ?>"><?= h($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="pembelian_langsung_id">Referensi Pembelian Langsung</label>
                    <select class="form-control select2" id="pembelian_langsung_id" name="pembelian_langsung_id" required>
                        <option></option>
                        <?php foreach ($pembelianList as $pl): ?>
                            <option value="<?= (int)$pl['id'] ?>"><?= h($pl['kode'] . ' (Tanggal: ' . tanggal_indo($pl['tanggal']) . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Rincian Barang dari Pembelian</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Produk</th>
                            <th class="text-right" style="width:200px">Qty</th>
                            <th class="text-right d-none" style="width:180px">Harga</th>
                            <th class="text-right d-none" style="width:180px">Total</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <tr>
                            <td colspan="3" class="text-center text-muted">Pilih referensi pembelian langsung terlebih dahulu.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary mt-3" id="btn-simpan" disabled><i class="fas fa-save"></i> Simpan</button>
            <a href="penerimaan_pembelian_langsung_index.php" class="btn btn-secondary mt-3">Batal</a>
        </div>
    </div>
</form>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
window.addEventListener('load', function () {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%', placeholder: '-- Pilih --', allowClear: true });

    function hitungTotal() {
        // Kalkulasi di belakang layar tetap berjalan agar nilai total[] terisi dengan benar saat disimpan
        $('#tbody-detail tr').each(function () {
            var $row = $(this);
            var qty = parseFloat($row.find('.qty-input').val()) || 0;
            var harga = parseFloat($row.find('.harga-input').val()) || 0;
            var total = qty * harga;
            $row.find('.total-input').val(total);
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
                    var fmt = new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
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
                                    <input type="number" name="qty[]" class="form-control form-control-sm text-right qty-input" value="${item.qty}" min="0" step="0.01" required>
                                </td>
                                <td class="text-right d-none">${fmt.format(item.harga)}</td>
                                <td class="text-right d-none">${fmt.format(item.total)}</td>
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