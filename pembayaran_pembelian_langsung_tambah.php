<?php
$page_title = "Tambah Pembayaran Pembelian Langsung";
include 'config.php';

function ppbl_kode_baru(mysqli $conn): string {
    $prefix = 'RBL' . date('ymd');
    $stmt = $conn->prepare("SELECT kode FROM pembayaran_pembelian_langsung WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;
    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

$tanggal_ini = date('Y-m-d');
$kode_otomatis = ppbl_kode_baru($conn);

$pembelianList = $conn->query("
    SELECT id, kode, tanggal, grand_total 
    FROM pembelian_langsung 
    WHERE id IN (SELECT pembelian_langsung_id FROM penerimaan_pembelian_langsung)
      AND id NOT IN (SELECT pembelian_langsung_id FROM pembayaran_pembelian_langsung)
    ORDER BY tanggal DESC
")->fetch_all(MYSQLI_ASSOC);

// Ambil akun kas/bank (atau semua akun sesuai sistem Anda)
$akunList = $conn->query("SELECT id, kode, nama FROM akun ORDER BY kode ASC")->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<form action="pembayaran_pembelian_langsung_simpan.php" method="post" autocomplete="off" id="form-pembayaran">
    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Informasi Pembayaran</h6></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="kode">Kode Pembayaran</label>
                    <input type="text" class="form-control" id="kode" name="kode" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label for="tanggal">Tanggal Pembayaran</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="akun_id">Sumber Dana</label>
                    <select class="form-control select2" id="akun_id" name="akun_id" required>
                        <option></option>
                        <?php foreach ($akunList as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= h($a['kode'] . ' - ' . $a['nama']) ?></option>
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
                            <option value="<?= (int)$pl['id'] ?>" data-total="<?= (float)$pl['grand_total'] ?>">
                                <?= h($pl['kode'] . ' (Tanggal: ' . tanggal_indo($pl['tanggal']) . ' - Total: ' . rp($pl['grand_total']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="total">Jumlah</label>
                    <input type="number" step="0.01" class="form-control" id="total" name="total" required readonly>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary" id="btn-simpan" disabled><i class="fas fa-save"></i> Simpan</button>
    <a href="pembayaran_pembelian_langsung_index.php" class="btn btn-secondary">Batal</a>
</form>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
window.addEventListener('load', function () {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%', placeholder: '-- Pilih --', allowClear: true });

    $('#pembelian_langsung_id').on('change', function () {
        var selectedOption = $(this).find(':selected');
        var total = selectedOption.data('total') || 0;
        
        if (total > 0) {
            $('#total').val(total);
            $('#btn-simpan').prop('disabled', false);
        } else {
            $('#total').val('');
            $('#btn-simpan').prop('disabled', true);
        }
    });
});
</script>