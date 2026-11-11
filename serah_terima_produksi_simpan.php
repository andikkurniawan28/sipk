<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: serah_terima_produksi_index.php');
    exit;
}

$kode       = trim($_POST['kode'] ?? '');
$tanggal    = trim($_POST['tanggal'] ?? '');
$gudang_id  = (int)($_POST['gudang_id'] ?? 0);
$kode_pb    = strtoupper(trim($_POST['pengambilan_bahan_kode'] ?? ''));
$user_id    = $_SESSION['sipk-user_id'];
$produk_ids = $_POST['produk_id'] ?? [];
$qtys       = $_POST['qty'] ?? [];

if (empty($kode) || empty($tanggal) || $gudang_id <= 0 || $kode_pb === '' || empty($produk_ids) || !is_array($produk_ids)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Data tidak lengkap.'];
    header('Location: serah_terima_produksi_tambah.php');
    exit;
}

// Gabungkan produk yang sama, abaikan baris kosong / qty <= 0
$qtyPerProduk = [];
foreach ($produk_ids as $i => $p) {
    $produk_id = (int)$p;
    $qty       = (float)($qtys[$i] ?? 0);
    if ($produk_id <= 0 || $qty <= 0) continue;
    $qtyPerProduk[$produk_id] = ($qtyPerProduk[$produk_id] ?? 0.0) + $qty;
}
ksort($qtyPerProduk); // urutan kunci konsisten, mencegah deadlock

if (empty($qtyPerProduk)) {
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => 'Item produk tidak ditemukan.'];
    header('Location: serah_terima_produksi_tambah.php');
    exit;
}

$akunBarangJadiId = 14; // 103.04 Persediaan Barang Jadi (Debit)
$akunWipId        = 13; // 103.03 Barang Dalam Proses (Kredit)

$conn->begin_transaction();

