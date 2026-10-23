<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion toggled" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-seedling"></i>
        </div>
        <div class="sidebar-brand-text mx-3">SIPK</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Home -->
    <li class="nav-item">
        <a class="nav-link" href="index.php">
            <i class="fas fa-fw fa-home"></i>
            <span>Home</span>
        </a>
    </li>

    <!-- Nav Item - Master -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseMaster"
            aria-expanded="true" aria-controls="collapseMaster">
            <i class="fas fa-fw fa-database"></i>
            <span>Master</span>
        </a>
        <div id="collapseMaster" class="collapse" aria-labelledby="headingMaster" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">

                <?= canAccess('user_index.php') ? '<a class="collapse-item" href="user_index.php">User</a>' : '' ?>
                <?= canAccess('produk_index.php') ? '<a class="collapse-item" href="produk_index.php">Produk</a>' : '' ?>
                <?= canAccess('gudang_index.php') ? '<a class="collapse-item" href="gudang_index.php">Gudang</a>' : '' ?>
                <?= canAccess('akun_index.php') ? '<a class="collapse-item" href="akun_index.php">Akun</a>' : '' ?>
                <?= canAccess('mitra_index.php') ? '<a class="collapse-item" href="mitra_index.php">Mitra</a>' : '' ?>
                <?= canAccess('petani_index.php') ? '<a class="collapse-item" href="petani_index.php">Petani</a>' : '' ?>
                <?= canAccess('pelanggan_index.php') ? '<a class="collapse-item" href="pelanggan_index.php">Pelanggan</a>' : '' ?>
                <?= canAccess('kebutuhan_index.php') ? '<a class="collapse-item" href="kebutuhan_index.php">Kebutuhan Pelanggan</a>' : '' ?>

            </div>
        </div>
    </li>

    <!-- Nav Item - Transaksi -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseTransaksi"
            aria-expanded="true" aria-controls="collapseTransaksi">
            <i class="fas fa-fw fa-cash-register"></i>
            <span>Transaksi</span>
        </a>
        <div id="collapseTransaksi" class="collapse" aria-labelledby="headingTransaksi" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">

                <?= canAccess('panen_index.php') ? '<a class="collapse-item" href="panen_index.php">Panen</a>' : '' ?>
                <?= canAccess('penjualan_index.php') ? '<a class="collapse-item" href="penjualan_index.php">Penjualan</a>' : '' ?>
                <?= canAccess('komplain_index.php') ? '<a class="collapse-item" href="komplain_index.php">Komplain Pelanggan</a>' : '' ?>
                <?= canAccess('pengolahan_index.php') ? '<a class="collapse-item" href="pengolahan_index.php">Pengolahan</a>' : '' ?>

            </div>
        </div>
    </li>

    <!-- Nav Item - Laporan -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseLaporan"
            aria-expanded="true" aria-controls="collapseLaporan">
            <i class="fas fa-fw fa-file-alt"></i>
            <span>Laporan</span>
        </a>
        <div id="collapseLaporan" class="collapse" aria-labelledby="headingLaporan" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <h6 class="collapse-header">Kemitraan:</h6>
                <h6 class="collapse-header">Penjualan:</h6>
                <h6 class="collapse-header">Pengolahan:</h6>
                <h6 class="collapse-header">Logistik:</h6>
                <h6 class="collapse-header">Keuangan:</h6>
            </div>
        </div>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<!-- End of Sidebar -->