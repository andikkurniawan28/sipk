<?php

const TOLERANSI_SELISIH_DESIMAL = 0.005;
const BATAS_BARIS_DATATABLES = 1000;

const NAMA_BULAN = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const KOLOM_SUMBER_BUKU_BESAR = [
    'pembelian_langsung_id',
    'penerimaan_pembelian_langsung_id',
    'pembayaran_pembelian_langsung_id',
    'pengambilan_bahan_id',
    'serah_terima_produksi_id',
    'penjualan_id',
    'pengiriman_produk_id',
    'pembayaran_penjualan_id',
    'penyesuaian_stok_id',
    'jurnal_id',
];


function bukaSessionJikaBelumAda(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function sedangDijalankanDariCli(): bool
{
    return PHP_SAPI === 'cli';
}

function namaHalamanSaatIni(): string
{
    return basename($_SERVER['SCRIPT_NAME'] ?? '');
}

function hubungkanDatabase(string $host, string $username, string $password, string $database): mysqli
{
    try {
        return mysqli_connect($host, $username, $password, $database);
    } catch (mysqli_sql_exception $e) {
        die('Koneksi database gagal: ' . $e->getMessage());
    }
}


function idUserLogin(): int
{
    return (int)($_SESSION['sipk-user_id'] ?? 0);
}

function roleLogin(): string
{
    return (string)($_SESSION['sipk-role'] ?? '');
}

function daftarHakAkses(): array
{
    $halamanUmum = ['index.php', 'api_dashboard.php', 'logout.php'];

    return [
        'Direktur' => ['*'],

        'Tanaman' => array_merge($halamanUmum, [
            'wilayah_index.php',
            'wilayah_tambah.php',
            'wilayah_simpan.php',
            'pemasok_index.php',
            'pemasok_tambah.php',
            'pemasok_simpan.php',
            'pembelian_langsung_index.php',
            'pembelian_langsung_data.php',
            'pembelian_langsung_tambah.php',
            'pembelian_langsung_simpan.php',
            'pembelian_langsung_lihat.php',
        ]),

        'Logistik' => array_merge($halamanUmum, [
            'gudang_index.php',
            'gudang_tambah.php',
            'gudang_simpan.php',
            'penerimaan_pembelian_langsung_index.php',
            'penerimaan_pembelian_langsung_data.php',
            'penerimaan_pembelian_langsung_tambah.php',
            'pembelian_langsung_get_detail.php',
            'penerimaan_pembelian_langsung_simpan.php',
            'penerimaan_pembelian_langsung_lihat.php',
            'pengambilan_bahan_index.php',
            'pengambilan_bahan_data.php',
            'pengambilan_bahan_tambah.php',
            'pengambilan_bahan_simpan.php',
            'pengambilan_bahan_lihat.php',
            'get_harga_produk.php',
            'serah_terima_produksi_index.php',
            'serah_terima_produksi_data.php',
            'serah_terima_produksi_tambah.php',
            'serah_terima_produksi_simpan.php',
            'serah_terima_produksi_lihat.php',
            'pengiriman_produk_index.php',
            'pengiriman_produk_data.php',
            'pengiriman_produk_tambah.php',
            'pengiriman_produk_simpan.php',
            'pengiriman_produk_lihat.php',
            'get_penjualan_detail.php',
            'penyesuaian_stok_index.php',
            'penyesuaian_stok_data.php',
            'penyesuaian_stok_tambah.php',
            'penyesuaian_stok_simpan.php',
            'penyesuaian_stok_lihat.php',
            'kartu_stok_index.php',
            'kartu_stok_proses.php',
        ]),

        'Keuangan' => array_merge($halamanUmum, [
            'akun_index.php',
            'akun_tambah.php',
            'akun_simpan.php',
            'jurnal_index.php',
            'jurnal_data.php',
            'jurnal_tambah.php',
            'jurnal_simpan.php',
            'jurnal_lihat.php',
            'pembayaran_pembelian_langsung_index.php',
            'pembayaran_pembelian_langsung_data.php',
            'pembayaran_pembelian_langsung_tambah.php',
            'penerimaan_pembelian_langsung_get_total.php',
            'pembayaran_pembelian_langsung_simpan.php',
            'pembayaran_pembelian_langsung_lihat.php',
            'pembayaran_penjualan_index.php',
            'pembayaran_penjualan_data.php',
            'pembayaran_penjualan_tambah.php',
            'pembayaran_penjualan_simpan.php',
            'pembayaran_penjualan_lihat.php',
            'get_piutang_status.php',
            'buku_besar_index.php',
            'buku_besar_proses.php',
            'kartu_hutang_index.php',
            'kartu_hutang_proses.php',
            'kartu_piutang_index.php',
            'kartu_piutang_proses.php',
            'laporan_arus_kas_index.php',
            'laporan_arus_kas_proses.php',
            'neraca_index.php',
            'neraca_proses.php',
            'laporan_laba_rugi_index.php',
            'laporan_laba_rugi_proses.php',
        ]),

        'Penjualan' => array_merge($halamanUmum, [
            'produk_index.php',
            'produk_tambah.php',
            'produk_simpan.php',
            'pelanggan_index.php',
            'pelanggan_tambah.php',
            'pelanggan_simpan.php',
            'kebutuhan_index.php',
            'kebutuhan_tambah.php',
            'kebutuhan_simpan.php',
            'penjualan_index.php',
            'penjualan_data.php',
            'penjualan_tambah.php',
            'penjualan_simpan.php',
            'penjualan_lihat.php',
        ]),
    ];
}

function roleBolehMengakses(array $hakAkses, string $role, string $halaman): bool
{
    if (!isset($hakAkses[$role])) {
        return false;
    }

    return $hakAkses[$role] === ['*'] || in_array($halaman, $hakAkses[$role], true);
}

function canAccess(string $pageName): bool
{
    global $accessControl;

    return roleBolehMengakses($accessControl, roleLogin(), $pageName);
}

function namaFileDariReferer(): string
{
    $path = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH);

    return $path ? basename($path) : '';
}

