<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, nama, alamat, telepon, keterangan, status FROM pelanggan WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$pelanggan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$pelanggan) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data pelanggan tidak ada.'];
    header('Location: pelanggan_index.php');
    exit;
}

$page_title = "Edit Pelanggan";
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ubah Data Pelanggan</h6>
    </div>
    <div class="card-body">
        <form action="pelanggan_update.php" method="post" autocomplete="off">
            <input type="hidden" name="id" value="<?= (int)$pelanggan['id'] ?>">

            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="nama">Nama Pelanggan</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="100"
                           value="<?= htmlspecialchars($pelanggan['nama'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>
                <div class="form-group col-md-4">
                    <label for="telepon">Telepon</label>
                    <input type="text" class="form-control" id="telepon" name="telepon" maxlength="30"
                           value="<?= htmlspecialchars((string)$pelanggan['telepon'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars((string)$pelanggan['alamat'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="form-group">
                <label for="alamat">Keterangan</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?= htmlspecialchars((string)$pelanggan['keterangan'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control select2" id="status" name="status" required>
                    <option value=""></option>
                    <option value="Prospek" <?php echo ($pelanggan['status'] === 'Prospek') ? 'selected' : ''; ?>>Prospek</option>
                    <option value="Penawaran" <?php echo ($pelanggan['status'] === 'Penawaran') ? 'selected' : ''; ?>>Penawaran</option>
                    <option value="Closing" <?php echo ($pelanggan['status'] === 'Closing') ? 'selected' : ''; ?>>Closing</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
            <a href="pelanggan_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>