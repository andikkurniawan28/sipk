<?php
$page_title = "Data Pelanggan";
include 'config.php';
include 'header.php';

$hasil = $conn->query("
    SELECT p.id, p.nama, p.alamat, p.telepon, p.created_at, p.keterangan, p.status, u.nama AS dibuat_oleh
    FROM pelanggan p
    JOIN `user` u ON u.id = p.user_id
    ORDER BY p.nama ASC
");
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Pelanggan</h6>
        <a href="pelanggan_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Pelanggan
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover datatable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Nama</th>
                        <th>Alamat</th>
                        <th>Telepon</th>
                        <th>Keterangan</th>
                        <th>Dibuat</th>
                        <th>Oleh</th>
                        <th>Status</th>
                        <th style="width:130px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = $hasil->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= nl2br(htmlspecialchars((string)$row['alamat'], ENT_QUOTES, 'UTF-8')) ?></td>
                            <td><?= htmlspecialchars((string)$row['telepon'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$row['keterangan'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-order="<?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= date('d-m-Y H:i', strtotime($row['created_at'])) ?>
                            </td>
                            <td><?= htmlspecialchars($row['dibuat_oleh'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a href="pelanggan_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="pelanggan_hapus.php?id=<?= (int)$row['id'] ?>" class="btn btn-danger btn-sm btn-hapus">
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