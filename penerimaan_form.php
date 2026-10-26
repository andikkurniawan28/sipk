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
    if (empty($row['produk_id'])) {
        return '<tr><td colspan="3" class="text-center text-muted">Silakan pilih proposal terlebih dahulu.</td></tr>';
    }

    $label = penerimaan_h($row['nama_produk'] ?? '');
    if (!empty($row['satuan'])) {
        $label .= ' (' . penerimaan_h($row['satuan']) . ')';
    }

    return '<tr>'
        . '<td>'
        . '<input type="hidden" name="produk_id[]" value="' . (int)$row['produk_id'] . '">'
        . '<input type="text" class="form-control form-control-sm" value="' . $label . '" readonly tabindex="-1">'
        . '</td>'
        . '<td><input type="number" name="qty[]" class="form-control form-control-sm text-right qty" min="0.01" step="0.01" value="' . penerimaan_h($row['qty'] ?? '') . '" required></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus baris"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}
?>

<form action="<?= penerimaan_h($form_action) ?>" method="post" autocomplete="off" id="form-penerimaan">
    <?php if (!empty($penerimaan['id'])): ?>
        <input type="hidden" name="id" value="<?= (int)$penerimaan['id'] ?>">
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Data Penerimaan</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="kode">Kode</label>
                    <input type="text" class="form-control" id="kode" name="kode" maxlength="50" value="<?= penerimaan_h($penerimaan['kode'] ?? '') ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= penerimaan_h($penerimaan['tanggal'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group col-md-4">
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
            </div>

            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="proposal_id">Proposal</label>
                    <select class="form-control select2" id="proposal_id" name="proposal_id" required>
                        <option></option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Item</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th style="width:180px">Qty</th>
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
    var $tbody     = $('#tbody-detail');
    var $proposal  = $('#proposal_id');
    var preselect  = "<?= penerimaan_h($penerimaan['proposal_id'] ?? '') ?>";
    var excludeId  = <?= (int)($penerimaan['id'] ?? 0) ?>;
    var awal       = true;   // true selama pemuatan awal: jangan timpa item hasil edit / isian lama

    var barisKosong = '<tr><td colspan="3" class="text-center text-muted">Silakan pilih proposal terlebih dahulu.</td></tr>';

    $('.select2').select2({ theme: 'bootstrap4', width: '100%', placeholder: '-- Pilih --', allowClear: true });

    function buatBaris(row) {
        var $tr = $('<tr>');
        var $td1 = $('<td>');
        $('<input>', { type: 'hidden', name: 'produk_id[]', value: row.produk_id }).appendTo($td1);
        $('<input>', {
            type: 'text', 'class': 'form-control form-control-sm', readonly: true, tabindex: -1,
            value: row.nama_produk + ' (' + row.satuan + ')'
        }).appendTo($td1);

        var $td2 = $('<td>');
        $('<input>', {
            type: 'number', name: 'qty[]', 'class': 'form-control form-control-sm text-right qty',
            min: '0.01', max: row.qty, step: '0.01', value: row.qty, required: true
        }).appendTo($td2);

        var $td3 = $('<td class="text-center">').append(
            '<button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus baris"><i class="fas fa-times"></i></button>'
        );
        return $tr.append($td1, $td2, $td3);
    }

    function muatReferensi() {
        $.getJSON('penerimaan_get_referensi.php', { exclude_id: excludeId })
            .done(function (data) {
                $proposal.empty().append('<option></option>');
                data.forEach(function (item) {
                    $('<option>', { value: item.id, text: item.label }).appendTo($proposal);
                });
                if (preselect) {
                    $proposal.val(preselect);
                }
                $proposal.trigger('change');
                awal = false;
            })
            .fail(function () {
                awal = false;
                $tbody.html('<tr><td colspan="3" class="text-center text-danger">Gagal memuat daftar proposal.</td></tr>');
            });
    }

    function muatDetail(id) {
        $.getJSON('penerimaan_get_detail.php', { id: id })
            .done(function (data) {
                $tbody.empty();
                if (!data.length) {
                    $tbody.html('<tr><td colspan="3" class="text-center text-muted">Proposal ini tidak punya item.</td></tr>');
                    return;
                }
                data.forEach(function (row) { $tbody.append(buatBaris(row)); });
            })
            .fail(function () {
                $tbody.html('<tr><td colspan="3" class="text-center text-danger">Gagal memuat item proposal.</td></tr>');
            });
    }

    $proposal.on('change', function () {
        if (awal) { return; }           // pemuatan awal: pertahankan item yang sudah dirender server
        var id = $(this).val();
        if (id) {
            muatDetail(id);
        } else {
            $tbody.html(barisKosong);
        }
    });

    $tbody.on('click', '.btn-hapus-baris', function () {
        $(this).closest('tr').remove();
        if (!$tbody.find('tr').length) {
            $tbody.html(barisKosong);
        }
    });

    muatReferensi();
});
</script>