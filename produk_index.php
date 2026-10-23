<?php
$page_title = "Data Produk";
include 'config.php';
include 'header.php';

$hasil = $conn->query("SELECT id, nama, satuan, harga_beli, harga_jual FROM produk ORDER BY nama ASC");
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Produk</h6>
        <a href="produk_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Produk
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover datatable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Nama</th>
                        <th>Satuan</th>
                        <th class="text-right">Harga Beli</th>
                        <th class="text-right">Harga Jual</th>
                        <th style="width:130px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = $hasil->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['satuan'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-right"><?= number_format((float)$row['harga_beli'], 2, ',', '.') ?></td>
                            <td class="text-right"><?= number_format((float)$row['harga_jual'], 2, ',', '.') ?></td>
                            <td>
                                <a href="produk_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="produk_hapus.php?id=<?= (int)$row['id'] ?>" class="btn btn-danger btn-sm btn-hapus">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>