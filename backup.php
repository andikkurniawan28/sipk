<?php
/**
 * backup.php
 * Backup database manual -> file .sql (atau .sql.gz) langsung diunduh ke perangkat client.
 *
 * Dipanggil dari halaman Setting:  backup.php?mulai=1  (tambah &gz=1 untuk kompres)
 * Tabel dibaca otomatis (SHOW TABLES), jadi tabel baru ikut ter-backup tanpa ubah script.
 *
 * Catatan: session_start() sudah ada di config.php, jadi tidak ditulis lagi di sini.
 */
include 'config.php';

/* ---------- Pengaturan (silakan sesuaikan) ---------- */
const CHUNK_BARIS      = 1000; // baris yang dibaca per query
const BARIS_PER_INSERT = 500;  // baris per statement INSERT di file backup
const JEDA_MS          = 20;   // jeda antar chunk (milidetik) agar database sempat "bernapas"

/* ---------- Autentikasi ---------- */
if (empty($_SESSION)) {
    http_response_code(401);
    exit('Unauthorized');
}
// Disarankan hanya untuk Direktur (file backup berisi hash password & data keuangan).
// Aktifkan jika session Anda menyimpan role, sesuaikan key-nya:
// if (($_SESSION['role'] ?? '') !== 'Direktur') { http_response_code(403); exit('Forbidden'); }

// Lepas kunci session. Tanpa ini, selama backup berjalan semua halaman lain milik
// user yang sama akan "menunggu" sampai download selesai.
session_write_close();

// Cegah terpicu tidak sengaja (prefetch, buka URL langsung, dsb.)
if (($_GET['mulai'] ?? '') !== '1') {
    header('Location: setting.php');
    exit;
}

@set_time_limit(0);
ignore_user_abort(false); // kalau client membatalkan download, proses ikut berhenti

$pakaiGz = !empty($_GET['gz']) && function_exists('deflate_init');

/* ---------- Kunci anti-ganda: hanya 1 backup berjalan pada satu waktu ---------- */
$adaKunci = (int) $conn->query("SELECT GET_LOCK('backup_manual_db', 0)")->fetch_row()[0];
if ($adaKunci !== 1) {
    http_response_code(423);
    exit('Backup lain sedang berjalan. Silakan coba lagi beberapa menit lagi.');
}

/* ---------- Siapkan output streaming ---------- */
while (ob_get_level() > 0) {
    ob_end_clean();
}
@ini_set('zlib.output_compression', '0');
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

$namaDb = $conn->query('SELECT DATABASE()')->fetch_row()[0] ?? 'database';
$namaFile = 'backup_' . preg_replace('/[^A-Za-z0-9_-]/', '', $namaDb) . '_' . date('Ymd_His') . ($pakaiGz ? '.sql.gz' : '.sql');

header('Content-Type: ' . ($pakaiGz ? 'application/gzip' : 'application/sql; charset=utf-8'));
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Accel-Buffering: no'); // nginx: jangan di-buffer

$gz = $pakaiGz ? deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]) : null;

function tulis(string $s): void
{
    global $gz;
    if ($gz) {
        $s = deflate_add($gz, $s, ZLIB_NO_FLUSH);
    }
    if ($s !== '') {
        echo $s;
    }
}

function q(string $nama): string
{
    return '`' . str_replace('`', '``', $nama) . '`';
}

$transaksiAktif = false;

