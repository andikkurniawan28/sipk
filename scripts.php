    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/js/dataTables.bootstrap4.min.js"></script>

    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1/dist/sweetalert2.all.min.js"></script>

    <!-- Inisialisasi global -->
    <script>
    $(function () {

        // ===== DataTables: pakai class .datatable pada <table> =====
        $('.datatable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            },
            responsive: false,
            pageLength: 10
        });

        // ===== Select2: pakai class .select2 pada <select> =====
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Pilih --',
            allowClear: true
        });

        // ===== SweetAlert2: konfirmasi hapus =====
        // Pakai: <a href="hapus.php?id=1" class="btn-hapus">Hapus</a>
        $(document).on('click', '.btn-hapus', function (e) {
            e.preventDefault();
            const url = $(this).attr('href');

            Swal.fire({
                title: 'Yakin hapus data?',
                text: 'Data yang dihapus tidak bisa dikembalikan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#858796',
                confirmButtonText: 'Ya, hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });

    });
    </script>

    <?php
    // Notifikasi flash dari session (opsional)
    // Set di halaman proses: $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data tersimpan'];
    if (!empty($_SESSION['flash'])):
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
    ?>
    <script>
        Swal.fire({
            icon: <?= json_encode($flash['icon'] ?? 'info') ?>,
            title: <?= json_encode($flash['title'] ?? '') ?>,
            text: <?= json_encode($flash['text'] ?? '') ?>,
            timer: 2500,
            showConfirmButton: false
        });
    </script>
    <?php endif; ?>

</body>

</html>