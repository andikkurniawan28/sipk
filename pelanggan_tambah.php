<?php
$page_title = "Tambah Pelanggan";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Pelanggan Baru</h6>
    </div>
    <div class="card-body">
        <form action="pelanggan_simpan.php" method="post" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="nama">Nama Pelanggan</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="100" required autofocus>
                </div>
                <div class="form-group col-md-4">
                    <label for="telepon">Telepon</label>
                    <input type="text" class="form-control" id="telepon" name="telepon" maxlength="30">
                </div>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="alamat">Keterangan</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control select2" id="status" name="status" required>
                    <option value=""></option>
                    <option value="Prospek">Prospek</option>
                    <option value="Penawaran">Penawaran</option>
                    <option value="Closing">Closing</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="pelanggan_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>