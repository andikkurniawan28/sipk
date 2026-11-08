<?php

include 'config.php';
require_once 'pembelian_langsung_core.php';

$tabel     = DEFINISI_TABEL_PEMBELIAN;
$parameter = ambilParameterDataTables($tabel['kolomUrut'], $tabel['urutDefault']);

$pembelianHalamanIni = ambilBarisHalaman($conn, $tabel, $parameter);
$produkPerPembelian  = ambilDetailProdukPerPembelian($conn, array_column($pembelianHalamanIni, 'id'));

$barisData = [];

foreach ($pembelianHalamanIni as $urutan => $pembelian) {
    $nomor        = $parameter['mulai'] + $urutan + 1;
    $daftarProduk = $produkPerPembelian[$pembelian['id']] ?? [];
    $barisData[]  = barisTabelPembelian($nomor, $pembelian, $daftarProduk);
}

kirimJsonDataTables(
    $parameter,
    hitungSemuaBaris($conn, $tabel),
    hitungBarisTersaring($conn, $tabel, $parameter['cari']),
    $barisData
);