try {
    $conn->set_charset('utf8mb4');
    // Samakan zona waktu agar kolom TIMESTAMP konsisten saat restore (sama seperti mysqldump --tz-utc)
    $conn->query("SET time_zone = '+00:00'");

    // Snapshot konsisten: seluruh tabel dibaca pada "foto" waktu yang sama, TANPA mengunci tabel.
    // Transaksi lain (input penjualan, dll.) tetap bisa jalan normal selama backup berlangsung.
    $conn->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $conn->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    $transaksiAktif = true;

    // Daftar tabel (tanpa VIEW)
    $daftarTabel = [];
    $r = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $r->fetch_row()) {
        $daftarTabel[] = $row[0];
    }
    $r->free();

    tulis("-- Backup database: {$namaDb}\n");
    tulis('-- Dibuat: ' . date('Y-m-d H:i:s') . "\n");
    tulis('-- Jumlah tabel: ' . count($daftarTabel) . "\n\n");
    tulis("SET NAMES utf8mb4;\n");
    tulis("SET time_zone = '+00:00';\n");
    tulis("SET FOREIGN_KEY_CHECKS = 0;\n");
    tulis("SET UNIQUE_CHECKS = 0;\n");
    tulis("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

    $totalBaris = 0;

    foreach ($daftarTabel as $tabel) {
        $t = q($tabel);

        // ----- Struktur -----
        $buat = $conn->query("SHOW CREATE TABLE $t")->fetch_row()[1];
        tulis("-- ----------------------------\n-- Tabel: {$tabel}\n-- ----------------------------\n");
        tulis("DROP TABLE IF EXISTS $t;\n$buat;\n\n");

        // ----- Kolom -----
        $kolom   = [];
        $idxId   = null;
        $rc      = $conn->query("SHOW COLUMNS FROM $t");
        while ($c = $rc->fetch_assoc()) {
            if ($c['Field'] === 'id' && $c['Key'] === 'PRI') {
                $idxId = count($kolom);
            }
            $kolom[] = q($c['Field']);
        }
        $rc->free();
        $daftarKolom = implode(',', $kolom);

        // ----- Data (per chunk) -----
        $lastId = 0;
        $offset = 0;

        while (true) {
            // Keyset pagination: pakai index primary key, tidak makin lambat di halaman belakang
            // (berbeda dengan LIMIT ... OFFSET yang harus membaca ulang semua baris sebelumnya).
            $sql = $idxId !== null
                ? "SELECT * FROM $t WHERE id > $lastId ORDER BY id LIMIT " . CHUNK_BARIS
                : "SELECT * FROM $t LIMIT " . CHUNK_BARIS . " OFFSET $offset"; // cadangan bila tabel tak punya id

            $res = $conn->query($sql);
            if ($res->num_rows === 0) {
                $res->free();
                break;
            }

            $batch = [];
            $n     = 0;
            while ($row = $res->fetch_row()) {
                $n++;
                if ($idxId !== null) {
                    $lastId = (int) $row[$idxId];
                }
                $nilai = [];
                foreach ($row as $v) {
                    $nilai[] = $v === null ? 'NULL' : "'" . $conn->real_escape_string((string) $v) . "'";
                }
                $batch[] = '(' . implode(',', $nilai) . ')';

                if (count($batch) >= BARIS_PER_INSERT) {
                    tulis("INSERT INTO $t ($daftarKolom) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                tulis("INSERT INTO $t ($daftarKolom) VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            $res->free();

            $offset     += $n;
            $totalBaris += $n;

            flush(); // kirim ke browser sekarang, jangan ditumpuk di memori server

            if (connection_aborted()) {
                throw new RuntimeException('Dibatalkan oleh client.');
            }
            usleep(JEDA_MS * 1000); // beri napas ke database
        }
        tulis("\n");
    }

    tulis("SET FOREIGN_KEY_CHECKS = 1;\n");
    tulis("SET UNIQUE_CHECKS = 1;\n\n");
    tulis("-- Backup selesai. Total baris: {$totalBaris}\n");

    if ($gz) {
        echo deflate_add($gz, '', ZLIB_FINISH);
    }
    flush();

} catch (Throwable $e) {
    // Header sudah terkirim, jadi tandai di dalam file bahwa backup TIDAK lengkap.
    // (File yang lengkap selalu diakhiri baris "-- Backup selesai".)
    tulis("\n-- BACKUP GAGAL / TIDAK LENGKAP: " . str_replace(["\r", "\n"], ' ', $e->getMessage()) . "\n");
    if ($gz) {
        echo deflate_add($gz, '', ZLIB_FINISH);
    }
    flush();
} finally {
    if ($transaksiAktif) {
        @$conn->query('ROLLBACK'); // hanya baca, tidak ada yang perlu di-commit
    }
    @$conn->query("SELECT RELEASE_LOCK('backup_manual_db')");
}
exit;