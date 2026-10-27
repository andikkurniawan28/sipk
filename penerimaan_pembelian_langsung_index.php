<?php
$page_title = "Data Penerimaan Pembelian Langsung";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Penerimaan Pembelian</h6>
        <a href="penerimaan_pembelian_langsung_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Penerimaan
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabel-penerimaan" class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Pembelian</th>
                        <th>Gudang</th>
                        <th>Admin</th>
                        <th>Detail</th>
                        <th class="text-right">Total</th>
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
window.addEventListener('load', function () {
    $('#tabel-penerimaan').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'penerimaan_pembelian_langsung_data.php',
            type: 'GET'
        },
        order: [[2, 'desc']],
        pageLength: 10,
        language: {
            url: 'https://cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.6/i18n/id.json'
        },
        columnDefs: [
            { targets: [0, 6, 9], orderable: false, searchable: false }, // 0: No, 6: Detail, 9: Aksi
            { targets: 7, className: 'text-right' }, // Total
            { targets: 9, className: 'text-center' }  // Aksi
        ]
    });
});
</script>