<?php
$page_title = "Data Penerimaan Pembelian Langsung";
include 'config.php';
require_once 'datatables_core.php';
require_once 'penerimaan_pembelian_langsung_core.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Penerimaan Pembelian Langsung</h6>
        <a href="penerimaan_pembelian_langsung_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Penerimaan
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabel-penerimaan" class="table table-bordered table-hover" width="100%" cellspacing="0">
                <?= htmlKepalaTabel(KOLOM_TABEL_PENERIMAAN) ?>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>

<?= skripDataTablesServerSide('tabel-penerimaan', 'penerimaan_pembelian_langsung_data.php', KOLOM_TABEL_PENERIMAAN, KOLOM_URUT_AWAL_PENERIMAAN) ?>