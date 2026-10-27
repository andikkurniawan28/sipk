<?php
$page_title = "Tambah Pembayaran Pembelian Langsung";
include 'config.php';

function ppbl_kode_baru(mysqli $conn): string {
    $prefix = 'BYR' . date('ymd');
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

// Ambil penerimaan pembelian yang BELUM pernah dibayar
$penerimaanList = $conn->query("
    SELECT ppl.id, ppl.kode AS kode_penerimaan, ppl.tanggal, pl.kode AS kode_pembelian
    FROM penerimaan_pembelian_langsung ppl
    JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
    WHERE ppl.id NOT IN (SELECT penerimaan_pembelian_langsung_id FROM pembayaran_pembelian_langsung)
    ORDER BY ppl.tanggal DESC
")->fetch_all(MYSQLI_ASSOC);

// Ambil akun kas/bank
$akunList = $conn->query("
    SELECT id, kode, nama 
    FROM akun 
    WHERE kode LIKE '101%' OR nama LIKE '%Kas%' OR nama LIKE '%Bank%' OR nama LIKE '%Rekening%' 
    ORDER BY kode ASC
")->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<style>
    /* Layout Kompak 1 Layar / Tanpa Scroll Berlebih */
    body { overflow-x: hidden; }
    .container-fluid { padding-left: 1rem; padding-right: 1rem; }
    .card { margin-bottom: 0.75rem !important; }
    .card-body { padding: 0.75rem 1rem !important; }
    .form-group { margin-bottom: 0.5rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="h3 mb-0 text-gray-800"><?= h($page_title) ?></h1>
</div>

<form action="pembayaran_pembelian_langsung_simpan.php" method="post" autocomplete="off" id="form-pembayaran">
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
                    <label>Akun</label>
                    <select class="form-control form-control-sm select2" id="akun_id" name="akun_id" required>
                        <option></option>
                        <?php foreach ($akunList as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= h($a['kode'] . ' - ' . $a['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label>Referensi</label>
                    <select class="form-control form-control-sm select2" id="penerimaan_pembelian_langsung_id" name="penerimaan_pembelian_langsung_id" required>
                        <option></option>
                        <?php foreach ($penerimaanList as $ppl): ?>
                            <option value="<?= (int)$ppl['id'] ?>">
                                <?= h($ppl['kode_penerimaan'] . ' (Ref. Pembelian: ' . $ppl['kode_pembelian'] . ' - Tgl: ' . tanggal_indo($ppl['tanggal']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Valuasi -->
    <div class="card shadow">
        <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Valuasi</h6></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Total</label>
                    <input type="number" step="any" class="form-control form-control-sm text-right font-weight-bold text-primary" id="total" name="total" required readonly>
                </div>
            </div>
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="mt-2 mb-3">
        <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan" disabled><i class="fas fa-save"></i> Simpan</button>
        <a href="pembayaran_pembelian_langsung_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
$(function () {
    $('#penerimaan_pembelian_langsung_id').on('change', function () {
        var id = $(this).val();
        
        if (!id) {
            $('#total').val('');
            $('#btn-simpan').prop('disabled', true);
            return;
        }

        $('#total').val('Memuat...');

        $.ajax({
            url: 'penerimaan_pembelian_langsung_get_total.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.total > 0) {
                    $('#total').val(res.total);
                    $('#btn-simpan').prop('disabled', false);
                } else {
                    $('#total').val('0');
                    $('#btn-simpan').prop('disabled', true);
                }
            },
            error: function () {
                $('#total').val('');
                $('#btn-simpan').prop('disabled', true);
                alert('Gagal menghitung total pembayaran.');
            }
        });
    });
});
</script>