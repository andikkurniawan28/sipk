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
                <?= canAccess('wilayah_index.php') ? '<a class="collapse-item" href="wilayah_index.php">Wilayah</a>' : '' ?>
                <?= canAccess('pemasok_index.php') ? '<a class="collapse-item" href="pemasok_index.php">Pemasok</a>' : '' ?>
                <?= canAccess('akun_index.php') ? '<a class="collapse-item" href="akun_index.php">Akun</a>' : '' ?>
                <?php /*
                <?= canAccess('pelanggan_index.php') ? '<a class="collapse-item" href="pelanggan_index.php">Pelanggan</a>' : '' ?>
                <?= canAccess('kebutuhan_index.php') ? '<a class="collapse-item" href="kebutuhan_index.php">Kebutuhan Pelanggan</a>' : '' ?>
                <?= canAccess('mitra_index.php') ? '<a class="collapse-item" href="mitra_index.php">Mitra</a>' : '' ?>
                */ ?>

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
                <?= canAccess('jurnal_index.php') ? '<a class="collapse-item" href="jurnal_index.php">Jurnal</a>' : '' ?>
                <?= canAccess('pembelian_langsung_index.php') ? '<a class="collapse-item" href="pembelian_langsung_index.php">Pembelian Langsung</a>' : '' ?>
                <?= canAccess('penerimaan_pembelian_langsung_index.php') ? '<a class="collapse-item" href="penerimaan_pembelian_langsung_index.php">Penerimaan PBL</a>' : '' ?>
                <?= canAccess('pembayaran_pembelian_langsung_index.php') ? '<a class="collapse-item" href="pembayaran_pembelian_langsung_index.php">Pembayaran PBL</a>' : '' ?>
                
                <?php /*
                <?= canAccess('proposal_index.php') ? '<a class="collapse-item" href="proposal_index.php">Proposal</a>' : '' ?>
                <?= canAccess('penerimaan_index.php') ? '<a class="collapse-item" href="penerimaan_index.php">Penerimaan</a>' : '' ?>

                <?= canAccess('panen_index.php') ? '<a class="collapse-item" href="panen_index.php">Panen</a>' : '' ?>
                <?= canAccess('preparasi_index.php') ? '<a class="collapse-item" href="preparasi_index.php">Preparasi</a>' : '' ?>
                <?= canAccess('produksi_index.php') ? '<a class="collapse-item" href="produksi_index.php">Produksi</a>' : '' ?>
                <?= canAccess('pengeluaran_index.php') ? '<a class="collapse-item" href="pengeluaran_index.php">Pengeluaran</a>' : '' ?>
                <?= canAccess('penjualan_index.php') ? '<a class="collapse-item" href="penjualan_index.php">Penjualan</a>' : '' ?>
                <?= canAccess('pengolahan_index.php') ? '<a class="collapse-item" href="pengolahan_index.php">Pengolahan</a>' : '' ?>
                <?= canAccess('komplain_index.php') ? '<a class="collapse-item" href="komplain_index.php">Komplain Pelanggan</a>' : '' ?>
                */ ?>

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
                <?= canAccess('buku_besar_index.php') ? '<a class="collapse-item" href="buku_besar_index.php">Buku Besar</a>' : '' ?>
                <?= canAccess('kartu_stok_index.php') ? '<a class="collapse-item" href="kartu_stok_index.php">Kartu Stok</a>' : '' ?>

                <?php /*
                */ ?>

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