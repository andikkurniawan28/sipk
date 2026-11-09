<?php
$page_title = "Laporan Laba Rugi";
include 'config.php';

$listBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Default: bulan/tahun ini, atau dari parameter (mis. setelah penutupan periode)
$reqBulan = $_GET['bulan'] ?? '';
$reqTahun = $_GET['tahun'] ?? '';
$autoLoad = preg_match('/^(0[1-9]|1[0-2])$/', $reqBulan) && preg_match('/^\d{4}$/', $reqTahun);

$defaultBulan = $autoLoad ? $reqBulan : date('m');
$defaultTahun = $autoLoad ? (int)$reqTahun : (int)date('Y');

$tahunSekarang = (int)date('Y');
$tahunAwal = min($tahunSekarang - 5, $defaultTahun);

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter</h6>
    </div>
    <div class="card-body">
        <form id="form-laba-rugi" autocomplete="off">
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
                        <?php for ($t = $tahunSekarang; $t >= $tahunAwal; $t--): ?>
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

<!-- Area Hasil Laporan Laba Rugi -->
<div class="card shadow mb-4" id="card-hasil" style="display: none;">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary" id="judul-laporan">Rincian Laporan Laba Rugi</h6>
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
            <!-- Hasil laporan dimuat lewat AJAX -->
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
window.addEventListener('load', function () {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    var xhrLaporan = null;

    function muatLaporan() {
        var bulan = $('#bulan').val();
        var tahun = $('#tahun').val();

        if (!bulan || !tahun) {
            alert('Silakan pilih bulan dan tahun terlebih dahulu.');
            return;
        }

        if (xhrLaporan) { xhrLaporan.abort(); xhrLaporan = null; }

        $('#card-hasil').show();
        $('#loading-spinner').show();
        $('#tabel-container').empty();

        xhrLaporan = $.ajax({
            url: 'laporan_laba_rugi_proses.php',
            type: 'GET',
            data: { bulan: bulan, tahun: tahun }
        })
        .done(function (response) {
            $('#tabel-container').html(response);
        })
        .fail(function (xhr, status) {
            if (status === 'abort') return;
            $('#tabel-container').html('<div class="alert alert-danger">Gagal memuat data laporan laba rugi.</div>');
        })
        .always(function () {
            $('#loading-spinner').hide();
            xhrLaporan = null;
        });
    }

    $('#form-laba-rugi').on('submit', function (e) {
        e.preventDefault();
        muatLaporan();
    });

    // Konfirmasi penutupan periode (form dimuat lewat AJAX, jadi pakai delegasi event)
    $('#tabel-container').on('submit', '#form-tutup', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            icon: 'warning',
            title: 'Tutup periode ini?',
            text: 'Jurnal penutup akan dibuat dan saldo Pendapatan serta Beban dipindahkan ke Laba Ditahan.',
            showCancelButton: true,
            confirmButtonText: 'Ya, tutup',
            cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) { form.submit(); }
        });
    });

    <?php if ($autoLoad): ?>
    muatLaporan();
    <?php endif; ?>
});
</script>