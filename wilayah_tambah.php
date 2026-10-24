<?php
$page_title = "Tambah Wilayah";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Wilayah Baru</h6>
    </div>
    <div class="card-body">
        <form action="wilayah_simpan.php" method="post" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="kode">Kode</label>
                    <input type="text" class="form-control text-uppercase" id="kode" name="kode"
                           minlength="2" maxlength="2" pattern="[A-Za-z0-9]{2}"
                           title="Tepat 2 karakter: huruf atau angka" placeholder="AA" required autofocus>
                    <small class="form-text text-muted">Tepat 2 karakter, harus unik.</small>
                </div>
                <div class="form-group col-md-9">
                    <label for="nama">Nama Wilayah</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="100" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="wilayah_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>