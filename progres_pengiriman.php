<?php
$page_title = "Progres Pengiriman";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Progres Pengiriman per Item Penjualan</h6>
        <select id="filter-status" class="form-control form-control-sm" style="width:200px">
            <option value="">Semua status</option>
            <option value="belum_lengkap">Belum lengkap</option>
            <option value="belum">Belum dikirim</option>
            <option value="sebagian">Sebagian</option>
            <option value="lengkap">Lengkap</option>
        </select>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabel-progres" class="table table-bordered table-hover table-sm table-striped"  width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Tanggal</th>
                        <th>Faktur</th>
                        <th>Pelanggan</th>
                        <th>Produk</th>
                        <th class="text-right">Dipesan</th>
                        <th class="text-right">Terkirim</th>
                        <th class="text-right">Sisa</th>
                        <th style="width:160px">Progres</th>
                        <th>Status</th>
                        <th>Surat Jalan</th>
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
    var tabel = $('#tabel-progres').DataTable({
        processing: true,
        serverSide: true,
        searchDelay: 400,
        ajax: {
            url: 'progres_pengiriman_data.php',
            type: 'GET',
            data: function (d) { d.status = $('#filter-status').val(); }
        },
        order: [[1, 'desc']],
        pageLength: 25,
        language: { url: 'https://cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.6/i18n/id.json' },
        columnDefs: [
            { targets: [0, 8, 10], orderable: false, searchable: false },
            { targets: [5, 6, 7], className: 'text-right' }
        ]
    });

    $('#filter-status').on('change', function () { tabel.ajax.reload(); });
});
</script>