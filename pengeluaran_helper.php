<?php
if (!defined('IN_APP')) {
    // Memastikan helper dipanggil dari alur aplikasi utama
}

function pengeluaran_h(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function pengeluaran_dari_old(array $post): array
{
    $pengeluaran = [
        'id'        => (int)($post['id'] ?? 0),
        'tanggal'   => $post['tanggal'] ?? date('Y-m-d'),
        'kode'      => $post['kode'] ?? '',
        'gudang_id' => $post['gudang_id'] ?? '',
        'preparasi_id'  => $post['preparasi_id'] ?? ''
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

    return [$pengeluaran, $detail];
}

function generate_kode_pengeluaran($conn): string
{
    $prefix = 'STOT-' . date('ymd') . '-';
    
    $stmt = $conn->prepare("SELECT kode FROM pengeluaran WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;

    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}