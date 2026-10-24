<?php
$page_title = "Tambah Preparasi";
include 'config.php';
require_once 'preparasi_helper.php';
include 'header.php';

$kode_otomatis = generate_kode_preparasi($conn);
$tanggal_default = date('Y-m-d');
$produkList = $conn->query("SELECT id, nama, satuan FROM produk ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<form action="preparasi_simpan.php" method="POST" autocomplete="off">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Data Preparasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="kode">Kode</label>
                    <input type="text" class="form-control" id="kode" name="kode" value="<?= $kode_otomatis ?>" readonly required>
                </div>
                <div class="form-group col-md-4">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= $tanggal_default ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="keterangan">Keterangan</label>
                    <textarea class="form-control" id="keterangan" name="keterangan" rows="1">Produksi</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Detail Produk</h6>
            <button type="button" class="btn btn-success btn-sm" id="tambah-baris">
                <i class="fas fa-plus"></i> Tambah Item
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tabel-item">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th style="width: 180px;">Qty</th>
                            <th style="width: 60px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="produk_id[]" class="form-control select2-produk" required>
                                    <option value="">-- Pilih Produk --</option>
                                    <?php foreach ($produkList as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>">
                                            <?= htmlspecialchars($p['nama'] . ' (' . $p['satuan'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="qty[]" class="form-control text-right" required>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-danger btn-sm btn-hapus-baris" disabled>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary mt-3">
                <i class="fas fa-save"></i> Simpan Preparasi
            </button>
            <a href="preparasi_index.php" class="btn btn-secondary mt-3">Batal</a>
        </div>
    </div>
</form>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
// Pastikan string opsi produk dibangun dengan benar dari PHP ke JS
let produkOpsiHTML = `<option value="">-- Pilih Produk --</option>`;
<?php foreach ($produkList as $p): ?>
    produkOpsiHTML += `<option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nama'] . ' (' . $p['satuan'] . ')', ENT_QUOTES, 'UTF-8') ?></option>`;
<?php endforeach; ?>

$(document).ready(function () {
    // Inisialisasi awal Select2
    $('.select2-produk').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: '-- Pilih Produk --',
        allowClear: true
    });

    // Tambah baris baru
    $('#tambah-baris').on('click', function () {
        let newRow = `
            <tr>
                <td>
                    <select name="produk_id[]" class="form-control select2-produk" required>
                        ${produkOpsiHTML}
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" name="qty[]" class="form-control text-right" required>
                </td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-danger btn-sm btn-hapus-baris">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#tabel-item tbody').append(newRow);

        // Inisialisasi Select2 pada select yang baru saja ditambahkan
        $('#tabel-item tbody tr:last-child .select2-produk').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Pilih Produk --',
            allowClear: true
        });

        aturTombolHapus();
    });

    // Hapus baris
    $(document).on('click', '.btn-hapus-baris', function () {
        let tr = $(this).closest('tr');
        let select = tr.find('.select2-produk');
        
        // Hancurkan instance select2 sebelum elemen dihapus
        if (select.data('select2')) {
            select.select2('destroy');
        }
        
        tr.remove();
        aturTombolHapus();
    });
});

function aturTombolHapus() {
    let rows = $('#tabel-item tbody tr');
    let tombolHapus = $('.btn-hapus-baris');
    tombolHapus.prop('disabled', rows.length === 1);
}
</script>