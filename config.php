<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. Definisikan Halaman Bebas (Tanpa Login)
$halamanBebas = ['login.php', 'login_proses.php'];
$halamanIni   = basename($_SERVER['SCRIPT_NAME'] ?? '');

// 2. Cek Status Login
if (PHP_SAPI !== 'cli' && empty($_SESSION['sipk-user_id']) && !in_array($halamanIni, $halamanBebas, true)) {
    $_SESSION['flash'] = [
        'icon'  => 'warning',
        'title' => 'Akses Ditolak',
        'text'  => 'Silakan login terlebih dahulu.'
    ];
    header('Location: login.php');
    exit;
}

// 3. Koneksi Database
$host     = "localhost";
$username = "root";
$password = "";
$database = "sipk";

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 4. Definisi Hak Akses Role (Direktur menggunakan '*' untuk akses penuh)
$accessControl = [
    'Direktur' => ['*'], // Akses ke SEMUA halaman
    'Tanaman' => [
        'index.php',
        'logout.php',
        'wilayah_index.php',
        'wilayah_tambah.php',
        'wilayah_simpan.php',
        'petani_index.php',
        'petani_tambah.php',
        'petani_simpan.php',
        'proposal_index.php',
        'proposal_data.php',
        'proposal_tambah.php',
        'proposal_simpan.php',
        'proposal_lihat.php',
        'proposal_status.php',
        'panen_index.php',
        'panen_data.php',
        'panen_tambah.php',
        'panen_simpan.php',
        'panen_lihat.php',
        'kartu_stok_index.php',
        'kartu_stok_proses.php',
    ],
    'Penjualan' => [
        'index.php',
        'logout.php',
        'produk_index.php',
        'produk_tambah.php',
        'produk_simpan.php',
        'pelanggan_index.php',
        'pelanggan_tambah.php',
        'pelanggan_simpan.php',
        'kebutuhan_index.php',
        'kebutuhan_tambah.php',
        'kebutuhan_simpan.php',
        'komplain_index.php',
        'komplain_tambah.php',
        'komplain_simpan.php',
        'komplain_lihat.php',
        'kartu_stok_index.php',
        'kartu_stok_proses.php',
    ],
    'Produksi' => [
        'index.php',
        'logout.php',
        'preparasi_index.php',
        'preparasi_data.php',
        'preparasi_tambah.php',
        'preparasi_simpan.php',
        'preparasi_lihat.php',
        'kartu_stok_index.php',
        'kartu_stok_proses.php',
    ],
    'Logistik' => [
        'index.php',
        'logout.php',
        'gudang_index.php',
        'gudang_tambah.php',
        'gudang_simpan.php',
        'penerimaan_index.php',
        'penerimaan_data.php',
        'penerimaan_tambah.php',
        'penerimaan_simpan.php',
        'penerimaan_get_referensi.php',
        'penerimaan_get_detail.php',
        'penerimaan_lihat.php',
        'pengeluaran_index.php',
        'pengeluaran_data.php',
        'pengeluaran_tambah.php',
        'pengeluaran_simpan.php',
        'pengeluaran_get_referensi.php',
        'pengeluaran_get_detail.php',
        'pengeluaran_lihat.php',
        'kartu_stok_index.php',
        'kartu_stok_proses.php',
    ],
];

// 5. Validasi Hak Akses Berdasarkan Role
if (PHP_SAPI !== 'cli' && !in_array($halamanIni, $halamanBebas, true)) {
    $userRole = $_SESSION['sipk-role'] ?? '';
    
    $isAllowed = false;
    if (isset($accessControl[$userRole])) {
        // Jika role memiliki tanda '*' atau file ada di dalam array akses role tersebut
        if ($accessControl[$userRole] === ['*'] || in_array($halamanIni, $accessControl[$userRole], true)) {
            $isAllowed = true;
        }
    }

    // Jika tidak punya akses
    if (!$isAllowed) {
        $_SESSION['flash'] = [
            'icon'  => 'error',
            'title' => 'Akses Ditolak!',
            'text'  => 'Anda tidak memiliki permission untuk mengakses fitur tersebut.'
        ];
        
        // Ambil URL halaman sebelumnya dari HTTP_REFERER
        $redirectUrl = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Validasi agar tidak terjadi redirect loop
        $parsedUrl = parse_url($redirectUrl, PHP_URL_PATH);
        $refererFile = $parsedUrl ? basename($parsedUrl) : '';
        
        // Cek apakah referer valid dan bisa diakses oleh role tersebut
        $isRefererAllowed = false;
        if (!empty($refererFile)) {
            if (isset($accessControl[$userRole])) {
                if ($accessControl[$userRole] === ['*'] || in_array($refererFile, $accessControl[$userRole], true)) {
                    $isRefererAllowed = true;
                }
            }
        }

        // Jika referer kosong, sama dengan halaman saat ini, atau tidak boleh diakses, fallback ke index.php
        if (empty($refererFile) || $refererFile === $halamanIni || !$isRefererAllowed) {
            $redirectUrl = 'index.php'; 
        }

        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Fungsi helper untuk mengecek apakah role yang sedang login punya akses ke suatu halaman
 */
function canAccess($pageName) {
    global $accessControl;
    $userRole = $_SESSION['sipk-role'] ?? '';

    if (!isset($accessControl[$userRole])) {
        return false;
    }

    // Jika role memiliki akses penuh (*) atau file ada di dalam array akses role tersebut
    if ($accessControl[$userRole] === ['*'] || in_array($pageName, $accessControl[$userRole], true)) {
        return true;
    }

    return false;
}

function tanggal_indo(string $tanggal): string
{
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = strtotime($tanggal);
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function rp($angka): string
{
    return number_format((float)$angka, 2, ',', '.');
}

function h($teks): string { return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8'); }