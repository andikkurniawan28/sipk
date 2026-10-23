<?php
$page_title = "Tambah Komplain Pelanggan";
include 'config.php';
include 'header.php';

$tanggalHariIni = date('Y-m-d');
$cekKode = $conn->query("SELECT MAX(id) AS max_id FROM komplain")->fetch_assoc();
$nextId = ((int)($cekKode['max_id'] ?? 0)) + 1;
$kodeOtomatis = 'KMP-' . date('Ymd') . '-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

$pelanggan = $conn->query("SELECT id, nama FROM pelanggan ORDER BY nama ASC");
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Komplain Baru</h6>
    </div>
    <div class="card-body">
        <form action="komplain_simpan.php" method="post" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= $tanggalHariIni ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="kode">Kode Komplain</label>
                    <input type="text" class="form-control" id="kode" name="kode" value="<?= $kodeOtomatis ?>" maxlength="30" required>
                </div>
            </div>
            <div class="form-group">
                <label for="pelanggan_id">Pelanggan</label>
                <select class="form-control select2" id="pelanggan_id" name="pelanggan_id" required>
                    <option value=""></option>
                    <?php while ($p = $pelanggan->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="keterangan">Keterangan Komplain</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="komplain_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>