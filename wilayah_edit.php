<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, kode, nama FROM wilayah WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$wilayah = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$wilayah) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data wilayah tidak ada.'];
    header('Location: wilayah_index.php');
    exit;
}

$page_title = "Edit Wilayah";
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ubah Data Wilayah</h6>
    </div>
    <div class="card-body">
        <form action="wilayah_update.php" method="post" autocomplete="off">
            <input type="hidden" name="id" value="<?= (int)$wilayah['id'] ?>">

            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="kode">Kode</label>
                    <input type="text" class="form-control text-uppercase" id="kode" name="kode"
                           minlength="2" maxlength="2" pattern="[A-Za-z0-9]{2}"
                           title="Tepat 2 karakter: huruf atau angka"
                           value="<?= htmlspecialchars($wilayah['kode'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                    <small class="form-text text-muted">Tepat 2 karakter, harus unik.</small>
                </div>
                <div class="form-group col-md-9">
                    <label for="nama">Nama Wilayah</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="100"
                           value="<?= htmlspecialchars($wilayah['nama'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
            <a href="wilayah_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>