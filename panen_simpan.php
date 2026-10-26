<?php
include 'config.php';
require_once 'panen_helper.php';

function flash_dan_pindah(string $icon, string $title, string $text, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: ' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panen_index.php');
    exit;
}

// Kembalikan ke form dengan isian lama agar tidak perlu mengetik ulang
function gagal_kembali(string $pesan): void
{
    $_SESSION['sipk-old-panen'] = $_POST;
    flash_dan_pindah('error', 'Gagal', $pesan, 'panen_tambah.php');
}

[$data, $error] = panen_ambil_input($_POST);
if ($error !== null) {
    gagal_kembali($error);
}

$user_id = (int)($_SESSION['sipk-user_id'] ?? 0);

/**
 * Fungsi untuk mencatat jurnal akuntansi dan buku besar transaksi panen dengan rincian lengkap
 */
/**
 * Fungsi untuk mencatat akuntansi langsung ke buku besar transaksi panen dengan rincian lengkap
 */
function panen_simpan_akuntansi(mysqli $conn, int $panen_id, array $data, int $user_id): void
{
    $grand_total = (float)$data['grand_total'];
    if ($grand_total <= 0) {
        return; // Tidak ada nilai finansial yang perlu dicatat
    }

    $tanggal  = $data['tanggal'];
    $subtotal = (float)($data['subtotal'] ?? 0);
    $pajak    = (float)($data['pajak'] ?? 0);
    $ongkir   = (float)($data['ongkir'] ?? 0);
    $biaya    = (float)($data['biaya'] ?? 0);
    $rafaksi  = (float)($data['rafaksi'] ?? 0);
    $diskon   = (float)($data['diskon'] ?? 0);
    $total_potongan = $rafaksi + $diskon;

    // Ambil Nama Petani untuk Keterangan
    $stmtPetani = $conn->prepare("SELECT nama FROM petani WHERE id = ? LIMIT 1");
    $stmtPetani->bind_param('i', $data['petani_id']);
    $stmtPetani->execute();
    $resPetani = $stmtPetani->get_result()->fetch_assoc();
    $stmtPetani->close();
    $namaPetani = $resPetani['nama'] ?? 'Tanpa Nama';

    $keterangan = "Pembelian Panen - " . $namaPetani;

    // Ambil ID Akun dari database berdasarkan kodenya
    $stmtAkun = $conn->prepare("SELECT id, kode FROM akun WHERE kode IN ('2-1120', '5-2100', '6-1200', '6-1900', '2-1220', '5-2110')");
    $stmtAkun->execute();
    $resAkun = $stmtAkun->get_result();
    $akunMap = [];
    while ($row = $resAkun->fetch_assoc()) {
        $akunMap[$row['kode']] = (int)$row['id'];
    }
    $stmtAkun->close();

    $akun_utang_id     = $akunMap['2-1120'] ?? null;
    $akun_pembelian_id = $akunMap['5-2100'] ?? null;
    $akun_ongkir_id    = $akunMap['6-1200'] ?? null;
    $akun_biaya_id     = $akunMap['6-1900'] ?? null;
    $akun_pajak_id     = $akunMap['2-1220'] ?? null;
    $akun_potongan_id  = $akunMap['5-2110'] ?? null;

    if (!$akun_utang_id || !$akun_pembelian_id) {
        throw new Exception("Akun utama Utang Usaha (2-1110) atau Pembelian Bahan Baku (5-2100) tidak ditemukan di database.");
    }

    // Susun Rincian Baris Buku Besar beserta akun_id-nya
    $detailEntries = [];

    // --- POSISI DEBIT ---
    if ($subtotal > 0) {
        $detailEntries[] = ['akun_id' => $akun_pembelian_id, 'debit' => $subtotal, 'kredit' => 0, 'ket' => '[Debet] ' . $keterangan . ' (Pembelian Kotor)'];
    }
    if ($ongkir > 0 && $akun_ongkir_id) {
        $detailEntries[] = ['akun_id' => $akun_ongkir_id, 'debit' => $ongkir, 'kredit' => 0, 'ket' => '[Debet] ' . $keterangan . ' (Ongkir)'];
    }
    if ($biaya > 0 && $akun_biaya_id) {
        $detailEntries[] = ['akun_id' => $akun_biaya_id, 'debit' => $biaya, 'kredit' => 0, 'ket' => '[Debet] ' . $keterangan . ' (Biaya Lain)'];
    }
    if ($pajak > 0 && $akun_pajak_id) {
        $detailEntries[] = ['akun_id' => $akun_pajak_id, 'debit' => $pajak, 'kredit' => 0, 'ket' => '[Debet] ' . $keterangan . ' (Pajak)'];
    }

    // --- POSISI KREDIT ---
    if ($grand_total > 0 && $akun_utang_id) {
        $detailEntries[] = ['akun_id' => $akun_utang_id, 'debit' => 0, 'kredit' => $grand_total, 'ket' => '[Kredit] ' . $keterangan . ' (Utang Petani)'];
    }
    if ($total_potongan > 0 && $akun_potongan_id) {
        $detailEntries[] = ['akun_id' => $akun_potongan_id, 'debit' => 0, 'kredit' => $total_potongan, 'ket' => '[Kredit] ' . $keterangan . ' (Rafaksi & Diskon)'];
    }

    // Eksekusi Insert langsung ke tabel `buku_besar` dengan menyertakan akun_id
    $stmtBukuBesar = $conn->prepare("
        INSERT INTO buku_besar (tanggal, akun_id, panen_id, jurnal_id, keterangan, debit, kredit, user_id) 
        VALUES (?, ?, ?, NULL, ?, ?, ?, ?)
    ");

    foreach ($detailEntries as $entry) {
        $stmtBukuBesar->bind_param(
            'siisddi',
            $tanggal, $entry['akun_id'], $panen_id, $entry['ket'], $entry['debit'], $entry['kredit'], $user_id
        );
        $stmtBukuBesar->execute();
    }

    $stmtBukuBesar->close();
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        INSERT INTO panen
            (tanggal, kode, petani_id, subtotal, pajak, ongkir, biaya, rafaksi, diskon, grand_total, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'ssidddddddi',
        $data['tanggal'], $data['kode'], $data['petani_id'],
        $data['subtotal'], $data['pajak'], $data['ongkir'], $data['biaya'],
        $data['rafaksi'], $data['diskon'], $data['grand_total'],
        $user_id
    );
    $stmt->execute();
    $panen_id = (int)$conn->insert_id;
    $stmt->close();

    // Simpan detail produk panen
    panen_simpan_detail($conn, $panen_id, $data['items']);

    // Simpan pencatatan akuntansi (Jurnal & Buku Besar)
    panen_simpan_akuntansi($conn, $panen_id, $data, $user_id);

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ((int)$e->getCode() === 1062) {
        gagal_kembali('Kode panen sudah dipakai, gunakan kode lain.');
    }
    if ((int)$e->getCode() === 1452) {
        gagal_kembali('Petani, atau produk yang dipilih tidak valid.');
    }
    gagal_kembali('Data tidak dapat disimpan: ' . $e->getMessage());
} catch (Throwable $e) {
    $conn->rollback();
    gagal_kembali('Data tidak dapat disimpan: ' . $e->getMessage());
}

flash_dan_pindah('success', 'Berhasil', 'Panen ' . $data['kode'] . ' berhasil disimpan.', 'panen_index.php');