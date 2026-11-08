<?php
$page_title = "Laporan Arus Kas";
include 'config.php';
include 'header.php';

// Default bulan dan tahun saat ini
$defaultBulan = date('m');
$defaultTahun = date('Y');

$listBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter</h6>
    </div>
    <div class="card-body">
        <form id="form-arus-kas" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="bulan">Bulan</label>
                    <select class="form-control select2" id="bulan" name="bulan" required>
                        <?php foreach ($listBulan as $key => $namaBulan): ?>
                            <option value="<?= $key ?>" <?= ($key == $defaultBulan) ? 'selected' : '' ?>><?= $namaBulan ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-5">
                    <label for="tahun">Tahun</label>
                    <select class="form-control select2" id="tahun" name="tahun" required>
                        <?php 
                        $tahunSekarang = (int)date('Y');
                        for ($t = $tahunSekarang; $t >= $tahunSekarang - 5; $t--): 
                        ?>
                            <option value="<?= $t ?>" <?= ($t == $defaultTahun) ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block" title="Tampilkan">
                        <i class="fas fa-search"></i> Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Area Hasil Laporan Arus Kas -->
<div class="card shadow mb-4" id="card-hasil" style="display: none;">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary" id="judul-laporan">Rincian Laporan Arus Kas</h6>
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
        width: '100%'
    });

    $('#form-arus-kas').on('submit', function (e) {
        e.preventDefault();

        var bulan = $('#bulan').val();
        var tahun = $('#tahun').val();

        if (!bulan || !tahun) {
            alert('Silakan pilih bulan dan tahun terlebih dahulu.');
            return;
        }

        $('#card-hasil').show();
        $('#loading-spinner').show();
        $('#tabel-container').empty();

        $.ajax({
            url: 'laporan_arus_kas_proses.php',
            type: 'GET',
            data: { bulan: bulan, tahun: tahun },
            success: function (response) {
                $('#loading-spinner').hide();
                $('#tabel-container').html(response);
            },
            error: function () {
                $('#loading-spinner').hide();
                $('#tabel-container').html('<div class="alert alert-danger">Gagal memuat data laporan arus kas.</div>');
            }
        });
    });
});
</script>