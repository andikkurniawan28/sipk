<?php

include 'config.php';
require_once  'jurnal_core.php';
pastikanRequestPost('jurnal_index.php');
try {
    $header    = ambilHeaderJurnalDariForm($_POST);
    $barisAkun = ambilBarisAkunDariForm($_POST);
    pastikanHeaderJurnalValid($header);
    pastikanDebitKreditSeimbang(jumlahkanKolom($barisAkun, 'debit'), jumlahkanKolom($barisAkun, 'kredit'));
    jalankanDalamTransaksi($conn, function () use ($conn, $header, $barisAkun) {
        $jurnalId = simpanHeaderJurnal($conn, $header, idUserLogin());
        simpanDetailJurnal($conn, $jurnalId, $barisAkun);
        postingKeBukuBesar(
            $conn,
            'jurnal_id',
            $jurnalId,
            $header['tanggal'],
            idUserLogin(),
            keteranganBukuBesarJurnal($header),
            $barisAkun
        );
    });
} catch (DomainException $e) {
    alihkanDenganPesanGagal('Validasi Gagal', $e->getMessage(), 'jurnal_tambah.php');
} catch (Throwable $e) {
    alihkanDenganPesanGagal('Gagal Menyimpan', 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'jurnal_tambah.php');
}
alihkanDenganPesanSukses('Data jurnal berhasil disimpan.', 'jurnal_index.php');