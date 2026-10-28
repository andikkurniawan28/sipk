<?php
$page_title = "Tambah Produk";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Produk Baru</h6>
    </div>
    <div class="card-body">
        <form action="produk_simpan.php" method="post" autocomplete="off">
            <div class="form-group">
                <label for="nama">Kode Produk</label>
                <input type="text" class="form-control" id="kode" name="kode" maxlength="3" required autofocus>
            </div>
            <div class="form-group">
                <label for="nama">Nama Produk</label>
                <input type="text" class="form-control" id="nama" name="nama" maxlength="100" required>
            </div>
            <div class="form-group">
                <label for="satuan">Satuan</label>
                <input type="text" class="form-control" id="satuan" name="satuan" maxlength="20" placeholder="kg, liter, pcs" required>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="harga_beli">Harga Beli</label>
                    <input type="number" class="form-control" id="harga_beli" name="harga_beli" min="0" step="any" value="0" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="harga_pokok">Harga Pokok</label>
                    <input type="number" class="form-control" id="harga_pokok" name="harga_pokok" min="0" step="any" value="0" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="harga_jual">Harga Jual</label>
                    <input type="number" class="form-control" id="harga_jual" name="harga_jual" min="0" step="any" value="0" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="produk_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>