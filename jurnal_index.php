<?php
$page_title = "Data Jurnal";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<!-- Card Daftar Jurnal -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Jurnal</h6>
        <a href="jurnal_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Jurnal
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabel-jurnal" class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Admin</th>
                        <th>Keterangan</th>
                        <th>Detail</th>
                        <th>Timestamp</th>
                        <th style="width:120px" class="text-center">Aksi</th>
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
let tabelJurnal;

window.addEventListener('load', function () {
    tabelJurnal = $('#tabel-jurnal').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'jurnal_data.php',
            type: 'GET'
        },
        order: [[2, 'desc']],
        pageLength: 10,
        language: {
            url: 'https://cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.6/i18n/id.json'
        },
        columnDefs: [
            { targets: [0, 7], orderable: false, searchable: false },
            { targets: 7, className: 'text-center' }
        ]
    });
});
</script>