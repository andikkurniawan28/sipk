<?php
$page_title = "Matriks Stok";
include 'config.php';
include 'header.php';

// 1. Ambil daftar semua gudang untuk dijadikan kolom dinamis
$gudangQuery = $conn->query("SELECT id, nama FROM gudang ORDER BY nama ASC");
$gudangList = [];
while ($g = $gudangQuery->fetch_assoc()) {
    $gudangList[] = $g;
}

// 2. Ambil daftar produk dan rekap stok per gudangnya menggunakan mutasi_stok
$sqlGudangCases = "";
foreach ($gudangList as $g) {
    $gId = (int)$g['id'];
    $sqlGudangCases .= ", COALESCE(SUM(CASE WHEN ms.gudang_id = $gId THEN ms.masuk - ms.keluar ELSE 0 END), 0) AS gudang_$gId";
}

$produkQuery = $conn->query("
    SELECT p.id, p.kode, p.nama AS nama_produk, p.satuan 
    $sqlGudangCases
    FROM produk p
    LEFT JOIN mutasi_stok ms ON ms.produk_id = p.id
    GROUP BY p.id, p.kode, p.nama, p.satuan
    ORDER BY p.nama ASC
");
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Matriks Stok</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Satuan</th>
                        <?php foreach ($gudangList as $g): ?>
                            <th class="text-right"><?= htmlspecialchars($g['nama'], ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                        <th class="text-right font-weight-bold">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $produkQuery->fetch_assoc()): ?>
                        <?php 
                        $totalProduk = 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['kode'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['nama_produk'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['satuan'], ENT_QUOTES, 'UTF-8') ?></td>
                            
                            <?php foreach ($gudangList as $g): ?>
                                <?php 
                                $gId = $g['id'];
                                $stokGudang = (float)$row["gudang_$gId"];
                                $totalProduk += $stokGudang;
                                ?>
                                <td class="text-right">
                                    <?= $stokGudang ?>
                                </td>
                            <?php endforeach; ?>
                            
                            <td class="text-right font-weight-bold">
                                <?= $totalProduk ?>
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