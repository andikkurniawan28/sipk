<?php
$page_title = "Kartu Stok";
include 'config.php';
include 'header.php';

// Ambil daftar produk dan gudang untuk pilihan select2
$produkList = $conn->query("SELECT id, kode, nama, satuan FROM produk ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$gudangList = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

// Default tanggal: awal bulan ini dan akhir bulan ini
$defaultDari = date('Y-m-01');
$defaultSampai = date('Y-m-t');
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Kartu Stok</h6>
    </div>
    <div class="card-body">
        <form id="form-kartu-stok" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="produk_id">Produk</label>
                    <select class="form-control select2" id="produk_id" name="produk_id" required>
                        <option value="">-- Pilih Produk --</option>
                        <?php foreach ($produkList as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= htmlspecialchars($p['kode'] . ' - ' . $p['nama'] . ' (' . $p['satuan'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="gudang_id">Gudang</label>
                    <select class="form-control select2" id="gudang_id" name="gudang_id">
                        <option value="">-- Semua Gudang --</option>
                        <?php foreach ($gudangList as $g): ?>
                            <option value="<?= (int)$g['id'] ?>">
                                <?= htmlspecialchars($g['nama'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="dari">Dari Tanggal</label>
                    <input type="date" class="form-control" id="dari" name="dari" value="<?= $defaultDari ?>" required>
                </div>
                <div class="form-group col-md-2">
                    <label for="sampai">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="sampai" name="sampai" value="<?= $defaultSampai ?>" required>
                </div>
                <div class="form-group col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block" title="Tampilkan">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Area Hasil Laporan Kartu Stok (Buku Besar) -->
<div class="card shadow mb-4" id="card-hasil" style="display: none;">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary" id="judul-laporan">Rincian Kartu Stok</h6>
        <button type="button" class="btn btn-secondary btn-sm no-print" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak
        </button>
    </div>
    <div class="card-body">
        <div id="loading-spinner" class="text-center py-4" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <div class="table-responsive" id="tabel-container">
            <!-- Hasil tabel akan dimuat lewat AJAX di sini -->
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
window.addEventListener('load', function () {
    // Inisialisasi Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: '-- Pilih --',
        allowClear: true
    });

    $('#form-kartu-stok').on('submit', function (e) {
        e.preventDefault();

        var produkId = $('#produk_id').val();
        var gudangId = $('#gudang_id').val();
        var dari = $('#dari').val();
        var sampai = $('#sampai').val();

        if (!produkId) {
            alert('Silakan pilih produk terlebih dahulu.');
            return;
        }

        $('#card-hasil').show();
        $('#loading-spinner').show();
        $('#tabel-container').empty();

        $.ajax({
            url: 'kartu_stok_proses.php',
            type: 'GET',
            data: { produk_id: produkId, gudang_id: gudangId, dari: dari, sampai: sampai },
            success: function (response) {
                $('#loading-spinner').hide();
                $('#tabel-container').html(response);
            },
            error: function () {
                $('#loading-spinner').hide();
                $('#tabel-container').html('<div class="alert alert-danger">Gagal memuat data kartu stok.</div>');
            }
        });
    });
});
</script>