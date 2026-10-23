<?php
$page_title = "Edit Komplain Pelanggan";
include 'config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'ID komplain tidak valid.'];
    header('Location: komplain_index.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM komplain WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$komplain = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$komplain) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data komplain tidak ditemukan.'];
    header('Location: komplain_index.php');
    exit;
}

$pelanggan = $conn->query("SELECT id, nama FROM pelanggan ORDER BY nama ASC");

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Form Edit Komplain</h6>
    </div>
    <div class="card-body">
        <form action="komplain_update.php" method="post" autocomplete="off">
            <input type="hidden" name="id" value="<?= $komplain['id'] ?>">

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= $komplain['tanggal'] ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="kode">Kode Komplain</label>
                    <input type="text" class="form-control" id="kode" name="kode" value="<?= htmlspecialchars($komplain['kode'], ENT_QUOTES, 'UTF-8') ?>" maxlength="30" required>
                </div>
            </div>
            <div class="form-group">
                <label for="pelanggan_id">Pelanggan</label>
                <select class="form-control select2" id="pelanggan_id" name="pelanggan_id" required>
                    <option value=""></option>
                    <?php while ($p = $pelanggan->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>" <?= ($p['id'] == $komplain['pelanggan_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="keterangan">Keterangan Komplain</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="4" required><?= htmlspecialchars((string)$komplain['keterangan'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
            <a href="komplain_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>