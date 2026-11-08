<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: serah_terima_produksi_index.php');
    exit;
}

$kode        = trim($_POST['kode'] ?? '');
$tanggal     = trim($_POST['tanggal'] ?? '');
$gudang_id   = (int)($_POST['gudang_id'] ?? 0);
$pb_id       = (int)($_POST['pengambilan_bahan_id'] ?? 0);
$user_id     = $_SESSION['sipk-user_id'];
$produk_ids  = $_POST['produk_id'] ?? [];
$qtys        = $_POST['qty'] ?? [];
$hargas      = $_POST['harga'] ?? [];

if (empty($kode) || empty($tanggal) || $gudang_id <= 0 || $pb_id <= 0 || empty($produk_ids)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data tidak lengkap.'];
    header('Location: serah_terima_produksi_tambah.php');
    exit;
}

$conn->begin_transaction();

try {
    // 1. Ambil nilai pengambilan bahan (kunci baris) + cek belum dipakai
    $stmtPb = $conn->prepare("SELECT total FROM pengambilan_bahan WHERE id = ? FOR UPDATE");
    $stmtPb->bind_param('i', $pb_id);
    $stmtPb->execute();
    $rowPb = $stmtPb->get_result()->fetch_assoc();
    $stmtPb->close();

    if (!$rowPb) {
        throw new Exception('Pengambilan bahan tidak ditemukan.');
    }
    $nilaiBahan = round((float)$rowPb['total'], 2);

    $stmtCek = $conn->prepare("SELECT id FROM serah_terima_produksi WHERE pengambilan_bahan_id = ?");
    $stmtCek->bind_param('i', $pb_id);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows > 0) {
        throw new Exception('Pengambilan bahan ini sudah pernah diserahterimakan.');
    }
    $stmtCek->close();

    // 2. Kumpulkan item valid & hitung bobot alokasi
    $items = [];
    $totalBobot = 0;
    for ($i = 0; $i < count($produk_ids); $i++) {
        $produk_id = (int)$produk_ids[$i];
        $qty       = (float)$qtys[$i];
        $harga     = (float)($hargas[$i] ?? 0);
        if ($produk_id <= 0 || $qty <= 0) continue;

        // Bobot: nilai input (qty x harga); jika harga kosong, pakai qty
        $bobot = ($harga > 0) ? $qty * $harga : $qty;
        $items[] = ['produk_id' => $produk_id, 'qty' => $qty, 'bobot' => $bobot];
        $totalBobot += $bobot;
    }

    if (empty($items) || $totalBobot <= 0) {
        throw new Exception('Tidak ada item produk yang valid.');
    }

    // 3. Simpan header
    $stmt = $conn->prepare("INSERT INTO serah_terima_produksi (tanggal, kode, gudang_id, user_id, pengambilan_bahan_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiiid', $tanggal, $kode, $gudang_id, $user_id, $pb_id, $nilaiBahan);
    $stmt->execute();
    $serah_id = $stmt->insert_id;
    $stmt->close();

    // 4. Detail + mutasi stok, nilai dialokasikan dari nilai bahan (HPP)
    $sisa  = $nilaiBahan;
    $jumlahItem = count($items);
    foreach ($items as $idx => $it) {
        // Item terakhir mengambil sisa agar total persis = nilai bahan
        if ($idx === $jumlahItem - 1) {
            $total = $sisa;
        } else {
            $total = round($nilaiBahan * ($it['bobot'] / $totalBobot), 2);
            $sisa -= $total;
        }
        $harga = round($total / $it['qty'], 2); // HPP per unit

        $stmtDet = $conn->prepare("INSERT INTO serah_terima_produksi_detail (serah_terima_produksi_id, produk_id, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
        $stmtDet->bind_param('iiddd', $serah_id, $it['produk_id'], $it['qty'], $harga, $total);
        $stmtDet->execute();
        $stmtDet->close();

        $stmtMutasi = $conn->prepare("INSERT INTO mutasi_stok (serah_terima_produksi_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
        $ket = "Serah Terima Produksi: " . $kode;
        $stmtMutasi->bind_param('issiidi', $serah_id, $tanggal, $ket, $gudang_id, $it['produk_id'], $it['qty'], $user_id);
        $stmtMutasi->execute();
        $stmtMutasi->close();

        // Update harga pokok produk dengan HPP per unit hasil alokasi
        $stmtHpp = $conn->prepare("UPDATE produk SET harga_pokok = ? WHERE id = ?");
        $stmtHpp->bind_param('di', $harga, $it['produk_id']);
        $stmtHpp->execute();
        $stmtHpp->close();
    }

    // 5. Buku besar: nilainya = nilai bahan yang diambil
    $ketBB = "Serah Terima Produksi (Barang Jadi Masuk): " . $kode;

    // Debit: Persediaan Barang Jadi [14]
    $stmtBB1 = $conn->prepare("INSERT INTO buku_besar (tanggal, serah_terima_produksi_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, 14, ?, ?, ?, 0)");
    $stmtBB1->bind_param('siisd', $tanggal, $serah_id, $user_id, $ketBB, $nilaiBahan);
    $stmtBB1->execute();
    $stmtBB1->close();

    // Kredit: Barang Dalam Proses (WIP) [13]
    $stmtBB2 = $conn->prepare("INSERT INTO buku_besar (tanggal, serah_terima_produksi_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, 13, ?, ?, 0, ?)");
    $stmtBB2->bind_param('siisd', $tanggal, $serah_id, $user_id, $ketBB, $nilaiBahan);
    $stmtBB2->execute();
    $stmtBB2->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data serah terima produksi berhasil disimpan.'];
    header('Location: serah_terima_produksi_index.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Kesalahan Sistem', 'text' => $e->getMessage()];
    header('Location: serah_terima_produksi_tambah.php');
    exit;
}