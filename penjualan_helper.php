<?php

// Fungsi bantu modul penjualan.
// Dipakai oleh: penjualan_tambah, penjualan_edit, penjualan_simpan, penjualan_update.

function penjualan_h($teks): string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

/**
 * Kode otomatis: PN + yymmdd + "-" + nomor urut 3 digit, mis. PN261023-001.
 * Hanya usulan awal; kolom kode di form tetap bisa diubah.
 */
function penjualan_kode_baru(mysqli $conn, string $tanggal): string
{
    $prefix = 'SL' . date('ymd', strtotime($tanggal));
    $like   = $prefix . '-%';

    $stmt = $conn->prepare("SELECT kode FROM penjualan WHERE kode LIKE ? ORDER BY kode DESC LIMIT 1");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $baris = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $urut = $baris ? ((int)substr($baris['kode'], -3) + 1) : 1;
    return sprintf('%s-%03d', $prefix, $urut);
}

/**
 * Validasi input form penjualan + hitung semua total di sisi server
 * (nilai total dari browser tidak dipercaya).
 *
 * Rumus: grand_total = subtotal + pajak + ongkir + biaya - rafaksi - diskon
 *
 * @return array [data|null, pesanError|null]
 */
function penjualan_ambil_input(array $post): array
{
    $tanggal = trim((string)($post['tanggal'] ?? ''));
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$d || $d->format('Y-m-d') !== $tanggal) {
        return [null, 'Tanggal tidak valid.'];
    }

    $kode = trim((string)($post['kode'] ?? ''));
    if ($kode === '') {
        return [null, 'Kode penjualan wajib diisi.'];
    }
    if (mb_strlen($kode) > 30) {
        return [null, 'Kode penjualan maksimal 30 karakter.'];
    }

    $pelanggan_id = (int)($post['pelanggan_id'] ?? 0);
    if ($pelanggan_id <= 0) {
        return [null, 'Petani wajib dipilih.'];
    }

    $gudang_id = (int)($post['gudang_id'] ?? 0);
    if ($gudang_id <= 0) {
        return [null, 'Gudang wajib dipilih.'];
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
        'pelanggan_id'   => $pelanggan_id,
        'gudang_id'   => $gudang_id,
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
function penjualan_simpan_detail(mysqli $conn, int $penjualan_id, array $items): void
{
    $n   = count($items);
    $sql = 'INSERT INTO penjualan_detail (penjualan_id, produk_id, qty, harga, total) VALUES '
         . implode(',', array_fill(0, $n, '(?,?,?,?,?)'));

    $params = [];
    foreach ($items as $it) {
        array_push($params, $penjualan_id, $it['produk_id'], $it['qty'], $it['harga'], $it['total']);
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('iiddd', $n), ...$params);
    $stmt->execute();
    $stmt->close();
}

/**
 * Susun ulang isi form dari input sebelumnya (dipakai saat validasi gagal,
 * supaya yang sudah diketik tidak hilang). Disimpan di $_SESSION['sipk-old-penjualan'].
 *
 * @return array [dataPanen, barisDetail]
 */
function penjualan_dari_old(array $old): array
{
    $penjualan = [];
    foreach (['tanggal', 'kode', 'pelanggan_id', 'gudang_id', 'pajak', 'ongkir', 'biaya', 'rafaksi', 'diskon'] as $f) {
        if (isset($old[$f]) && !is_array($old[$f])) {
            $penjualan[$f] = $old[$f];
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

    return [$penjualan, $detail];
}