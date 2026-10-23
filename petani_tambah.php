<?php
$page_title = "Tambah Petani";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Petani Baru</h6>
    </div>
    <div class="card-body">
        <form action="petani_simpan.php" method="post" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="nama">Nama Petani</label>
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
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="petani_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>