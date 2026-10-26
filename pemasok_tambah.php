<?php
$page_title = "Tambah Pemasok";
include 'config.php';
include 'header.php';

// Ambil data wilayah untuk pilihan dropdown
$wilayahList = $conn->query("SELECT id, kode, nama FROM wilayah ORDER BY kode ASC")->fetch_all(MYSQLI_ASSOC);
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Pemasok Baru</h6>
    </div>
    <div class="card-body">
        <form action="pemasok_simpan.php" method="post" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="nama">Nama Pemasok</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="100" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="wilayah_id">Wilayah</label>
                    <select class="form-control select2" id="wilayah_id" name="wilayah_id" style="width: 100%;" required>
                        <option value="">-- Pilih Wilayah --</option>
                        <?php foreach ($wilayahList as $wilayah): ?>
                            <option value="<?= (int)$wilayah['id'] ?>">
                                <?= htmlspecialchars($wilayah['kode'] . ' - ' . $wilayah['nama'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="telepon">Telepon</label>
                    <input type="text" class="form-control" id="telepon" name="telepon" maxlength="30">
                </div>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="bank">Bank</label>
                    <input type="text" class="form-control" id="bank" name="bank" maxlength="50" placeholder="contoh: BRI">
                </div>
                <div class="form-group col-md-4">
                    <label for="nomor_rekening">Nomor Rekening</label>
                    <input type="text" class="form-control" id="nomor_rekening" name="nomor_rekening" maxlength="30" inputmode="numeric">
                </div>
                <div class="form-group col-md-5">
                    <label for="atas_nama_rekening">Atas Nama Rekening</label>
                    <input type="text" class="form-control" id="atas_nama_rekening" name="atas_nama_rekening" maxlength="100">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="pemasok_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>