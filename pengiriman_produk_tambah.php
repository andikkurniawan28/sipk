<?php
$page_title = "Tambah Pengiriman Produk";
include 'config.php';

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

$tanggal_ini = date('Y-m-d');
$kode_otomatis = do_kode_baru($conn);

// Ambil daftar penjualan yang belum dibuatkan pengiriman (atau semua penjualan)
$penjualanList = $conn->query("
    SELECT pn.id, pn.kode, pn.tanggal, plg.nama AS nama_pelanggan 
    FROM penjualan pn 
    JOIN pelanggan plg ON plg.id = pn.pelanggan_id 
    ORDER BY pn.id DESC
")->fetch_all(MYSQLI_ASSOC);

$gudangList = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<form action="pengiriman_produk_simpan.php" method="post" autocomplete="off">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Informasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Kode</label>
                    <input type="text" class="form-control" name="kode" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label>Tanggal</label>
                    <input type="date" class="form-control" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Faktur Penjualan</label>
                    <select class="form-control select2" id="penjualan_id" name="penjualan_id" required>
                        <option value="">-- Pilih Faktur --</option>
                        <?php foreach ($penjualanList as $pn): ?>
                            <option value="<?= (int)$pn['id'] ?>">
                                <?= h($pn['kode'] . ' - ' . $pn['nama_pelanggan'] . ' (' . tanggal_indo($pn['tanggal']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Gudang</label>
                    <select class="form-control select2" name="gudang_id" required>
                        <option value="">-- Pilih Gudang --</option>
                        <?php foreach ($gudangList as $g): ?>
                            <option value="<?= (int)$g['id'] ?>"><?= h($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Area Detail Produk dari Faktur (Dimuat via AJAX) -->
    <div class="card shadow mb-4" id="card-detail" style="display: none;">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Detail</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive" id="detail-container">
                <!-- Tabel detail produk akan muncul di sini -->
            </div>
        </div>
    </div>

    <div class="mb-4">
        <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan" style="display: none;"><i class="fas fa-save"></i> Simpan</button>
        <a href="pengiriman_produk_index.php" class="btn btn-secondary btn-sm">Batal</a>
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
            $('#card-detail').hide();
            $('#btn-simpan').hide();
            return;
        }

        $.ajax({
            url: 'get_penjualan_detail.php',
            type: 'GET',
            data: { penjualan_id: penjualanId },
            success: function (res) {
                $('#detail-container').html(res);
                $('#card-detail').show();
                $('#btn-simpan').show();
            },
            error: function () {
                alert('Gagal memuat rincian produk penjualan.');
            }
        });
    });
});
</script>