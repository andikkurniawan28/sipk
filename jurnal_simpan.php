<?php
include 'config.php';

// Pastikan diakses via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Akses Ditolak',
        'text'  => 'Metode akses tidak valid.'
    ];
    header('Location: jurnal_index.php');
    exit;
}

// Ambil dan bersihkan data dari form
$tanggal    = trim((string)($_POST['tanggal'] ?? ''));
$kode       = trim((string)($_POST['kode'] ?? ''));
$keterangan = trim((string)($_POST['keterangan'] ?? ''));
$user_id    = 1; // Ganti sesuai session user yang login, misal: $_SESSION['user_id'] ?? 1

// Validasi Tanggal
$d = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$d || $d->format('Y-m-d') !== $tanggal) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Validasi Gagal',
        'text'  => 'Format tanggal tidak valid.'
    ];
    header('Location: jurnal_tambah.php');
    exit;
}

// Validasi Kode Jurnal
if ($kode === '') {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Validasi Gagal',
        'text'  => 'Kode jurnal wajib diisi.'
    ];
    header('Location: jurnal_tambah.php');
    exit;
}

// Ambil Data Detail (Akun, Debit, Kredit)
$akun_ids = $_POST['akun_id'] ?? [];
$debets   = $_POST['debit'] ?? [];
$kredits  = $_POST['kredit'] ?? [];

if (!is_array($akun_ids) || !is_array($debets) || !is_array($kredits)) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Validasi Gagal',
        'text'  => 'Struktur data detail jurnal tidak valid.'
    ];
    header('Location: jurnal_tambah.php');
    exit;
}

$items       = [];
$totalDebit  = 0.0;
$totalKredit = 0.0;

foreach ($akun_ids as $i => $akun_id) {
    $aid = (int)$akun_id;
    if ($aid <= 0) {
        continue; // Lewati baris kosong
    }

    $deb = (float)($debets[$i] ?? 0);
    $kre = (float)($kredits[$i] ?? 0);

    if ($deb < 0 || $kre < 0) {
        $_SESSION['flash'] = [
            'icon'  => 'error',
            'title' => 'Validasi Gagal',
            'text'  => 'Nilai debit atau kredit tidak boleh bernilai negatif.'
        ];
        header('Location: jurnal_tambah.php');
        exit;
    }

    // Pastikan dalam satu baris tidak diisi dua-duanya secara non-zero secara tidak wajar, 
    // atau biarkan sesuai input jika diizinkan. Di sini kita catat nilainya.
    $items[] = [
        'akun_id' => $aid,
        'debit'   => round($deb, 2),
        'kredit'  => round($kre, 2)
    ];

    $totalDebit  += $deb;
    $totalKredit += $kre;
}

if (empty($items)) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Validasi Gagal',
        'text'  => 'Minimal harus ada satu baris detail akun jurnal.'
    ];
    header('Location: jurnal_tambah.php');
    exit;
}

// Validasi Balance (Total Debit harus sama persis dengan Total Kredit)
$totalDebit  = round($totalDebit, 2);
$totalKredit = round($totalKredit, 2);

if ($totalDebit !== $totalKredit) {
    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Jurnal Tidak Balance',
        'text'  => 'Total Debit (' . number_format($totalDebit, 2, ',', '.') . ') harus sama dengan Total Kredit (' . number_format($totalKredit, 2, ',', '.') . ').'
    ];
    header('Location: jurnal_tambah.php');
    exit;
}

$keteranganBukuBesar = "Jurnal Umum - {$kode} - {$keterangan}.";

// Mulai Database Transaksi
$conn->begin_transaction();

try {
    // 1. Simpan Header Jurnal
    $stmt = $conn->prepare("INSERT INTO jurnal (tanggal, kode, user_id, keterangan) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssis', $tanggal, $kode, $user_id, $keterangan);
    $stmt->execute();
    $jurnal_id = $conn->insert_id;
    $stmt->close();

    // 2. Simpan Detail Jurnal (Multi-row insert untuk performa optimal)
    $n = count($items);
    $sqlDetail = "INSERT INTO jurnal_detail (jurnal_id, akun_id, debit, kredit) VALUES " . implode(',', array_fill(0, $n, '(?,?,?,?)'));
    $params = [];
    foreach ($items as $it) {
        array_push($params, $jurnal_id, $it['akun_id'], $it['debit'], $it['kredit']);
    }

    $stmtDetail = $conn->prepare($sqlDetail);
    $stmtDetail->bind_param(str_repeat('iidd', $n), ...$params);
    $stmtDetail->execute();
    $stmtDetail->close();

    // 3. Posting Otomatis ke Buku Besar
    foreach ($items as $it) {
        $stmtBB = $conn->prepare("INSERT INTO buku_besar (tanggal, jurnal_id, akun_id, user_id, keterangan, debit, kredit) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtBB->bind_param('siiisdd', $tanggal, $jurnal_id, $it['akun_id'], $user_id, $keteranganBukuBesar, $it['debit'], $it['kredit']);
        $stmtBB->execute();
        $stmtBB->close();
    }

    // Commit Transaksi jika semua Berhasil
    $conn->commit();

    $_SESSION['flash'] = [
        'icon'  => 'success',
        'title' => 'Berhasil',
        'text'  => 'Data jurnal berhasil disimpan dan diposting ke buku besar.'
    ];
    header('Location: jurnal_index.php');
    exit;

} catch (Throwable $e) {
    // Rollback jika terjadi kesalahan database
    $conn->rollback();

    $_SESSION['flash'] = [
        'icon'  => 'error',
        'title' => 'Gagal Menyimpan',
        'text'  => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ];
    header('Location: jurnal_tambah.php');
    exit;
}