<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, nama, alamat, telepon, keterangan FROM mitra WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$mitra = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$mitra) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data mitra tidak ada.'];
    header('Location: mitra_index.php');
    exit;
}

$page_title = "Edit Mitra";
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ubah Data Mitra</h6>
    </div>
    <div class="card-body">
        <form action="mitra_update.php" method="post" autocomplete="off">
            <input type="hidden" name="id" value="<?= (int)$mitra['id'] ?>">

            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="nama">Nama Mitra</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="100"
                           value="<?= htmlspecialchars($mitra['nama'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>
                <div class="form-group col-md-4">
                    <label for="telepon">Telepon</label>
                    <input type="text" class="form-control" id="telepon" name="telepon" maxlength="30"
                           value="<?= htmlspecialchars((string)$mitra['telepon'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars((string)$mitra['alamat'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="form-group">
                <label for="keterangan">Keterangan</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?= htmlspecialchars((string)$mitra['keterangan'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
            <a href="mitra_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>