<?php

// Fungsi bantu modul pengolahan.
// Dipakai oleh: pengolahan_tambah, pengolahan_edit, pengolahan_simpan, pengolahan_update.

function pg_h($teks): string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

/**
 * Kode otomatis: PG + yymmdd + "-" + nomor urut 3 digit, mis. PG261023-001.
 * Hanya usulan awal; kolom kode di form tetap bisa diubah.
 */
function pg_kode_baru(mysqli $conn, string $tanggal): string
{
    $prefix = 'PG' . date('ymd', strtotime($tanggal));
    $like   = $prefix . '-%';

    $stmt = $conn->prepare("SELECT kode FROM pengolahan WHERE kode LIKE ? ORDER BY kode DESC LIMIT 1");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $baris = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $urut = $baris ? ((int)substr($baris['kode'], -3) + 1) : 1;
    return sprintf('%s-%03d', $prefix, $urut);
}

/**
 * Baca satu bagian baris (input atau output) dari form.
 * $awalan: 'in_' atau 'out_'. $label: 'input' atau 'output' (untuk pesan error).
 * Total per baris dihitung ulang di server (qty x harga).
 *
 * @return array [items|null, pesanError|null]
 */
function pg_baca_baris(array $post, string $awalan, string $label): array
{
    $pid   = $post[$awalan . 'produk_id'] ?? [];
    $gid   = $post[$awalan . 'gudang_id'] ?? [];
    $qty   = $post[$awalan . 'qty'] ?? [];
    $harga = $post[$awalan . 'harga'] ?? [];

    if (!is_array($pid) || !is_array($gid) || !is_array($qty) || !is_array($harga)) {
        return [null, 'Data ' . $label . ' tidak valid.'];
    }

    $items = [];
    foreach ($pid as $i => $p) {
        $produk_id = (int)$p;
        if ($produk_id <= 0) {
            continue; // baris kosong dilewati
        }

        $gudang_id = (int)($gid[$i] ?? 0);
        if ($gudang_id <= 0) {
            return [null, 'Gudang wajib dipilih untuk setiap item ' . $label . '.'];
        }

        $q = trim((string)($qty[$i] ?? ''));
        $h = trim((string)($harga[$i] ?? ''));
        if (!is_numeric($q) || (float)$q <= 0) {
            return [null, 'Qty setiap item ' . $label . ' harus lebih dari 0.'];
        }
        if (!is_numeric($h) || (float)$h < 0) {
            return [null, 'Harga setiap item ' . $label . ' harus berupa angka dan tidak boleh negatif.'];
        }

        $q = round((float)$q, 2);
        $h = round((float)$h, 2);

        $items[] = [
            'produk_id' => $produk_id,
            'gudang_id' => $gudang_id,
            'qty'       => $q,
            'harga'     => $h,
            'total'     => round($q * $h, 2),
        ];
    }

    if (!$items) {
        return [null, 'Minimal satu item ' . $label . ' harus diisi.'];
    }

    return [$items, null];
}

/**
 * Validasi input form pengolahan.
 * Header hanya berisi tanggal, kode, dan keterangan. Tidak ada subtotal/grand total yang disimpan:
 * nilai input (persediaan keluar) dan output (persediaan masuk) dihitung dari SUM(total) tabel detail.
 *
 * @return array [data|null, pesanError|null]
 */
function pg_ambil_input(array $post): array
{
    $tanggal = trim((string)($post['tanggal'] ?? ''));
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$d || $d->format('Y-m-d') !== $tanggal) {
        return [null, 'Tanggal tidak valid.'];
    }

    $kode = trim((string)($post['kode'] ?? ''));
    if ($kode === '') {
        return [null, 'Kode pengolahan wajib diisi.'];
    }
    if (mb_strlen($kode) > 30) {
        return [null, 'Kode pengolahan maksimal 30 karakter.'];
    }

    $keterangan = trim((string)($post['keterangan'] ?? ''));
    $keterangan = $keterangan === '' ? null : $keterangan;

    [$input, $err] = pg_baca_baris($post, 'in_', 'input');
    if ($err !== null) {
        return [null, $err];
    }

    [$output, $err] = pg_baca_baris($post, 'out_', 'output');
    if ($err !== null) {
        return [null, $err];
    }

    return [[
        'tanggal'    => $tanggal,
        'kode'       => $kode,
        'keterangan' => $keterangan,
        'input'      => $input,
        'output'     => $output,
    ], null];
}

/**
 * Simpan semua baris detail dengan SATU query INSERT multi-row (tanpa perulangan query).
 * $tabel hanya boleh input_pengolahan atau output_pengolahan. Harus dipanggil di dalam transaksi.
 */
function pg_simpan_detail(mysqli $conn, string $tabel, int $pengolahan_id, array $items): void
{
    if (!in_array($tabel, ['input_pengolahan', 'output_pengolahan'], true)) {
        throw new InvalidArgumentException('Tabel detail tidak dikenal.');
    }

    $n   = count($items);
    $sql = "INSERT INTO $tabel (pengolahan_id, produk_id, gudang_id, qty, harga, total) VALUES "
         . implode(',', array_fill(0, $n, '(?,?,?,?,?,?)'));

    $params = [];
    foreach ($items as $it) {
        array_push($params, $pengolahan_id, $it['produk_id'], $it['gudang_id'], $it['qty'], $it['harga'], $it['total']);
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('iiiddd', $n), ...$params);
    $stmt->execute();
    $stmt->close();
}

/**
 * Susun ulang isi form dari input sebelumnya (dipakai saat validasi gagal,
 * supaya yang sudah diketik tidak hilang). Disimpan di $_SESSION['sipk-old-pengolahan'].
 *
 * @return array [dataPengolahan, barisInput, barisOutput]
 */
function pg_dari_old(array $old): array
{
    $pengolahan = [];
    foreach (['tanggal', 'kode', 'keterangan'] as $f) {
        if (isset($old[$f]) && !is_array($old[$f])) {
            $pengolahan[$f] = $old[$f];
        }
    }

    $bagian = function (string $awalan) use ($old): array {
        $pid   = is_array($old[$awalan . 'produk_id'] ?? null) ? $old[$awalan . 'produk_id'] : [];
        $gid   = is_array($old[$awalan . 'gudang_id'] ?? null) ? $old[$awalan . 'gudang_id'] : [];
        $qty   = is_array($old[$awalan . 'qty'] ?? null) ? $old[$awalan . 'qty'] : [];
        $harga = is_array($old[$awalan . 'harga'] ?? null) ? $old[$awalan . 'harga'] : [];

        $ambil = static function ($nilai) {
            return is_array($nilai) ? '' : $nilai;
        };

        $baris = [];
        foreach ($pid as $i => $p) {
            $baris[] = [
                'produk_id' => $ambil($p),
                'gudang_id' => $ambil($gid[$i] ?? ''),
                'qty'       => $ambil($qty[$i] ?? ''),
                'harga'     => $ambil($harga[$i] ?? ''),
            ];
        }
        return $baris;
    };

    return [$pengolahan, $bagian('in_'), $bagian('out_')];
}