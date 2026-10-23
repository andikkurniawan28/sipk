<?php
include 'config.php';

$id = (int)($_GET['id'] ?? 0);

// Hash password tidak diambil sama sekali
$stmt = $conn->prepare("SELECT id, nama, username, role, aktif FROM `user` WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect harus sebelum ada output HTML (header.php)
if (!$user) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Tidak ditemukan', 'text' => 'Data user tidak ada.'];
    header('Location: user_index.php');
    exit;
}

$page_title = "Edit User";
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ubah Data User</h6>
    </div>
    <div class="card-body">
        <form action="user_update.php" method="post" autocomplete="off">
            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama" name="nama" maxlength="100"
                       value="<?= htmlspecialchars($user['nama'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" maxlength="50"
                           pattern="[A-Za-z0-9_.]{3,50}" title="3-50 karakter: huruf, angka, titik, atau underscore"
                           value="<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="role">Role</label>
                    <input type="text" class="form-control" id="role" name="role" maxlength="30" list="daftar-role"
                           value="<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>" required>
                    <datalist id="daftar-role">
                        <option value="Direktur">
                        <option value="Admin">
                        <option value="Staf">
                    </datalist>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="password">Password Baru</label>
                    <input type="password" class="form-control" id="password" name="password" minlength="6" autocomplete="new-password">
                    <small class="form-text text-muted">Kosongkan jika tidak ingin mengganti password.</small>
                </div>
                <div class="form-group col-md-6">
                    <label for="password2">Ulangi Password Baru</label>
                    <input type="password" class="form-control" id="password2" name="password2" minlength="6" autocomplete="new-password">
                </div>
            </div>
            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="aktif" name="aktif" value="1"
                           <?= (int)$user['aktif'] === 1 ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="aktif">Aktif</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
            <a href="user_index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>