<?php

include 'config.php';
require_once 'jurnal_core.php';
$parameter = ambilParameterDataTables(KOLOM_URUT_JURNAL, 'j.tanggal');
$jurnalHalamanIni = ambilJurnalHalamanIni($conn, $parameter);
$detailPerJurnal  = ambilDetailAkunPerJurnal($conn, array_column($jurnalHalamanIni, 'id'));
$barisData = [];
foreach ($jurnalHalamanIni as $urutan => $jurnal) {
    $nomor       = $parameter['mulai'] + $urutan + 1;
    $daftarAkun  = $detailPerJurnal[$jurnal['id']] ?? [];
    $barisData[] = barisTabelJurnal($nomor, $jurnal, $daftarAkun);
}
kirimJsonDataTables(
    $parameter,
    hitungSemuaJurnal($conn),
    hitungJurnalSesuaiPencarian($conn, $parameter['cari']),
    $barisData
);