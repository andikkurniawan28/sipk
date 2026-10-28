<?php
$page_title = "Kartu Hutang";
include 'config.php';
include 'header.php';

// Ambil daftar pemasok untuk pilihan select2
$pemasokList = $conn->query("SELECT id, kode, nama FROM pemasok ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

// Default tanggal: awal bulan ini dan akhir bulan ini
$defaultDari = date('Y-m-01');
$defaultSampai = date('Y-m-t');
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Kartu Hutang</h6>
    </div>
    <div class="card-body">
        <form id="form-kartu-hutang" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="pemasok_id">Pemasok</label>
                    <select class="form-control select2" id="pemasok_id" name="pemasok_id" required>
                        <option value="">-- Pilih Pemasok --</option>
                        <?php foreach ($pemasokList as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= htmlspecialchars($p['kode'] . ' - ' . $p['nama'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="dari">Dari Tanggal</label>
                    <input type="date" class="form-control" id="dari" name="dari" value="<?= $defaultDari ?>" required>
                </div>
                <div class="form-group col-md-3">
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

<!-- Area Hasil Laporan Kartu Hutang -->
<div class="card shadow mb-4" id="card-hasil" style="display: none;">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary" id="judul-laporan">Rincian Kartu Hutang</h6>
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

    $('#form-kartu-hutang').on('submit', function (e) {
        e.preventDefault();

        var pemasokId = $('#pemasok_id').val();
        var dari = $('#dari').val();
        var sampai = $('#sampai').val();

        if (!pemasokId) {
            alert('Silakan pilih pemasok terlebih dahulu.');
            return;
        }

        $('#card-hasil').show();
        $('#loading-spinner').show();
        $('#tabel-container').empty();

        $.ajax({
            url: 'kartu_hutang_proses.php',
            type: 'GET',
            data: { pemasok_id: pemasokId, dari: dari, sampai: sampai },
            success: function (response) {
                $('#loading-spinner').hide();
                $('#tabel-container').html(response);
            },
            error: function () {
                $('#loading-spinner').hide();
                $('#tabel-container').html('<div class="alert alert-danger">Gagal memuat data kartu hutang.</div>');
            }
        });
    });
});
</script>