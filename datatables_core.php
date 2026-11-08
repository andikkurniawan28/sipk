<?php

const URL_BAHASA_DATATABLES = 'https://cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.6/i18n/id.json';

function htmlKepalaTabel(array $kolom): string
{
    $htmlKolom = '';

    foreach ($kolom as $satuKolom) {
        $atribut = '';

        if (isset($satuKolom['lebar'])) {
            $atribut .= ' style="width:' . h($satuKolom['lebar']) . '"';
        }

        if (isset($satuKolom['rata'])) {
            $atribut .= ' class="text-' . h($satuKolom['rata']) . '"';
        }

        $htmlKolom .= "<th{$atribut}>" . h($satuKolom['judul']) . '</th>';
    }

    return '<thead><tr>' . $htmlKolom . '</tr></thead>';
}

function aturanKolomDataTables(array $kolom): array
{
    $aturanSemuaKolom = [];

    foreach ($kolom as $indeks => $satuKolom) {
        $aturan = ['targets' => $indeks];

        if (($satuKolom['urut'] ?? true) === false) {
            $aturan['orderable']  = false;
            $aturan['searchable'] = false;
        }

        if (isset($satuKolom['rata'])) {
            $aturan['className'] = 'text-' . $satuKolom['rata'];
        }

        if (count($aturan) > 1) {
            $aturanSemuaKolom[] = $aturan;
        }
    }

    return $aturanSemuaKolom;
}

function skripDataTablesServerSide(
    string $idTabel,
    string $urlData,
    array $kolom,
    int $urutKolomAwal,
    string $urutArahAwal = 'desc'
): string {
    $konfigurasi = [
        'processing' => true,
        'serverSide' => true,
        'ajax'       => ['url' => $urlData, 'type' => 'GET'],
        'order'      => [[$urutKolomAwal, $urutArahAwal]],
        'pageLength' => 10,
        'language'   => ['url' => URL_BAHASA_DATATABLES],
        'columnDefs' => aturanKolomDataTables($kolom),
    ];

    return '<script>window.addEventListener("load", function () { $("#' . $idTabel . '").DataTable('
         . json_encode($konfigurasi, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
         . '); });</script>';
}