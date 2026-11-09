<?php

include 'config.php';
require_once 'penerimaan_pembelian_langsung_core.php';

pastikanRequestPost('penerimaan_pembelian_langsung_index.php');

try {
    $header = ambilHeaderPenerimaanDariForm($_POST);
    pastikanHeaderPenerimaanValid($header);

    $hargaPerProduk  = ambilHargaPembelianPerProduk($conn, $header['pembelian_langsung_id']);
    $barisProduk     = ambilBarisProdukPenerimaanDariForm($_POST, $hargaPerProduk);
    $totalPenerimaan = jumlahkanKolom($barisProduk, 'total');

    $kodePembelian = ambilKodePembelian($conn, $header['pembelian_langsung_id']);
    $keterangan    = keteranganPenerimaan($header, $kodePembelian);
    $barisAkun     = susunBarisAkunPenerimaan($totalPenerimaan);

    jalankanDalamTransaksi($conn, function () use ($conn, $header, $barisProduk, $barisAkun, $totalPenerimaan, $keterangan) {
        // Kunci faktur lalu cek sisa qty, di dalam transaksi agar dua penerimaan bersamaan tidak sama-sama lolos
        kunciBarisPembelian($conn, $header['pembelian_langsung_id']);
        pastikanQtyTidakMelebihiSisa($conn, $header['pembelian_langsung_id'], $barisProduk);

        $penerimaanId = simpanHeaderPenerimaan($conn, $header, $totalPenerimaan, idUserLogin());

        simpanDetailPenerimaan($conn, $penerimaanId, $barisProduk);

        postingKeBukuBesar(
            $conn,
            'penerimaan_pembelian_langsung_id',
            $penerimaanId,
            $header['tanggal'],
            idUserLogin(),
            $keterangan,
            $barisAkun
        );

        // Harus sebelum mutasi stok masuk, karena stok lama dihitung dari mutasi_stok
        perbaruiHargaPokokRataRata($conn, $barisProduk);

        simpanMutasiStokMasuk($conn, $penerimaanId, $header, $keterangan, $barisProduk, idUserLogin());
    });
} catch (DomainException $e) {
    alihkanDenganPesanGagal('Validasi Gagal', $e->getMessage(), 'penerimaan_pembelian_langsung_tambah.php');
} catch (Throwable $e) {
    alihkanDenganPesanGagal('Gagal Menyimpan', 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'penerimaan_pembelian_langsung_tambah.php');
}

alihkanDenganPesanSukses('Data penerimaan pembelian langsung berhasil disimpan.', 'penerimaan_pembelian_langsung_index.php');