<?php
function generate_kode_produksi($conn): string
{
    $prefix = 'PROD-' . date('ymd') . '-';
    
    $stmt = $conn->prepare("SELECT kode FROM produksi WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;

    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}