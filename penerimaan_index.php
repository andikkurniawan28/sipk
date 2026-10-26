<?php
$page_title = "Data Penerimaan";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Penerimaan</h6>
        <a href="penerimaan_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Penerimaan
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <!-- Sengaja TANPA class "datatable" agar tidak diinisialisasi ulang secara otomatis (client-side) -->
            <table id="tabel-penerimaan" class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Faktur</th>
                        <th>Gudang</th>
                        <th>Oleh</th>
                        <th style="width:130px" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
window.addEventListener('load', function () {
    $('#tabel-penerimaan').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'penerimaan_data.php',
            type: 'GET'
        },
        order: [[1, 'desc']],
        pageLength: 10,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        columnDefs: [
            { targets: [0, 6], orderable: false, searchable: false },
            { targets: 6, className: 'text-center' }
        ]
    });
});
</script>