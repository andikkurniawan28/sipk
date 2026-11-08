<?php

include 'config.php';
require_once 'penerimaan_pembelian_langsung_core.php';

$tabel     = DEFINISI_TABEL_PENERIMAAN;
$parameter = ambilParameterDataTables($tabel['kolomUrut'], $tabel['urutDefault']);

$penerimaanHalamanIni = ambilBarisHalaman($conn, $tabel, $parameter);
$produkPerPenerimaan  = ambilDetailProdukPerPenerimaan($conn, array_column($penerimaanHalamanIni, 'id'));

$barisData = [];

foreach ($penerimaanHalamanIni as $urutan => $penerimaan) {
    $nomor        = $parameter['mulai'] + $urutan + 1;
    $daftarProduk = $produkPerPenerimaan[$penerimaan['id']] ?? [];
    $barisData[]  = barisTabelPenerimaan($nomor, $penerimaan, $daftarProduk);
}

kirimJsonDataTables(
    $parameter,
    hitungSemuaBaris($conn, $tabel),
    hitungBarisTersaring($conn, $tabel, $parameter['cari']),
    $barisData
);