function tujuanSetelahAksesDitolak(array $hakAkses, string $role, string $halamanIni): string
{
    $halamanSebelumnya = namaFileDariReferer();

    $bolehKembaliKeSana = $halamanSebelumnya !== ''
        && $halamanSebelumnya !== $halamanIni
        && roleBolehMengakses($hakAkses, $role, $halamanSebelumnya);

    return $bolehKembaliKeSana ? $_SERVER['HTTP_REFERER'] : 'index.php';
}

function pastikanSudahLogin(): void
{
    if (idUserLogin() > 0) {
        return;
    }

    alihkanDenganPesan('warning', 'Akses Ditolak', 'Silakan login terlebih dahulu.', 'login.php');
}

function pastikanRoleValid(array $hakAkses, string $role): void
{
    if (isset($hakAkses[$role])) {
        return;
    }

    unset($_SESSION['sipk-user_id'], $_SESSION['sipk-role']);

    alihkanDenganPesan('error', 'Role Tidak Valid', 'Role akun Anda belum diatur. Hubungi administrator.', 'login.php');
}

function pastikanRoleBolehMengakses(array $hakAkses, string $role, string $halaman): void
{
    if (roleBolehMengakses($hakAkses, $role, $halaman)) {
        return;
    }

    alihkanDenganPesan(
        'error',
        'Akses Ditolak!',
        'Anda tidak memiliki permission untuk mengakses fitur tersebut.',
        tujuanSetelahAksesDitolak($hakAkses, $role, $halaman)
    );
}


function tanggal_indo(string $tanggal): string
{
    $waktu = strtotime($tanggal);

    return date('j', $waktu) . ' ' . NAMA_BULAN[(int)date('n', $waktu)] . ' ' . date('Y', $waktu);
}

function rp($angka): string
{
    return number_format((float)$angka, 0, ',', '.');
}

function h($teks): string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function formatKapitalRapi(string $teks): string
{
    return ucwords(strtolower(str_replace('_', ' ', $teks)));
}


function alihkanDenganPesan(string $icon, string $judul, string $teks, string $tujuan): void
{
    $_SESSION['flash'] = ['icon' => $icon, 'title' => $judul, 'text' => $teks];
    header('Location: ' . $tujuan);
    exit;
}

function alihkanDenganPesanSukses(string $teks, string $tujuan): void
{
    alihkanDenganPesan('success', 'Berhasil', $teks, $tujuan);
}

function alihkanDenganPesanGagal(string $judul, string $teks, string $tujuan): void
{
    alihkanDenganPesan('error', $judul, $teks, $tujuan);
}


function pastikanRequestPost(string $tujuanJikaBukanPost): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        alihkanDenganPesanGagal('Akses Ditolak', 'Metode akses tidak valid.', $tujuanJikaBukanPost);
    }
}

function ambilTeksDariForm(array $form, string $namaField): string
{
    return trim((string)($form[$namaField] ?? ''));
}

function pastikanTidakKosong(string $nilai, string $namaField): void
{
    if ($nilai === '') {
        throw new DomainException("{$namaField} wajib diisi.");
    }
}

