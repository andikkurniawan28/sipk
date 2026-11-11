<?php

// Qty dipesan, terkirim, sisa, dan harga jual rata-rata per produk untuk satu penjualan
function ambilSisaQtyPenjualan(mysqli $conn, int $penjualanId): array
{
    $stmt = $conn->prepare("
        SELECT d.produk_id, d.qty_pesan, d.harga, COALESCE(t.qty_kirim, 0) AS qty_kirim
        FROM (
            SELECT produk_id, SUM(qty) AS qty_pesan, SUM(total) / SUM(qty) AS harga
            FROM penjualan_detail
            WHERE penjualan_id = ?
            GROUP BY produk_id
            HAVING SUM(qty) > 0
        ) d
        LEFT JOIN (
            SELECT pd.produk_id, SUM(pd.qty) AS qty_kirim
            FROM pengiriman_produk_detail pd
            JOIN pengiriman_produk pk ON pk.id = pd.pengiriman_produk_id
            WHERE pk.penjualan_id = ?
            GROUP BY pd.produk_id
        ) t ON t.produk_id = d.produk_id
    ");
    $stmt->bind_param('ii', $penjualanId, $penjualanId);
    $stmt->execute();
    $hasil = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $out = [];
    foreach ($hasil as $r) {
        $out[(int)$r['produk_id']] = [
            'pesan'  => (float)$r['qty_pesan'],
            'kirim'  => (float)$r['qty_kirim'],
            'sisa'   => round((float)$r['qty_pesan'] - (float)$r['qty_kirim'], 2),
            'harga'  => (float)$r['harga'],
        ];
    }
    return $out;
}

// Penjualan yang masih punya sisa qty belum dikirim
function ambilPenjualanBelumLengkapDikirim(mysqli $conn): array
{
    return $conn->query("
        SELECT pn.id, pn.kode, pn.tanggal, plg.nama AS nama_pelanggan
        FROM penjualan pn
        JOIN pelanggan plg ON plg.id = pn.pelanggan_id
        WHERE EXISTS (
            SELECT 1
            FROM penjualan_detail d
            WHERE d.penjualan_id = pn.id
            GROUP BY d.produk_id
            HAVING SUM(d.qty) - COALESCE((
                SELECT SUM(pd.qty)
                FROM pengiriman_produk_detail pd
                JOIN pengiriman_produk pk ON pk.id = pd.pengiriman_produk_id
                WHERE pk.penjualan_id = pn.id AND pd.produk_id = d.produk_id
            ), 0) > 0.0001
        )
        ORDER BY pn.id DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

function ambilStokGudang(mysqli $conn, int $produkId, int $gudangId): float
{
    $stmt = $conn->prepare("SELECT COALESCE(SUM(masuk - keluar), 0) AS stok FROM mutasi_stok WHERE produk_id = ? AND gudang_id = ?");
    $stmt->bind_param('ii', $produkId, $gudangId);
    $stmt->execute();
    $stok = (float)$stmt->get_result()->fetch_assoc()['stok'];
    $stmt->close();
    return $stok;
}