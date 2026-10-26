<?php
$page_title = "Data Pembelian Langsung";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Pembelian Langsung</h6>
        <a href="pembelian_langsung_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Pembelian
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabel-pembelian" class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Pemasok</th>
                        <th>User</th>
                        <th>Detail Produk</th>
                        <th class="text-right">Grand Total</th>
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
    $('#tabel-pembelian').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'pembelian_langsung_data.php',
            type: 'GET'
        },
        order: [[1, 'desc']],
        pageLength: 10,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        columnDefs: [
            { targets: [0, 5, 7], orderable: false, searchable: false }, // 5 untuk Detail, 7 untuk Aksi
            { targets: 6, className: 'text-right' }, // Grand Total
            { targets: 7, className: 'text-center' }  // Aksi
        ]
    });
});
</script>