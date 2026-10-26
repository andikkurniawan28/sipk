<?php
$page_title = "Setting";
include 'config.php';
include 'header.php';
?>

    <h1 class="h3 mb-4 text-gray-800"><?= $page_title ?></h1>

    <!-- Content Row -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Inisialisasi & Reset Tabel Database</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Gunakan tombol di bawah ini untuk menjalankan skrip pengaturan atau perbaikan struktur database secara manual. 
                        <strong class="text-danger">Peringatan:</strong> Proses setup dengan fitur reset akan menghapus data lama pada tabel terkait.
                    </p>
                    <hr>
                    
                    <div class="list-group">
                        <!-- Tombol 1: Setup Master -->
                        <a href="setup_master.php" class="list-group-item list-group-item-action d-flex align-items-center py-3 btn-setup" data-nama="Setup Master">
                            <div class="mr-3">
                                <div class="icon-circle bg-primary text-white">
                                    <i class="fas fa-cogs"></i>
                                </div>
                            </div>
                            <div>
                                <div class="font-weight-bold text-gray-800">Setup Master</div>
                                <div class="text-xs text-muted">Mengatur ulang tabel Produk, Gudang, Akun, Mitra, dan User.</div>
                            </div>
                        </a>

                        <!-- Tombol 2: Setup Kemitraan -->
                        <a href="setup_kemitraan.php" class="list-group-item list-group-item-action d-flex align-items-center py-3 btn-setup" data-nama="Setup Kemitraan">
                            <div class="mr-3">
                                <div class="icon-circle bg-success text-white">
                                    <i class="fas fa-cogs"></i>
                                </div>
                            </div>
                            <div>
                                <div class="font-weight-bold text-gray-800">Setup Kemitraan</div>
                                <div class="text-xs text-muted">Mengatur ulang tabel Petani, Panen, dan Panen Detail.</div>
                            </div>
                        </a>

                        <!-- Tombol 3: Setup Penjualan -->
                        <a href="setup_penjualan.php" class="list-group-item list-group-item-action d-flex align-items-center py-3 btn-setup" data-nama="Setup Penjualan">
                            <div class="mr-3">
                                <div class="icon-circle bg-info text-white">
                                    <i class="fas fa-cogs"></i>
                                </div>
                            </div>
                            <div>
                                <div class="font-weight-bold text-gray-800">Setup Penjualan</div>
                                <div class="text-xs text-muted">Mengatur ulang tabel Pelanggan, Kebutuhan, Penjualan, Penjualan Detail, dan Komplain.</div>
                            </div>
                        </a>

                        <!-- Tombol: Setup Pengolahan -->
                        <a href="setup_pengolahan.php" class="list-group-item list-group-item-action d-flex align-items-center py-3 btn-setup" data-nama="Setup Pengolahan">
                            <div class="mr-3">
                                <div class="icon-circle bg-warning text-white">
                                    <i class="fas fa-cogs"></i>
                                </div>
                            </div>
                            <div>
                                <div class="font-weight-bold text-gray-800">Setup Pengolahan</div>
                                <div class="text-xs text-muted">Mengatur ulang tabel Pengolahan, Input Pengolahan, dan Output Pengolahan.</div>
                            </div>
                        </a>

                        <!-- Tombol: Setup Logistik -->
                        <a href="setup_logistik.php" class="list-group-item list-group-item-action d-flex align-items-center py-3 btn-setup" data-nama="Setup Logistik">
                            <div class="mr-3">
                                <div class="icon-circle bg-info text-white">
                                    <i class="fas fa-cogs"></i>
                                </div>
                            </div>
                            <div>
                                <div class="font-weight-bold text-gray-800">Setup Logistik</div>
                                <div class="text-xs text-muted">Mengatur ulang tabel Penerimaan, Penerimaan Detail, dan Mutasi Stok.</div>
                            </div>
                        </a>

                        <!-- Tombol: Setup Keuangan -->
                        <a href="setup_keuangan.php" class="list-group-item list-group-item-action d-flex align-items-center py-3 btn-setup" data-nama="Setup Keuangan">
                            <div class="mr-3">
                                <div class="icon-circle bg-success text-white">
                                    <i class="fas fa-cogs"></i>
                                </div>
                            </div>
                            <div>
                                <div class="font-weight-bold text-gray-800">Setup Keuangan</div>
                                <div class="text-xs text-muted">Mengatur ulang tabel Jurnal, Jurnal Detail, dan Buku Besar.</div>
                            </div>
                        </a>

                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
include 'footer.php';
include 'scripts.php';
?>

<!-- Skrip JavaScript SweetAlert2 Confirm -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const setupButtons = document.querySelectorAll('.btn-setup');

        setupButtons.forEach(button => {
            button.addEventListener('click', function (e) {
                e.preventDefault(); // Mencegah link langsung terbuka
                const url = this.getAttribute('href');
                const namaSetup = this.getAttribute('data-nama');

                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: `Anda akan menjalankan "${namaSetup}". Data lama pada tabel terkait akan direset/dihapus!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, Lanjutkan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url; // Lanjutkan ke halaman setup jika dikonfirmasi
                    }
                });
            });
        });
    });
</script>