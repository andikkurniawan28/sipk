<?php
$page_title = "Home";
include 'config.php';

// 1. Hitung total seluruh petani binaan
$queryTotal = mysqli_query($conn, "SELECT COUNT(*) AS total FROM petani");
$dataTotal = mysqli_fetch_assoc($queryTotal);
$jumlahPetani = $dataTotal['total'] ?? 0;

// 2. Cari pembina (user) dengan jumlah petani terbanyak
$queryTop = mysqli_query($conn, "
    SELECT u.nama AS nama_pembina, COUNT(p.id) AS jumlah_binaan 
    FROM petani p 
    JOIN user u ON p.user_id = u.id 
    GROUP BY p.user_id 
    ORDER BY jumlah_binaan DESC 
    LIMIT 1
");
$dataTop = mysqli_fetch_assoc($queryTop);
$namaPembina = $dataTop['nama_pembina'] ?? '-';
$jumlahBinaanTop = $dataTop['jumlah_binaan'] ?? 0;

// 3. Statistik Pelanggan & Statusnya (Prospek, Penawaran, Closing)
$queryPelangganTotal = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pelanggan");
$jumlahPelanggan = mysqli_fetch_assoc($queryPelangganTotal)['total'] ?? 0;

$queryStatusPelanggan = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN status = 'Prospek' THEN 1 ELSE 0 END) AS prospek,
        SUM(CASE WHEN status = 'Penawaran' THEN 1 ELSE 0 END) AS penawaran,
        SUM(CASE WHEN status = 'Closing' THEN 1 ELSE 0 END) AS closing
    FROM pelanggan
");
$dataStatus = mysqli_fetch_assoc($queryStatusPelanggan);
$jmlProspek  = $dataStatus['prospek'] ?? 0;
$jmlPenawaran = $dataStatus['penawaran'] ?? 0;
$jmlClosing  = $dataStatus['closing'] ?? 0;

