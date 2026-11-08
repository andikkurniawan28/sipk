<?php

include 'config.php';
require_once 'pembelian_langsung_core.php';

pastikanRequestPost('pembelian_langsung_index.php');

try {
    $header = ambilHeaderPembelianDariForm($_POST);
    pastikanHeaderPembelianValid($header);
    $barisProduk = ambilBarisProdukPembelianDariForm($_POST);
    $subtotal    = jumlahkanKolom($barisProduk, 'total');
    $grandTotal  = hitungGrandTotalPembelian($header, $subtotal);
    pastikanGrandTotalTidakNegatif($grandTotal);
    $barisAkun = susunBarisAkunPembelian($header, $subtotal, $grandTotal);
    pastikanDebitKreditSeimbang(jumlahkanKolom($barisAkun, 'debit'), jumlahkanKolom($barisAkun, 'kredit'));
    jalankanDalamTransaksi($conn, function () use ($conn, $header, $barisProduk, $barisAkun, $subtotal, $grandTotal) {
        $pembelianId = simpanHeaderPembelian($conn, $header, $subtotal, $grandTotal, idUserLogin());
        simpanDetailPembelian($conn, $pembelianId, $barisProduk);
        postingKeBukuBesar(
            $conn,
            'pembelian_langsung_id',
            $pembelianId,
            $header['tanggal'],
            idUserLogin(),
            keteranganBukuBesarPembelian($header),
            $barisAkun
        );
    });
} catch (DomainException $e) {
    alihkanDenganPesanGagal('Validasi Gagal', $e->getMessage(), 'pembelian_langsung_tambah.php');
} catch (Throwable $e) {
    alihkanDenganPesanGagal('Gagal Menyimpan', 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'pembelian_langsung_tambah.php');
}

alihkanDenganPesanSukses('Data pembelian langsung berhasil disimpan.', 'pembelian_langsung_index.php');