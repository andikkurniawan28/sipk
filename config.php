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
    'Manajer Kemitraan' => [
        'index.php',
        'logout.php',
        'petani_index.php',
        'petani_tambah.php',
        'petani_simpan.php',
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