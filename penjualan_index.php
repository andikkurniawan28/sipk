<?php
$page_title = "Data Penjualan";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Penjualan</h6>
        <a href="penjualan_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Penjualan
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <!-- Sengaja TANPA class "datatable" agar tidak ikut diinisialisasi ulang oleh scripts.php (mode client-side) -->
            <table id="tabel-penjualan" class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Pelanggan</th>
                        <th class="text-center">Item</th>
                        <th class="text-right">Grand Total</th>
                        <th style="width:170px" class="text-center">Aksi</th>
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
// jQuery dan DataTables dimuat di scripts.php (di atas), jadi dijalankan saat halaman selesai dimuat.
window.addEventListener('load', function () {
    $('#tabel-penjualan').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'penjualan_data.php',
            type: 'GET'
        },
        order: [[1, 'desc']],
        pageLength: 10,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        columnDefs: [
            { targets: [0, 6], orderable: false, searchable: false },
            { targets: 4, className: 'text-center' },
            { targets: 5, className: 'text-right' },
            { targets: 6, className: 'text-center' }
        ]
    });
});
</script>