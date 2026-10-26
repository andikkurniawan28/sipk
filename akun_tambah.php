<?php
$page_title = "Tambah Akun";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Akun Baru</h6>
    </div>
    <div class="card-body">
        <form action="akun_simpan.php" method="post" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="kode">Kode Akun</label>
                    <input type="text" class="form-control" id="kode" name="kode" maxlength="20" placeholder="contoh: 1-1110" required autofocus>
                </div>
                <div class="form-group col-md-8">
                    <label for="nama">Nama Akun</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="100" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="kelompok">Kelompok</label>
                    <select class="form-control" id="kelompok" name="kelompok" required>
                        <option value="" disabled selected>-- Pilih kelompok --</option>
                        <option value="Aset">Aset</option>
                        <option value="Kewajiban">Kewajiban</option>
                        <option value="Ekuitas">Ekuitas</option>
                        <option value="Pendapatan">Pendapatan</option>
                        <option value="Beban">Beban</option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="saldo_normal">Saldo Normal</label>
                    <select class="form-control" id="saldo_normal" name="saldo_normal" required>
                        <option value="Debit">Debit</option>
                        <option value="Kredit">Kredit</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="akun_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>