function pastikanFormatTanggalValid(string $tanggal): void
{
    $hasil = DateTime::createFromFormat('Y-m-d', $tanggal);

    if (!$hasil || $hasil->format('Y-m-d') !== $tanggal) {
        throw new DomainException('Format tanggal tidak valid.');
    }
}

function pastikanDebitKreditSeimbang(float $totalDebit, float $totalKredit): void
{
    if (abs($totalDebit - $totalKredit) >= TOLERANSI_SELISIH_DESIMAL) {
        throw new DomainException(
            'Jurnal tidak balance. Total Debit (' . number_format($totalDebit, 2, ',', '.')
            . ') harus sama dengan Total Kredit (' . number_format($totalKredit, 2, ',', '.') . ').'
        );
    }
}

function jumlahkanKolom(array $baris, string $kolom): float
{
    return round(array_sum(array_column($baris, $kolom)), 2);
}


function jalankanDalamTransaksi(mysqli $conn, callable $proses): void
{
    $conn->begin_transaction();

    try {
        $proses();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function insertBanyakBaris(mysqli $conn, string $tabel, array $kolom, string $tipeKolom, array $baris): void
{
    if (empty($baris)) {
        return;
    }

    $placeholderSatuBaris = '(' . implode(',', array_fill(0, count($kolom), '?')) . ')';
    $sql = "INSERT INTO {$tabel} (" . implode(', ', $kolom) . ') VALUES '
         . implode(',', array_fill(0, count($baris), $placeholderSatuBaris));

    $semuaNilai = array_merge(...array_map('array_values', $baris));

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat($tipeKolom, count($baris)), ...$semuaNilai);
    $stmt->execute();
    $stmt->close();
}

function postingKeBukuBesar(
    mysqli $conn,
    string $kolomSumber,
    int $idSumber,
    string $tanggal,
    int $userId,
    string $keterangan,
    array $barisAkun
): void {
    if (!in_array($kolomSumber, KOLOM_SUMBER_BUKU_BESAR, true)) {
        throw new InvalidArgumentException("Kolom sumber buku besar tidak dikenal: {$kolomSumber}");
    }

    $stmt = $conn->prepare(
        "INSERT INTO buku_besar (tanggal, {$kolomSumber}, akun_id, user_id, keterangan, debit, kredit)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($barisAkun as $akun) {
        $stmt->bind_param('siiisdd', $tanggal, $idSumber, $akun['akun_id'], $userId, $keterangan, $akun['debit'], $akun['kredit']);
        $stmt->execute();
    }

    $stmt->close();
}


function ambilIdDariUrl(string $namaParameter = 'id'): int
{
    return (int)($_GET[$namaParameter] ?? 0);
}

function pastikanIdValid(int $id, string $namaData): void
{
    if ($id <= 0) {
        throw new DomainException("ID {$namaData} tidak valid.");
    }
}

function hitungBaris(mysqli $conn, string $sql, string $tipeNilai = '', array $nilai = []): int
{
    $stmt = $conn->prepare($sql);

    if ($tipeNilai !== '') {
        $stmt->bind_param($tipeNilai, ...$nilai);
    }

    $stmt->execute();
    $jumlah = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();

    return $jumlah;
}

function polaCariLike(string $kataKunci): string
{
    return '%' . addcslashes($kataKunci, '%_\\') . '%';
}

function ambilParameterDataTables(array $kolomUrut, string $kolomUrutDefault): array
{
    $urut    = $_GET['order'][0] ?? [];
    $panjang = (int)($_GET['length'] ?? 10);

    return [
        'draw'      => (int)($_GET['draw'] ?? 1),
        'mulai'     => max(0, (int)($_GET['start'] ?? 0)),
        'panjang'   => $panjang > 0 ? min($panjang, BATAS_BARIS_DATATABLES) : BATAS_BARIS_DATATABLES,
        'cari'      => trim((string)($_GET['search']['value'] ?? '')),
        'urutKolom' => $kolomUrut[(int)($urut['column'] ?? -1)] ?? $kolomUrutDefault,
        'urutArah'  => strtolower((string)($urut['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC',
    ];
}

function kirimJsonDataTables(array $parameter, int $totalSemua, int $totalTersaring, array $barisData): void
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'draw'            => $parameter['draw'],
        'recordsTotal'    => $totalSemua,
        'recordsFiltered' => $totalTersaring,
        'data'            => $barisData,
    ]);
    exit;
}


function buatKodeBaru(mysqli $conn, string $tabel, string $awalan, ?string $tanggal = null): string
{
    if (!preg_match('/^[a-z_]+$/', $tabel)) {
        throw new InvalidArgumentException("Nama tabel tidak valid: {$tabel}");
    }

    $awalanDenganTanggal = $awalan . date('ymd', strtotime($tanggal ?? 'now'));
    $pola                = $awalanDenganTanggal . '%';

    $stmt = $conn->prepare("SELECT kode FROM {$tabel} WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('s', $pola);
    $stmt->execute();
    $kodeTerakhir = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $nomorUrut = $kodeTerakhir ? (int)substr($kodeTerakhir['kode'], -5) + 1 : 1;

    return $awalanDenganTanggal . str_pad($nomorUrut, 5, '0', STR_PAD_LEFT);
}


function ambilAngkaDariForm(array $form, string $namaField): float
{
    return (float)($form[$namaField] ?? 0);
}

function ambilIdDariForm(array $form, string $namaField): int
{
    return (int)($form[$namaField] ?? 0);
}

function pastikanTerpilih(int $id, string $namaField): void
{
    if ($id <= 0) {
        throw new DomainException("{$namaField} wajib dipilih.");
    }
}

function pastikanTidakNegatif(float $nilai, string $namaField): void
{
    if ($nilai < 0) {
        throw new DomainException("{$namaField} tidak boleh negatif.");
    }
}

function barisAkunDebit(int $akunId, float $nominal): array
{
    return ['akun_id' => $akunId, 'debit' => $nominal, 'kredit' => 0.0];
}

function barisAkunKredit(int $akunId, float $nominal): array
{
    return ['akun_id' => $akunId, 'debit' => 0.0, 'kredit' => $nominal];
}

function hanyaBarisBernilai(array $barisAkun): array
{
    return array_values(array_filter(
        $barisAkun,
        fn(array $baris): bool => $baris['debit'] > 0 || $baris['kredit'] > 0
    ));
}


function kelompokkanBerdasarkanKolom(iterable $baris, string $kolom): array
{
    $hasil = [];

    foreach ($baris as $satuBaris) {
        $hasil[$satuBaris[$kolom]][] = $satuBaris;
    }

    return $hasil;
}

function kondisiPencarian(array $kolomCari, string $kataKunci): array
{
    if ($kataKunci === '') {
        return ['', '', []];
    }

    $jumlahKolom      = count($kolomCari);
    $kondisiTiapKolom = array_map(fn(string $kolom): string => "{$kolom} LIKE ?", $kolomCari);

    return [
        'WHERE ' . implode(' OR ', $kondisiTiapKolom),
        str_repeat('s', $jumlahKolom),
        array_fill(0, $jumlahKolom, polaCariLike($kataKunci)),
    ];
}

function hitungSemuaBaris(mysqli $conn, array $tabel): int
{
    return hitungBaris($conn, 'SELECT COUNT(*) FROM ' . $tabel['tabelUtama']);
}

function hitungBarisTersaring(mysqli $conn, array $tabel, string $kataKunci): int
{
    [$kondisi, $tipeNilai, $nilai] = kondisiPencarian($tabel['kolomCari'], $kataKunci);

    return hitungBaris($conn, "SELECT COUNT(*) {$tabel['dari']} {$kondisi}", $tipeNilai, $nilai);
}

function ambilBarisHalaman(mysqli $conn, array $tabel, array $parameter): array
{
    [$kondisi, $tipeNilai, $nilai] = kondisiPencarian($tabel['kolomCari'], $parameter['cari']);

    $sql = "SELECT {$tabel['select']} {$tabel['dari']} {$kondisi} "
         . "ORDER BY {$parameter['urutKolom']} {$parameter['urutArah']}, {$tabel['kolomId']} DESC "
         . 'LIMIT ? OFFSET ?';

    $nilaiBind = [...$nilai, $parameter['panjang'], $parameter['mulai']];

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipeNilai . 'ii', ...$nilaiBind);
    $stmt->execute();
    $baris = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $baris;
}


bukaSessionJikaBelumAda();
date_default_timezone_set('Asia/Jakarta');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$halamanBebas      = ['login.php', 'login_proses.php'];
$halamanIni        = namaHalamanSaatIni();
$halamanDilindungi = !sedangDijalankanDariCli() && !in_array($halamanIni, $halamanBebas, true);

if ($halamanDilindungi) {
    pastikanSudahLogin();
}

$host     = 'localhost';
$username = 'root';
$password = '';
$database = 'sipk';
$conn     = hubungkanDatabase($host, $username, $password, $database);

$accessControl = daftarHakAkses();
$userRole      = roleLogin();

if ($halamanDilindungi) {
    pastikanRoleValid($accessControl, $userRole);
    pastikanRoleBolehMengakses($accessControl, $userRole, $halamanIni);
}