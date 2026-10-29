<?php
$page_title = "Data Kebutuhan Pelanggan";
include 'config.php';
include 'header.php';

// Qty tanpa nol di belakang koma: 1.500,00 -> 1.500 ; 12,50 -> 12,5
function format_qty($angka): string
{
    $teks = number_format((float)$angka, 2, ',', '.');
    return rtrim(rtrim($teks, '0'), ',');
}

$hasil = $conn->query("
    SELECT k.id, k.qty, k.keterangan, 
           p.nama AS nama_pelanggan, 
           pr.nama AS nama_produk, pr.satuan, pr.kode
    FROM kebutuhan k
    JOIN pelanggan p ON p.id = k.pelanggan_id
    JOIN produk pr ON pr.id = k.produk_id
    ORDER BY p.nama ASC, pr.nama ASC
");
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Kebutuhan Pelanggan</h6>
        <a href="kebutuhan_tambah.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Kebutuhan
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover datatable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Pelanggan</th>
                        <th>Produk</th>
                        <th>Qty</th>
                        <th>Keterangan</th>
                        <th style="width:130px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = $hasil->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama_pelanggan'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['kode'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($row['nama_produk'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= format_qty($row['qty']) . ' ' . htmlspecialchars($row['satuan'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$row['keterangan'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a href="kebutuhan_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="kebutuhan_hapus.php?id=<?= (int)$row['id'] ?>" class="btn btn-danger btn-sm btn-hapus">
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