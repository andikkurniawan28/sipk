<?php
$page_title = "Data Komplain Pelanggan";
include 'config.php';
include 'header.php';

$hasil = $conn->query("
    SELECT k.id, k.tanggal, k.kode, k.keterangan, 
           pl.nama AS nama_pelanggan, u.nama AS nama_user
    FROM komplain k
    JOIN pelanggan pl ON pl.id = k.pelanggan_id
    JOIN `user` u ON u.id = k.user_id
    ORDER BY k.tanggal DESC, k.id DESC
");
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Komplain</h6>
        <a href="komplain_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Komplain
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover datatable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Pelanggan</th>
                        <th>Keterangan</th>
                        <th>Oleh</th>
                        <th style="width:130px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = $hasil->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($row['kode'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['nama_pelanggan'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= nl2br(htmlspecialchars((string)$row['keterangan'], ENT_QUOTES, 'UTF-8')) ?></td>
                            <td><?= htmlspecialchars($row['nama_user'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a href="komplain_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="komplain_hapus.php?id=<?= (int)$row['id'] ?>" class="btn btn-danger btn-sm btn-hapus">
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