<?php
if (!isset($conn, $form_action, $label_tombol, $penerimaan, $detail)) {
    exit;
}

require_once 'penerimaan_helper.php';

$gudangList = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

if (!$detail) {
    $detail = [[]];
}

function penerimaan_baris_html(array $row = []): string
{
    $namaProduk = penerimaan_h($row['nama_produk'] ?? 'Pilih referensi dokumen terlebih dahulu');
    $satuan     = penerimaan_h($row['satuan'] ?? '');
    $prodId     = (string)($row['produk_id'] ?? '');
    $qty        = (string)($row['qty'] ?? '');

    $labelTampil = $satuan !== '' ? "$namaProduk ($satuan)" : $namaProduk;

    return '<tr>'
        . '<td>'
        . '<input type="hidden" name="produk_id[]" value="' . $prodId . '" class="produk-id-input">'
        . '<input type="text" class="form-control form-control-sm produk-nama-view" value="' . $labelTampil . '" readonly tabindex="-1">'
        . '</td>'
        . '<td><input type="number" name="qty[]" class="form-control form-control-sm text-right qty" min="0" step="0.01" value="' . penerimaan_h($qty) . '" required></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus baris"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}
?>

<form action="<?= penerimaan_h($form_action) ?>" method="post" autocomplete="off" id="form-penerimaan">
    <?php if (!empty($penerimaan['id'])): ?>
        <input type="hidden" name="id" value="<?= (int)$penerimaan['id'] ?>">
    <?php endif; ?>

    <!-- Informasi Utama Penerimaan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Data Penerimaan</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="kode">Kode</label>
                    <input type="text" class="form-control" id="kode" name="kode" maxlength="30" value="<?= penerimaan_h($penerimaan['kode'] ?? '') ?>" required readonly>
                </div>
                <div class="form-group col-md-3">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= penerimaan_h($penerimaan['tanggal'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="gudang_id">Gudang</label>
                    <select class="form-control select2" id="gudang_id" name="gudang_id" required>
                        <option></option>
                        <?php foreach ($gudangList as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" <?= (string)($penerimaan['gudang_id'] ?? '') === (string)$g['id'] ? 'selected' : '' ?>>
                                <?= penerimaan_h($g['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="sumber">Terima dari</label>
                    <select class="form-control" id="sumber" name="sumber">
                        <option value="panen" <?= ($penerimaan['sumber'] ?? '') === 'panen' ? 'selected' : '' ?>>Panen</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="referensi_id">Pilih Dokumen Faktur (Belum Diterima)</label>
                    <select class="form-control select2" id="referensi_id" name="referensi_id" required>
                        <option></option>
                        <!-- Diisi secara dinamis melalui AJAX berdasarkan sumber -->
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Item -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Item</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th style="width:180px">Qty Diterima</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-detail">
                        <?php foreach ($detail as $baris): ?>
                            <?= penerimaan_baris_html($baris) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <button type="submit" class="btn btn-primary mt-3">
                <i class="fas fa-save"></i> <?= penerimaan_h($label_tombol) ?>
            </button>
            <a href="penerimaan_index.php" class="btn btn-secondary mt-3">Batal</a>
        </div>
    </div>
</form>

<script>
window.addEventListener('load', function () {
    var $tbody = $('#tbody-detail');
    var selectedRefId = "<?= penerimaan_h($penerimaan['referensi_id'] ?? '') ?>";

    function muatReferensi(sumber, preselectId = '') {
        console.log("Memanggil referensi untuk sumber:", sumber);
        $.ajax({
            url: 'penerimaan_get_referensi.php',
            type: 'GET',
            data: { sumber: sumber, exclude_id: "<?= (int)($penerimaan['id'] ?? 0) ?>" },
            dataType: 'json',
            success: function (data) {
                console.log("Respon referensi diterima:", data);
                var $refSelect = $('#referensi_id');
                $refSelect.empty().append('<option></option>');
                data.forEach(function (item) {
                    var sel = (item.id == preselectId) ? 'selected' : '';
                    $refSelect.append('<option value="' + item.id + '" ' + sel + '>' + item.label + '</option>');
                });
                $refSelect.trigger('change');
            },
            error: function (xhr, status, error) {
                console.error("Gagal memuat referensi:", error);
            }
        });
    }

    function muatDetailReferensi(sumber, id) {
        if (!id) {
            $tbody.html('<tr><td colspan="3" class="text-center text-muted">Silakan pilih dokumen referensi terlebih dahulu.</td></tr>');
            return;
        }
        console.log("Memuat detail item untuk sumber:", sumber, "ID:", id);
        $.ajax({
            url: 'penerimaan_get_detail.php',
            type: 'GET',
            data: { sumber: sumber, id: id },
            dataType: 'json',
            success: function (data) {
                console.log("Respon detail item diterima:", data);
                $tbody.empty();
                if (data.length === 0) {
                    $tbody.html('<tr><td colspan="3" class="text-center text-muted">Tidak ada item pada dokumen ini.</td></tr>');
                    return;
                }
                data.forEach(function (row) {
                    var html = '<tr>' +
                        '<td>' +
                        '<input type="hidden" name="produk_id[]" value="' + row.produk_id + '">' +
                        '<input type="text" class="form-control form-control-sm" value="' + row.nama_produk + ' (' + row.satuan + ')" readonly tabindex="-1">' +
                        '</td>' +
                        '<td><input type="number" name="qty[]" class="form-control form-control-sm text-right qty" min="0" step="0.01" value="' + row.qty + '" required></td>' +
                        '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris"><i class="fas fa-times"></i></button></td>' +
                        '</tr>';
                    $tbody.append(html);
                });
            },
            error: function (xhr, status, error) {
                console.error("Gagal memuat detail item:", error);
            }
        });
    }

    // Inisialisasi awal Select2
    $('.select2').select2({ theme: 'bootstrap4', width: '100%', placeholder: '-- Pilih --', allowClear: true });

    // Event perubahan sumber (Panen / Pengolahan) -> Reset Select2 referensi dan tabel item
    $('#sumber').on('change', function () {
        var sumberBaru = $(this).val();
        
        // Reset pilihan dokumen referensi & kosongkan tabel item
        $('#referensi_id').val(null).trigger('change');
        $tbody.html('<tr><td colspan="3" class="text-center text-muted">Silakan pilih dokumen referensi terlebih dahulu.</td></tr>');
        
        muatReferensi(sumberBaru);
    });

    // Event perubahan dokumen referensi terpilih
    $('#referensi_id').on('change', function () {
        var refId = $(this).val();
        var sumber = $('#sumber').val();
        if (refId) {
            muatDetailReferensi(sumber, refId);
        } else {
            $tbody.html('<tr><td colspan="3" class="text-center text-muted">Silakan pilih dokumen referensi terlebih dahulu.</td></tr>');
        }
    });

    // Load pertama kali saat edit atau old input
    var initialFaktur = $('#sumber').val();
    muatReferensi(initialFaktur, selectedRefId);

    $tbody.on('click', '.btn-hapus-baris', function () {
        $(this).closest('tr').remove();
        if (!$tbody.find('tr').length) {
            $tbody.html('<tr><td colspan="3" class="text-center text-muted">Tidak ada item.</td></tr>');
        }
    });
});
</script>