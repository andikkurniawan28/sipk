<?php
$page_title = "Pembayaran Penjualan";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar <?= h($page_title) ?></h6>
        <a href="pembayaran_penjualan_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah <?= h($page_title) ?>
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabel-pembayaran" class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Faktur</th>
                        <th>Pelanggan</th>
                        <th>Akun</th>
                        <th>Admin</th>
                        <th class="text-right">Total</th>
                        <th style="width:100px" class="text-center">Aksi</th>
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
    $('#tabel-pembayaran').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'pembayaran_penjualan_data.php',
            type: 'GET'
        },
        order: [[2, 'desc']],
        pageLength: 10,
        language: {
            url: 'https://cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.6/i18n/id.json'
        },
        columnDefs: [
            { targets: [0, 8], orderable: false, searchable: false },
            { targets: 7, className: 'text-right' },
            { targets: 8, className: 'text-center' }
        ]
    });
});
</script>