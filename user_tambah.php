<?php
$page_title = "Tambah User";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form User Baru</h6>
    </div>
    <div class="card-body">
        <form action="user_simpan.php" method="post" autocomplete="off">
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama" name="nama" maxlength="100" required autofocus>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" maxlength="50"
                           pattern="[A-Za-z0-9_.]{3,50}" title="3-50 karakter: huruf, angka, titik, atau underscore" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="role">Role</label>
                    <select class="form-control select2" id="role" name="role" required>
                        <option value="" disabled selected>-- Pilih Role --</option>
                        <option value="Direktur">Direktur</option>
                        <option value="Tanaman">Tanaman</option>
                        <option value="Logistik">Logistik</option>
                        <option value="Keuangan">Keuangan</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" minlength="6" autocomplete="new-password" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="password2">Ulangi Password</label>
                    <input type="password" class="form-control" id="password2" name="password2" minlength="6" autocomplete="new-password" required>
                </div>
            </div>
            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="aktif" name="aktif" value="1" checked>
                    <label class="custom-control-label" for="aktif">Aktif</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="user_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>