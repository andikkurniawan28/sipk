<?php
if (!defined('IN_APP')) {
    // Memastikan helper dipanggil dari alur aplikasi utama
}

function penerimaan_h(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function penerimaan_dari_old(array $post): array
{
    $penerimaan = [
        'id'        => (int)($post['id'] ?? 0),
        'tanggal'   => $post['tanggal'] ?? date('Y-m-d'),
        'kode'      => $post['kode'] ?? '',
        'gudang_id' => $post['gudang_id'] ?? '',
        'panen_id'  => $post['panen_id'] ?? ''
    ];

    $detail = [];
    $produkIds = $post['produk_id'] ?? [];
    $qtys      = $post['qty'] ?? [];

    for ($i = 0; $i < count($produkIds); $i++) {
        $detail[] = [
            'produk_id' => $produkIds[$i] ?? '',
            'qty'       => $qtys[$i] ?? '0'
        ];
    }

    if (empty($detail)) {
        $detail = [[]];
    }

    return [$penerimaan, $detail];
}

function generate_kode_penerimaan($conn): string
{
    $prefix = 'STIN-' . date('ymd') . '-';
    
    $stmt = $conn->prepare("SELECT kode FROM penerimaan WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;

    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}