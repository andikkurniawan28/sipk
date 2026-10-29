<?php
$page_title = "Kartu Piutang";
include 'config.php';
include 'header.php';

// Ambil daftar pelanggan untuk pilihan select2
$pelangganList = $conn->query("SELECT id, nama FROM pelanggan ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

// Default tanggal: awal bulan ini dan akhir bulan ini
$defaultDari = date('Y-m-01');
$defaultSampai = date('Y-m-t');
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter</h6>
    </div>
    <div class="card-body">
        <form id="form-kartu-piutang" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-5">
                    <label>Pelanggan</label>
                    <select class="form-control select2" id="pelanggan_id" name="pelanggan_id" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($pelangganList as $plg): ?>
                            <option value="<?= (int)$plg['id'] ?>"><?= h($plg['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Dari</label>
                    <input type="date" class="form-control" id="dari" name="dari" value="<?= $defaultDari ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Sampai</label>
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

<!-- Area Hasil Laporan Kartu Piutang -->
<div class="card shadow mb-4" id="card-hasil" style="display: none;">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary" id="judul-laporan">Rincian</h6>
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
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: '-- Pilih --',
        allowClear: true
    });

    $('#form-kartu-piutang').on('submit', function (e) {
        e.preventDefault();

        var pelangganId = $('#pelanggan_id').val();
        var dari = $('#dari').val();
        var sampai = $('#sampai').val();

        if (!pelangganId) {
            alert('Silakan pilih pelanggan terlebih dahulu.');
            return;
        }

        $('#card-hasil').show();
        $('#loading-spinner').show();
        $('#tabel-container').empty();

        $.ajax({
            url: 'kartu_piutang_proses.php',
            type: 'GET',
            data: { pelanggan_id: pelangganId, dari: dari, sampai: sampai },
            success: function (response) {
                $('#loading-spinner').hide();
                $('#tabel-container').html(response);
            },
            error: function () {
                $('#loading-spinner').hide();
                $('#tabel-container').html('<div class="alert alert-danger">Gagal memuat data kartu piutang.</div>');
            }
        });
    });
});
</script>