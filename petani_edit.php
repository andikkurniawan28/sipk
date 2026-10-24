<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, kode, wilayah_id, nama, alamat, telepon FROM petani WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$petani = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$petani) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data petani tidak ada.'];
    header('Location: petani_index.php');
    exit;
}

// Ambil data wilayah untuk dropdown
$wilayahList = $conn->query("SELECT id, kode, nama FROM wilayah ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);

$page_title = "Edit Petani";
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ubah Data Petani</h6>
    </div>
    <div class="card-body">
        <form action="petani_update.php" method="post" autocomplete="off">
            <input type="hidden" name="id" value="<?= (int)$petani['id'] ?>">

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="kode">Kode Petani</label>
                    <input type="text" class="form-control" id="kode" value="<?= htmlspecialchars($petani['kode'], ENT_QUOTES, 'UTF-8') ?>" readonly disabled>
                </div>
                <div class="form-group col-md-8">
                    <label for="nama">Nama Petani</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="100"
                           value="<?= htmlspecialchars($petani['nama'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="wilayah_id">Wilayah</label>
                    <select class="form-control select2" id="wilayah_id" name="wilayah_id" style="width: 100%;" required>
                        <option value="">-- Pilih Wilayah --</option>
                        <?php foreach ($wilayahList as $wilayah): ?>
                            <option value="<?= (int)$wilayah['id'] ?>" <?= ($wilayah['id'] == $petani['wilayah_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wilayah['kode'] . ' - ' . $wilayah['nama'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="telepon">Telepon</label>
                    <input type="text" class="form-control" id="telepon" name="telepon" maxlength="30"
                           value="<?= htmlspecialchars((string)$petani['telepon'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars((string)$petani['alamat'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
            <a href="petani_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>