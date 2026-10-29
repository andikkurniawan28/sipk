<?php
$page_title = "Tambah Kebutuhan Pelanggan";
include 'config.php';
include 'header.php';

// Ambil data pelanggan untuk dropdown
$pelanggan = $conn->query("SELECT id, nama FROM pelanggan ORDER BY nama ASC");

// Ambil data produk untuk dropdown
$produk = $conn->query("SELECT id, nama, satuan, kode FROM produk ORDER BY nama ASC");
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Kebutuhan Pelanggan Baru</h6>
    </div>
    <div class="card-body">
        <form action="kebutuhan_simpan.php" method="post" autocomplete="off">
            <div class="form-group">
                <label for="pelanggan_id">Pelanggan</label>
                <select class="form-control select2" id="pelanggan_id" name="pelanggan_id" required autofocus>
                    <option value=""></option>
                    <?php while ($p = $pelanggan->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="produk_id">Produk</label>
                <select class="form-control select2" id="produk_id" name="produk_id" required>
                    <option value=""></option>
                    <?php while ($pr = $produk->fetch_assoc()): ?>
                        <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['kode'] . ' - ' . $pr['nama'] . ' (' . $pr['satuan'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="qty">Qty (Jumlah)</label>
                <input type="number" step="0.01" class="form-control" id="qty" name="qty" value="0" min="0" required>
            </div>
            <div class="form-group">
                <label for="keterangan">Keterangan</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="kebutuhan_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>