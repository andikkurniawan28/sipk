<?php
$page_title = "Tambah Pembayaran Penjualan";
include 'config.php';

function bayar_kode_baru(mysqli $conn): string {
    $prefix = 'PLS' . date('ymd');
    $stmt = $conn->prepare("SELECT kode FROM pembayaran_penjualan WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;
    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

$tanggal_ini = date('Y-m-d');
$kode_otomatis = bayar_kode_baru($conn);

$penjualanList = $conn->query("
    SELECT pn.id, pn.kode, pn.tanggal, pn.grand_total, plg.nama AS nama_pelanggan 
    FROM penjualan pn 
    JOIN pelanggan plg ON plg.id = pn.pelanggan_id 
    ORDER BY pn.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Akun Kas/Bank (kelompok Aset)
$akunList = $conn->query("SELECT id, kode, nama FROM akun WHERE kelompok = 'Aset' AND (nama LIKE '%Kas%' OR nama LIKE '%Bank%') ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<form action="pembayaran_penjualan_simpan.php" method="post" autocomplete="off">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Informasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Kode</label>
                    <input type="text" class="form-control" name="kode" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-3">
                    <label>Tanggal</label>
                    <input type="date" class="form-control" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Faktur</label>
                    <select class="form-control select2" id="penjualan_id" name="penjualan_id" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($penjualanList as $pn): ?>
                            <option value="<?= (int)$pn['id'] ?>">
                                <?= h($pn['kode'] . ' - ' . $pn['nama_pelanggan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Akun</label>
                    <select class="form-control select2" name="akun_id" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($akunList as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= h($a['kode'] . ' - ' . $a['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Sisa Tagihan -->
    <div class="card shadow mb-4" id="card-info" style="display: none;">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Status</h6>
        </div>
        <div class="card-body" id="info-container">
            <!-- Info total tagihan, sudah dibayar, sisa tagihan -->
        </div>
    </div>

    <div class="card shadow mb-4" id="card-nominal" style="display: none;">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Nominal</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Jumlah</label>
                    <input type="number" class="form-control text-right" name="total" id="total" min="1" step="any" required>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan" style="display: none;"><i class="fas fa-save"></i> Simpan</button>
        <a href="pembayaran_penjualan_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
window.addEventListener('load', function () {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    $('#penjualan_id').on('change', function () {
        var penjualanId = $(this).val();
        if (!penjualanId) {
            $('#card-info').hide();
            $('#card-nominal').hide();
            $('#btn-simpan').hide();
            return;
        }

        $.ajax({
            url: 'get_piutang_status.php',
            type: 'GET',
            data: { penjualan_id: penjualanId },
            success: function (res) {
                $('#info-container').html(res);
                $('#card-info').show();
                $('#card-nominal').show();
                $('#btn-simpan').show();
            }
        });
    });
});
</script>