try {
    // 1. Validasi nomor pengambilan bahan & ambil total HPP bahan dari kolom `total`
    $stmtPb = $conn->prepare("SELECT id, total FROM pengambilan_bahan WHERE kode = ? LIMIT 1");
    $stmtPb->bind_param('s', $kode_pb);
    $stmtPb->execute();
    $pb = $stmtPb->get_result()->fetch_assoc();
    $stmtPb->close();

    if (!$pb) {
        throw new Exception('Nomor pengambilan bahan tidak valid.');
    }
    $pb_id = (int)$pb['id'];
    $totalWipBatch = (float)($pb['total'] ?? 0); // Total nilai bahan awal di WIP

    // 2. Cek akumulasi nilai yang SUDAH pernah diserahterimakan sebelumnya untuk PB ini
    $stmtCekSudah = $conn->prepare("SELECT SUM(total) as total_sudah_disetor FROM serah_terima_produksi WHERE pengambilan_bahan_id = ?");
    $stmtCekSudah->bind_param('i', $pb_id);
    $stmtCekSudah->execute();
    $resSudah = $stmtCekSudah->get_result()->fetch_assoc();
    $stmtCekSudah->close();

    $sudahDisetor     = (float)($resSudah['total_sudah_disetor'] ?? 0);
    $sisaWipTersedia  = $totalWipBatch - $sudahDisetor;

    if ($sisaWipTersedia <= 0) {
        throw new Exception('Semua hasil produksi untuk pengambilan bahan ' . $kode_pb . ' sudah tuntas diserahterimakan sebelumnya.');
    }

    // 3. Ambil Harga Jual Standar dari tabel produk untuk menghitung proporsi nilai pasar relatif
    $stmtProduk = $conn->prepare("SELECT nama, harga_jual FROM produk WHERE id = ? FOR UPDATE");
    $itemsTemp  = [];
    $totalNilaiPasarInput = 0.0;

    foreach ($qtyPerProduk as $produk_id => $qty) {
        $stmtProduk->bind_param('i', $produk_id);
        $stmtProduk->execute();
        $produk = $stmtProduk->get_result()->fetch_assoc();

        if (!$produk) {
            throw new Exception('Produk tidak ditemukan (ID produk: ' . $produk_id . ').');
        }

        $hargaJual = (float)$produk['harga_jual']; // Gunakan harga jual acuan untuk bobot alokasi
        if ($hargaJual <= 0) {
            throw new Exception('Harga jual acuan untuk "' . $produk['nama'] . '" belum diisi di master produk.');
        }

        $nilaiPasarItem = $qty * $hargaJual;
        $totalNilaiPasarInput += $nilaiPasarItem;
        
        $itemsTemp[] = [
            'produk_id'  => $produk_id, 
            'qty'        => $qty, 
            'harga_jual' => $hargaJual,
            'nilai_pasar'=> $nilaiPasarItem
        ];
    }
    $stmtProduk->close();

    if ($totalNilaiPasarInput <= 0) {
        throw new Exception('Total nilai pasar produk serah terima tidak valid.');
    }

    // 4. Hitung alokasi HPP secara proporsional parsial terhadap sisa WIP yang tersedia
    $alokasiWipSesiIni = min($totalNilaiPasarInput, $sisaWipTersedia);
    
    $grandTotalSerahTerima = 0.0;
    $items = [];

    foreach ($itemsTemp as $it) {
        $proporsiItem = $it['nilai_pasar'] / $totalNilaiPasarInput;
        $totalHppItem = round($proporsiItem * $alokasiWipSesiIni, 2);
        $hppSatuan    = round($totalHppItem / $it['qty'], 2);

        $grandTotalSerahTerima += $totalHppItem;
        
        $items[] = [
            'produk_id' => $it['produk_id'],
            'qty'       => $it['qty'],
            'harga'     => $hppSatuan,   // HPP per unit hasil hitung proporsional
            'total'     => $totalHppItem
        ];
    }
    $grandTotalSerahTerima = round($grandTotalSerahTerima, 2);

    // 5. Header Serah Terima Produksi
    $stmt = $conn->prepare("INSERT INTO serah_terima_produksi (pengambilan_bahan_id, tanggal, kode, gudang_id, user_id, total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issiid', $pb_id, $tanggal, $kode, $gudang_id, $user_id, $grandTotalSerahTerima);
    $stmt->execute();
    $serah_id = $stmt->insert_id;
    $stmt->close();

    // 6. Detail + Mutasi Stok Masuk + Update Harga Pokok di Tabel Produk
    $stmtDet        = $conn->prepare("INSERT INTO serah_terima_produksi_detail (serah_terima_produksi_id, produk_id, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
    $stmtMutasi     = $conn->prepare("INSERT INTO mutasi_stok (serah_terima_produksi_id, tanggal, keterangan, gudang_id, produk_id, masuk, keluar, user_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
    $stmtUpdateHpp  = $conn->prepare("UPDATE produk SET harga_pokok = ? WHERE id = ?");
    
    $ket = "Serah Terima Produksi: " . $kode;

    foreach ($items as $it) {
        // Simpan detail serah terima
        $stmtDet->bind_param('iiddd', $serah_id, $it['produk_id'], $it['qty'], $it['harga'], $it['total']);
        $stmtDet->execute();

        // Catat mutasi stok masuk
        $stmtMutasi->bind_param('issiidi', $serah_id, $tanggal, $ket, $gudang_id, $it['produk_id'], $it['qty'], $user_id);
        $stmtMutasi->execute();

        // Update harga_pokok terbaru di tabel produk berdasarkan hasil perhitungan alokasi HPP satuan terkini
        $stmtUpdateHpp->bind_param('di', $it['harga'], $it['produk_id']);
        $stmtUpdateHpp->execute();
    }
    
    $stmtDet->close();
    $stmtMutasi->close();
    $stmtUpdateHpp->close();

    // 7. Buku Besar: Dr Persediaan Barang Jadi / Cr WIP sejumlah alokasi parsial ini
    $ketBB  = "Serah Terima Produksi (Barang Jadi Masuk) {$kode} / Ref. {$kode_pb}";
    $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, serah_terima_produksi_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $nol = 0.0;
    // Debit Barang Jadi
    $stmtBB->bind_param('siiisdd', $tanggal, $serah_id, $akunBarangJadiId, $user_id, $ketBB, $grandTotalSerahTerima, $nol);
    $stmtBB->execute();

    // Kredit WIP (sebesar porsi parsial sesi ini saja)
    $stmtBB->bind_param('siiisdd', $tanggal, $serah_id, $akunWipId, $user_id, $ketBB, $nol, $grandTotalSerahTerima);
    $stmtBB->execute();
    $stmtBB->close();

    $conn->commit();
    $_SESSION['flash'] = ['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data serah terima produksi parsial & update harga pokok berhasil disimpan.'];
    header('Location: serah_terima_produksi_index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash'] = ['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()];
    header('Location: serah_terima_produksi_tambah.php');
    exit;
}