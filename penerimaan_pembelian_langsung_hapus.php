<?php

include 'config.php';
require_once 'penerimaan_pembelian_langsung_core.php';

try {
    $penerimaanId = ambilIdDariUrl();
    pastikanIdValid($penerimaanId, 'penerimaan');

    hapusPenerimaan($conn, $penerimaanId);
} catch (DomainException $e) {
    alihkanDenganPesanGagal('Gagal', $e->getMessage(), 'penerimaan_pembelian_langsung_index.php');
} catch (Throwable $e) {
    alihkanDenganPesanGagal('Gagal Menghapus', 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'penerimaan_pembelian_langsung_index.php');
}

alihkanDenganPesanSukses('Data penerimaan pembelian langsung berhasil dihapus.', 'penerimaan_pembelian_langsung_index.php');