// 4. Cari User/Sales yang paling banyak mencari pelanggan baru
$queryTopPelanggan = mysqli_query($conn, "
    SELECT u.nama AS nama_pencari, COUNT(pl.id) AS jumlah_pelanggan 
    FROM pelanggan pl 
    JOIN user u ON pl.user_id = u.id 
    GROUP BY pl.user_id 
    ORDER BY jumlah_pelanggan DESC 
    LIMIT 1
");
$dataTopPelanggan = mysqli_fetch_assoc($queryTopPelanggan);
$namaPencariPelanggan = $dataTopPelanggan['nama_pencari'] ?? '-';
$jumlahPelangganTop = $dataTopPelanggan['jumlah_pelanggan'] ?? 0;

// 5. Total Penjualan Bulan Ini (Grand Total & Jumlah Transaksi)
$awalBulan      = date('Y-m-01');
$awalBulanDepan  = date('Y-m-01', strtotime($awalBulan . ' +1 month'));

$stmtPenjualanBulan = $conn->prepare("
    SELECT COUNT(*) AS total_transaksi, SUM(grand_total) AS total_omset
    FROM penjualan
    WHERE tanggal >= ? AND tanggal < ?
");
$stmtPenjualanBulan->bind_param('ss', $awalBulan, $awalBulanDepan);
$stmtPenjualanBulan->execute();
$dataPenjualanBulan = $stmtPenjualanBulan->get_result()->fetch_assoc();
$stmtPenjualanBulan->close();

$jumlahTransaksiBulan = $dataPenjualanBulan['total_transaksi'] ?? 0;
$totalOmsetBulan = $dataPenjualanBulan['total_omset'] ?? 0;

// 6. Cari Pelanggan dengan Penjualan/Omset Terbanyak
$queryTopSalesPelanggan = mysqli_query($conn, "
    SELECT pl.nama AS nama_pelanggan, SUM(p.grand_total) AS total_belanja, COUNT(p.id) AS jumlah_transaksi
    FROM penjualan p
    JOIN pelanggan pl ON p.pelanggan_id = pl.id
    GROUP BY p.pelanggan_id
    ORDER BY total_belanja DESC
    LIMIT 1
");
$dataTopSalesPelanggan = mysqli_fetch_assoc($queryTopSalesPelanggan);
$namaPelangganTop = $dataTopSalesPelanggan['nama_pelanggan'] ?? '-';
$totalBelanjaTop = $dataTopSalesPelanggan['total_belanja'] ?? 0;
$jumlahTransaksiTopPelanggan = $dataTopSalesPelanggan['jumlah_transaksi'] ?? 0;

// 7. Total Komplain (Keseluruhan & Bulan Ini)
$queryKomplainTotal = mysqli_query($conn, "SELECT COUNT(*) AS total FROM komplain");
$jumlahKomplain = mysqli_fetch_assoc($queryKomplainTotal)['total'] ?? 0;

$stmtKomplainBulan = $conn->prepare("
    SELECT COUNT(*) AS total FROM komplain WHERE tanggal >= ? AND tanggal < ?
");
$stmtKomplainBulan->bind_param('ss', $awalBulan, $awalBulanDepan);
$stmtKomplainBulan->execute();
$jumlahKomplainBulan = $stmtKomplainBulan->get_result()->fetch_assoc()['total'] ?? 0;
$stmtKomplainBulan->close();

// 8. Total panen bulan ini per produk
$stmtPanen = $conn->prepare("
    SELECT pr.id, pr.nama, pr.satuan, SUM(d.qty) AS total_qty
    FROM panen_detail d
    JOIN panen p ON p.id = d.panen_id
    JOIN produk pr ON pr.id = d.produk_id
    WHERE p.tanggal >= ? AND p.tanggal < ?
    GROUP BY pr.id, pr.nama, pr.satuan
    ORDER BY pr.nama ASC
");
$stmtPanen->bind_param('ss', $awalBulan, $awalBulanDepan);
$stmtPanen->execute();
$panenBulanIni = $stmtPanen->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPanen->close();

$namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$labelBulan = $namaBulan[(int)date('n')] . ' ' . date('Y');

// Warna card bergantian agar tiap produk mudah dibedakan
$warnaCard = ['info', 'warning', 'danger', 'secondary'];

// Qty tanpa nol di belakang koma
function format_qty($angka): string
{
    $teks = number_format((float)$angka, 2, ',', '.');
    return rtrim(rtrim($teks, '0'), ',');
}

include 'header.php';
?>

    <h1 class="h3 mb-4 text-gray-800"><?= $page_title ?></h1>

    <!-- Content Row -->
    <div class="row">

        <!-- Card 1: Jumlah Petani Binaan -->
        <div class="col-xl-3 col-md-3 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Petani Binaan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($jumlahPetani, 0, ',', '.') ?> Orang</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Pembina Petani Terbanyak -->
        <div class="col-xl-3 col-md-3 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Pembina Terbanyak</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= htmlspecialchars($namaPembina) ?> 
                                <span class="text-xs font-weight-normal text-muted">(<?= $jumlahBinaanTop ?> Petani)</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-award fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Total Pelanggan & Status (Prospek, Penawaran, Closing) -->
        <div class="col-xl-3 col-md-3 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Pelanggan (<?= number_format($jumlahPelanggan, 0, ',', '.') ?>)</div>
                            <div class="small font-weight-bold text-gray-800 mt-1">
                                <span class="badge badge-warning text-dark">Prospek: <?= $jmlProspek ?></span>
                                <span class="badge badge-info">Penawaran: <?= $jmlPenawaran ?></span>
                                <span class="badge badge-success">Closing: <?= $jmlClosing ?></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-address-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4: Paling Banyak Mencari Pelanggan Baru -->
        <div class="col-xl-3 col-md-3 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Top Pencari Pelanggan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= htmlspecialchars($namaPencariPelanggan) ?> 
                                <span class="text-xs font-weight-normal text-muted">(<?= $jumlahPelangganTop ?> Pelanggan)</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tag fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 5: Penjualan Bulan Ini -->
        <div class="col-xl-3 col-md-3 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Penjualan <?= $labelBulan ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?= number_format($totalOmsetBulan, 0, ',', '.') ?>
                            </div>
                            <div class="text-xs text-muted mt-1">
                                Total Transaksi: <strong><?= $jumlahTransaksiBulan ?></strong>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 6: Pelanggan dengan Penjualan Terbanyak -->
        <div class="col-xl-3 col-md-3 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Top Pelanggan (Penjualan)</div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?= htmlspecialchars($namaPelangganTop) ?>
                            </div>
                            <div class="text-xs text-muted mt-1">
                                Rp <?= number_format($totalBelanjaTop, 0, ',', '.') ?> <span class="font-weight-normal">(<?= $jumlahTransaksiTopPelanggan ?> Transaksi)</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-store fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 7: Komplain Pelanggan -->
        <div class="col-xl-3 col-md-3 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Total Komplain (<?= number_format($jumlahKomplain, 0, ',', '.') ?>)</div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800 mt-1">
                                Bulan Ini: <span class="text-danger"><?= $jumlahKomplainBulan ?> Kasus</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card panen bulan ini: satu card per produk yang dipanen -->
        <?php foreach ($panenBulanIni as $i => $p): ?>
            <?php $warna = $warnaCard[$i % count($warnaCard)]; ?>
            <div class="col-xl-3 col-md-3 mb-4">
                <div class="card border-left-<?= $warna ?> shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-<?= $warna ?> text-uppercase mb-1">
                                    Panen <?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?>
                                    <span class="font-weight-normal text-muted">(<?= $labelBulan ?>)</span>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= format_qty($p['total_qty']) ?> <?= htmlspecialchars($p['satuan'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-seedling fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$panenBulanIni): ?>
            <!-- Belum ada panen bulan ini -->
            <div class="col-xl-3 col-md-3 mb-4">
                <div class="card border-left-secondary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                    Panen <?= $labelBulan ?></div>
                                <div class="h6 mb-0 text-gray-600">Belum ada panen</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-seedling fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

<?php
include 'footer.php';
include 'scripts.php';
?>