<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, kode, nama, saldo_normal, saldo_awal FROM akun WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$akun = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$akun) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data akun tidak ada.'];
    header('Location: akun_index.php');
    exit;
}

$page_title = "Edit Akun";
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ubah Data Akun</h6>
    </div>
    <div class="card-body">
        <form action="akun_update.php" method="post" autocomplete="off">
            <input type="hidden" name="id" value="<?= (int)$akun['id'] ?>">

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="kode">Kode Akun</label>
                    <input type="text" class="form-control" id="kode" name="kode" maxlength="20"
                           value="<?= htmlspecialchars($akun['kode'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>
                <div class="form-group col-md-8">
                    <label for="nama">Nama Akun</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="100"
                           value="<?= htmlspecialchars($akun['nama'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="saldo_normal">Saldo Normal</label>
                    <select class="form-control" id="saldo_normal" name="saldo_normal" required>
                        <option value="Debit" <?= $akun['saldo_normal'] === 'Debit' ? 'selected' : '' ?>>Debit</option>
                        <option value="Kredit" <?= $akun['saldo_normal'] === 'Kredit' ? 'selected' : '' ?>>Kredit</option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="saldo_awal">Saldo Awal</label>
                    <input type="number" class="form-control" id="saldo_awal" name="saldo_awal" step="0.01"
                           value="<?= htmlspecialchars($akun['saldo_awal'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
            <a href="akun_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>