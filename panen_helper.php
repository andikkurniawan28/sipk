<?php

// Fungsi bantu modul panen.
// Dipakai oleh: panen_tambah, panen_edit, panen_simpan, panen_update.

function panen_h($teks): string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

/**
 * Kode otomatis: PN + yymmdd + "-" + nomor urut 3 digit, mis. PN261023-001.
 * Hanya usulan awal; kolom kode di form tetap bisa diubah.
 */
function panen_kode_baru(mysqli $conn, string $tanggal): string
{
    $prefix = 'PANN-' . date('ymd') . '-';
    
    $stmt = $conn->prepare("SELECT kode FROM panen WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;

    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

/**
 * Validasi input form panen + hitung semua total di sisi server
 * (nilai total dari browser tidak dipercaya).
 *
 * Rumus: grand_total = subtotal + pajak + ongkir + biaya - rafaksi - diskon
 *
 * @return array [data|null, pesanError|null]
 */
function panen_ambil_input(array $post): array
{
    $tanggal = trim((string)($post['tanggal'] ?? ''));
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$d || $d->format('Y-m-d') !== $tanggal) {
        return [null, 'Tanggal tidak valid.'];
    }

    $kode = trim((string)($post['kode'] ?? ''));
    if ($kode === '') {
        return [null, 'Kode panen wajib diisi.'];
    }
    if (mb_strlen($kode) > 30) {
        return [null, 'Kode panen maksimal 30 karakter.'];
    }

    $petani_id = (int)($post['petani_id'] ?? 0);
    if ($petani_id <= 0) {
        return [null, 'Petani wajib dipilih.'];
    }

    // Pajak, ongkir, biaya, rafaksi, diskon
    $tambahan = [];
    foreach (['pajak', 'ongkir', 'biaya', 'rafaksi', 'diskon'] as $f) {
        $v = trim((string)($post[$f] ?? ''));
        if ($v === '') {
            $v = '0';
        }
        if (!is_numeric($v) || (float)$v < 0) {
            return [null, ucfirst($f) . ' harus berupa angka dan tidak boleh negatif.'];
        }
        $tambahan[$f] = round((float)$v, 2);
    }

    // Detail
    $pid   = $post['produk_id'] ?? [];
    $qty   = $post['qty'] ?? [];
    $harga = $post['harga'] ?? [];
    if (!is_array($pid) || !is_array($qty) || !is_array($harga)) {
        return [null, 'Data detail tidak valid.'];
    }

    $items    = [];
    $subtotal = 0.0;
    foreach ($pid as $i => $p) {
        $produk_id = (int)$p;
        if ($produk_id <= 0) {
            continue; // baris kosong dilewati
        }
        $q = trim((string)($qty[$i] ?? ''));
        $h = trim((string)($harga[$i] ?? ''));
        if (!is_numeric($q) || (float)$q <= 0) {
            return [null, 'Qty setiap item harus lebih dari 0.'];
        }
        if (!is_numeric($h) || (float)$h < 0) {
            return [null, 'Harga setiap item harus berupa angka dan tidak boleh negatif.'];
        }
        $q     = round((float)$q, 2);
        $h     = round((float)$h, 2);
        $total = round($q * $h, 2);

        $items[]   = ['produk_id' => $produk_id, 'qty' => $q, 'harga' => $h, 'total' => $total];
        $subtotal += $total;
    }

    if (!$items) {
        return [null, 'Minimal satu item produk harus diisi.'];
    }

    $subtotal = round($subtotal, 2);
    $grand    = round(
        $subtotal + $tambahan['pajak'] + $tambahan['ongkir'] + $tambahan['biaya']
        - $tambahan['rafaksi'] - $tambahan['diskon'],
        2
    );
    if ($grand < 0) {
        return [null, 'Grand total tidak boleh negatif. Periksa rafaksi dan diskon.'];
    }

    return [[
        'tanggal'     => $tanggal,
        'kode'        => $kode,
        'petani_id'   => $petani_id,
        'subtotal'    => $subtotal,
        'pajak'       => $tambahan['pajak'],
        'ongkir'      => $tambahan['ongkir'],
        'biaya'       => $tambahan['biaya'],
        'rafaksi'     => $tambahan['rafaksi'],
        'diskon'      => $tambahan['diskon'],
        'grand_total' => $grand,
        'items'       => $items,
    ], null];
}

/**
 * Simpan semua baris detail dengan SATU query INSERT multi-row (tanpa perulangan query).
 * Harus dipanggil di dalam transaksi.
 */
function panen_simpan_detail(mysqli $conn, int $panen_id, array $items): void
{
    $n   = count($items);
    $sql = 'INSERT INTO panen_detail (panen_id, produk_id, qty, harga, total) VALUES '
         . implode(',', array_fill(0, $n, '(?,?,?,?,?)'));

    $params = [];
    foreach ($items as $it) {
        array_push($params, $panen_id, $it['produk_id'], $it['qty'], $it['harga'], $it['total']);
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('iiddd', $n), ...$params);
    $stmt->execute();
    $stmt->close();
}

/**
 * Susun ulang isi form dari input sebelumnya (dipakai saat validasi gagal,
 * supaya yang sudah diketik tidak hilang). Disimpan di $_SESSION['sipk-old-panen'].
 *
 * @return array [dataPanen, barisDetail]
 */
function panen_dari_old(array $old): array
{
    $panen = [];
    foreach (['tanggal', 'kode', 'petani_id', 'pajak', 'ongkir', 'biaya', 'rafaksi', 'diskon'] as $f) {
        if (isset($old[$f]) && !is_array($old[$f])) {
            $panen[$f] = $old[$f];
        }
    }

    $pid   = is_array($old['produk_id'] ?? null) ? $old['produk_id'] : [];
    $qty   = is_array($old['qty'] ?? null) ? $old['qty'] : [];
    $harga = is_array($old['harga'] ?? null) ? $old['harga'] : [];

    $detail = [];
    foreach ($pid as $i => $p) {
        $detail[] = [
            'produk_id' => is_array($p) ? '' : $p,
            'qty'       => is_array($qty[$i] ?? '') ? '' : ($qty[$i] ?? ''),
            'harga'     => is_array($harga[$i] ?? '') ? '' : ($harga[$i] ?? ''),
        ];
    }

    return [$panen, $detail];
}