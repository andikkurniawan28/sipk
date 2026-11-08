<?php

include 'config.php';
require_once 'jurnal_core.php';
try {
    $jurnalId = ambilIdDariUrl();
    pastikanIdValid($jurnalId, 'jurnal');
    hapusJurnal($conn, $jurnalId);
} catch (DomainException $e) {
    alihkanDenganPesanGagal('Gagal', $e->getMessage(), 'jurnal_index.php');
} catch (Throwable $e) {
    alihkanDenganPesanGagal('Gagal Menghapus', 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'jurnal_index.php');
}
alihkanDenganPesanSukses('Data jurnal berhasil dihapus.', 'jurnal_index.php');