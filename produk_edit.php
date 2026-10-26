<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, kode, nama, satuan, harga_beli, harga_jual FROM produk WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$produk) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data produk tidak ada.'];
    header('Location: produk_index.php');
    exit;
}

$page_title = "Edit Produk";
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ubah Data Produk</h6>
    </div>
    <div class="card-body">
        <form action="produk_update.php" method="post" autocomplete="off">
            <input type="hidden" name="id" value="<?= (int)$produk['id'] ?>">

            <div class="form-group">
                <label for="nama">Kode Produk</label>
                <input type="text" class="form-control" id="kode" name="kode" maxlength="3"
                       value="<?= htmlspecialchars($produk['kode'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="nama">Nama Produk</label>
                <input type="text" class="form-control" id="nama" name="nama" maxlength="100"
                       value="<?= htmlspecialchars($produk['nama'], ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="form-group">
                <label for="satuan">Satuan</label>
                <input type="text" class="form-control" id="satuan" name="satuan" maxlength="20"
                       value="<?= htmlspecialchars($produk['satuan'], ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="harga_beli">Harga Beli</label>
                    <input type="number" class="form-control" id="harga_beli" name="harga_beli" min="0" step="0.01"
                           value="<?= htmlspecialchars($produk['harga_beli'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="harga_jual">Harga Jual</label>
                    <input type="number" class="form-control" id="harga_jual" name="harga_jual" min="0" step="0.01"
                           value="<?= htmlspecialchars($produk['harga_jual'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
            <a href="produk_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>