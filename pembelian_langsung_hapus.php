<?php

include 'config.php';
require_once 'pembelian_langsung_core.php';

try {
    $pembelianId = ambilIdDariUrl();
    pastikanIdValid($pembelianId, 'pembelian');

    hapusPembelian($conn, $pembelianId);
} catch (DomainException $e) {
    alihkanDenganPesanGagal('Gagal', $e->getMessage(), 'pembelian_langsung_index.php');
} catch (Throwable $e) {
    alihkanDenganPesanGagal('Gagal Menghapus', 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'pembelian_langsung_index.php');
}

alihkanDenganPesanSukses('Data pembelian langsung berhasil dihapus.', 'pembelian_langsung_index.php');