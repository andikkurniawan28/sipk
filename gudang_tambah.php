<?php
$page_title = "Tambah Gudang";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Gudang Baru</h6>
    </div>
    <div class="card-body">
        <form action="gudang_simpan.php" method="post" autocomplete="off">
            <div class="form-group">
                <label for="nama">Nama Gudang</label>
                <input type="text" class="form-control" id="nama" name="nama" maxlength="100" required autofocus>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="gudang_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>