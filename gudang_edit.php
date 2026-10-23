<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, nama, alamat FROM gudang WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$gudang = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$gudang) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data gudang tidak ada.'];
    header('Location: gudang_index.php');
    exit;
}

$page_title = "Edit Gudang";
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ubah Data Gudang</h6>
    </div>
    <div class="card-body">
        <form action="gudang_update.php" method="post" autocomplete="off">
            <input type="hidden" name="id" value="<?= (int)$gudang['id'] ?>">

            <div class="form-group">
                <label for="nama">Nama Gudang</label>
                <input type="text" class="form-control" id="nama" name="nama" maxlength="100"
                       value="<?= htmlspecialchars($gudang['nama'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars((string)$gudang['alamat'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
            <a href="gudang_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>