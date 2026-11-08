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
                    <h6 class="m-0 font-weight-bold text-primary">Setting</h6>
                </div>
                <div class="card-body">                    
                    <div class="list-group">
                        <a href="setup_master.php" class="list-group-item list-group-item-action d-flex align-items-center py-3 btn-setup" data-nama="Setup Master">
                            <div class="mr-3">
                                <div class="icon-circle bg-primary text-white">
                                    <i class="fas fa-cogs"></i>
                                </div>
                            </div>
                            <div>
                                <div class="font-weight-bold text-gray-800">Setup Database</div>
                                <div class="text-xs text-muted">Mengatur ulang struktur tabel database.</div>
                            </div>
                        </a>

                        <a href="backup.php?mulai=1" class="list-group-item list-group-item-action d-flex align-items-center py-3 btn-backup">
                            <div class="mr-3">
                                <div class="icon-circle bg-success text-white">
                                    <i class="fas fa-download"></i>
                                </div>
                            </div>
                            <div>
                                <div class="font-weight-bold text-gray-800">Backup Database</div>
                                <div class="text-xs text-muted">Unduh cadangan seluruh data (.sql) ke perangkat Anda.</div>
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

        // Backup database (tidak merusak data, jadi konfirmasinya ringan)
        document.querySelectorAll('.btn-backup').forEach(button => {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                const url = this.getAttribute('href');

                Swal.fire({
                    title: 'Backup database?',
                    text: 'File backup akan diunduh ke perangkat Anda. Sebaiknya dilakukan di luar jam sibuk.',
                    icon: 'question',
                    input: 'checkbox',
                    inputValue: 1,
                    inputPlaceholder: 'Kompres file (.sql.gz, ukuran lebih kecil)',
                    showCancelButton: true,
                    confirmButtonColor: '#1cc88a',
                    cancelButtonColor: '#858796',
                    confirmButtonText: 'Ya, Backup',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url + (result.value ? '&gz=1' : '');
                        Swal.fire({
                            title: 'Backup sedang disiapkan',
                            text: 'Unduhan akan dimulai otomatis. Jangan tutup halaman sampai selesai.',
                            icon: 'info',
                            timer: 4000,
                            showConfirmButton: false
                        });
                    }
                });
            });
        });
    });
</script>