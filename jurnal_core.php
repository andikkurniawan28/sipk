<?php

const JUMLAH_BARIS_AWAL_JURNAL = 2;

const SQL_DARI_JURNAL = 'FROM jurnal j JOIN `user` u ON u.id = j.user_id';

const KOLOM_URUT_JURNAL = [
    0 => 'j.id',
    1 => 'j.tanggal',
    2 => 'j.kode',
    3 => 'u.nama',
    4 => 'j.keterangan',
    6 => 'j.timestamp',
];

function ambilHeaderJurnalDariForm(array $form): array
{
    return [
        'tanggal'    => ambilTeksDariForm($form, 'tanggal'),
        'kode'       => ambilTeksDariForm($form, 'kode'),
        'keterangan' => ambilTeksDariForm($form, 'keterangan'),
    ];
}

function pastikanHeaderJurnalValid(array $header): void
{
    pastikanFormatTanggalValid($header['tanggal']);
    pastikanTidakKosong($header['kode'], 'Kode jurnal');
}

function ambilBarisAkunDariForm(array $form): array
{
    $akunIds = $form['akun_id'] ?? [];
    $debets  = $form['debit'] ?? [];
    $kredits = $form['kredit'] ?? [];

    if (!is_array($akunIds) || !is_array($debets) || !is_array($kredits)) {
        throw new DomainException('Struktur data detail jurnal tidak valid.');
    }

    $barisAkun = [];

    foreach ($akunIds as $i => $akunId) {
        if ((int)$akunId <= 0) {
            continue;
        }

        $debit  = round((float)($debets[$i] ?? 0), 2);
        $kredit = round((float)($kredits[$i] ?? 0), 2);

        if ($debit < 0 || $kredit < 0) {
            throw new DomainException('Nilai debit atau kredit tidak boleh bernilai negatif.');
        }

        $barisAkun[] = ['akun_id' => (int)$akunId, 'debit' => $debit, 'kredit' => $kredit];
    }

    if (empty($barisAkun)) {
        throw new DomainException('Minimal harus ada satu baris detail akun jurnal.');
    }

    return $barisAkun;
}

function simpanHeaderJurnal(mysqli $conn, array $header, int $userId): int
{
    $stmt = $conn->prepare("INSERT INTO jurnal (tanggal, kode, user_id, keterangan) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssis', $header['tanggal'], $header['kode'], $userId, $header['keterangan']);
    $stmt->execute();
    $jurnalId = $conn->insert_id;
    $stmt->close();

    return $jurnalId;
}

function simpanDetailJurnal(mysqli $conn, int $jurnalId, array $barisAkun): void
{
    $baris = [];

    foreach ($barisAkun as $akun) {
        $baris[] = [$jurnalId, $akun['akun_id'], $akun['debit'], $akun['kredit']];
    }

    insertBanyakBaris($conn, 'jurnal_detail', ['jurnal_id', 'akun_id', 'debit', 'kredit'], 'iidd', $baris);
}

function keteranganBukuBesarJurnal(array $header): string
{
    return "Jurnal Umum - {$header['kode']} - {$header['keterangan']}.";
}

function hapusJurnal(mysqli $conn, int $jurnalId): void
{
    $stmt = $conn->prepare("DELETE FROM jurnal WHERE id = ?");
    $stmt->bind_param('i', $jurnalId);
    $stmt->execute();
    $jumlahTerhapus = $stmt->affected_rows;
    $stmt->close();

    if ($jumlahTerhapus === 0) {
        throw new DomainException('Data jurnal tidak ditemukan.');
    }
}

function kondisiPencarianJurnal(string $kataKunci): array
{
    if ($kataKunci === '') {
        return ['', '', []];
    }

    $pola = polaCariLike($kataKunci);

    return ['WHERE j.kode LIKE ? OR j.keterangan LIKE ? OR u.nama LIKE ?', 'sss', [$pola, $pola, $pola]];
}

function hitungSemuaJurnal(mysqli $conn): int
{
    return hitungBaris($conn, 'SELECT COUNT(*) FROM jurnal');
}

function hitungJurnalSesuaiPencarian(mysqli $conn, string $kataKunci): int
{
    [$kondisi, $tipeNilai, $nilai] = kondisiPencarianJurnal($kataKunci);

    return hitungBaris($conn, 'SELECT COUNT(*) ' . SQL_DARI_JURNAL . " {$kondisi}", $tipeNilai, $nilai);
}

function ambilJurnalHalamanIni(mysqli $conn, array $parameter): array
{
    [$kondisi, $tipeNilai, $nilai] = kondisiPencarianJurnal($parameter['cari']);

    $sql = 'SELECT j.id, j.tanggal, j.kode, j.keterangan, j.timestamp, u.nama AS nama_admin '
         . SQL_DARI_JURNAL . " {$kondisi} "
         . "ORDER BY {$parameter['urutKolom']} {$parameter['urutArah']}, j.id DESC "
         . 'LIMIT ? OFFSET ?';

    $nilaiBind = [...$nilai, $parameter['panjang'], $parameter['mulai']];

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipeNilai . 'ii', ...$nilaiBind);
    $stmt->execute();
    $jurnal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $jurnal;
}

function ambilDetailAkunPerJurnal(mysqli $conn, array $jurnalIds): array
{
    if (empty($jurnalIds)) {
        return [];
    }

    $daftarId = implode(',', array_map('intval', $jurnalIds));

    $hasil = $conn->query("
        SELECT jd.jurnal_id, a.kode AS kode_akun, a.nama AS nama_akun, jd.debit, jd.kredit
        FROM jurnal_detail jd
        JOIN akun a ON a.id = jd.akun_id
        WHERE jd.jurnal_id IN ({$daftarId})
        ORDER BY jd.id
    ");

    $detailPerJurnal = [];

    foreach ($hasil as $baris) {
        $detailPerJurnal[$baris['jurnal_id']][] = $baris;
    }

    return $detailPerJurnal;
}

function labelNominalAkun(array $akun): string
{
    if ((float)$akun['debit'] > 0) {
        return '<span class="text-success">D: ' . rp($akun['debit']) . '</span>';
    }

    return '<span class="text-danger">K: ' . rp($akun['kredit']) . '</span>';
}

function htmlDaftarAkunJurnal(array $daftarAkun): string
{
    $itemAkun = '';

    foreach ($daftarAkun as $akun) {
        $itemAkun .= '<li><small><strong>' . h($akun['kode_akun'] . ' - ' . $akun['nama_akun']) . '</strong>: '
                   . labelNominalAkun($akun) . '</small></li>';
    }

    return '<ul style="list-style-type: none; padding-left: 0; margin-bottom: 0;">' . $itemAkun . '</ul>';
}

function htmlTombolAksiJurnal(int $jurnalId): string
{
    return '<a href="jurnal_lihat.php?id=' . $jurnalId . '" class="btn btn-info btn-sm" title="Lihat Detail"><i class="fas fa-eye"></i></a> '
         . '<a href="jurnal_hapus.php?id=' . $jurnalId . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>';
}

function barisTabelJurnal(int $nomor, array $jurnal, array $daftarAkun): array
{
    return [
        $nomor,
        h(tanggal_indo($jurnal['tanggal'])),
        h($jurnal['kode']),
        h($jurnal['nama_admin']),
        h($jurnal['keterangan'] ?: '-'),
        htmlDaftarAkunJurnal($daftarAkun),
        h($jurnal['timestamp']),
        htmlTombolAksiJurnal((int)$jurnal['id']),
    ];
}

function ambilDaftarAkun(mysqli $conn): array
{
    return $conn->query("SELECT id, kode, nama FROM akun ORDER BY kode ASC")->fetch_all(MYSQLI_ASSOC);
}

function htmlOpsiAkun(array $daftarAkun, int $akunTerpilihId = 0): string
{
    $opsi = '<option></option>';

    foreach ($daftarAkun as $akun) {
        $terpilih = (int)$akun['id'] === $akunTerpilihId ? ' selected' : '';

        $opsi .= '<option value="' . (int)$akun['id'] . '"' . $terpilih . '>'
               . h($akun['kode'] . ' - ' . $akun['nama']) . '</option>';
    }

    return $opsi;
}

function htmlBarisDetailJurnal(array $daftarAkun, array $nilaiAwal = []): string
{
    $akunId = (int)($nilaiAwal['akun_id'] ?? 0);
    $debit  = (string)($nilaiAwal['debit'] ?? '0');
    $kredit = (string)($nilaiAwal['kredit'] ?? '0');

    return '<tr>'
        . '<td><select name="akun_id[]" class="form-control form-control-sm select2 akun-select" required>'
        . htmlOpsiAkun($daftarAkun, $akunId) . '</select></td>'
        . '<td><input type="number" name="debit[]" class="form-control form-control-sm text-right debit" min="0" step="any" value="' . h($debit) . '" placeholder="0"></td>'
        . '<td><input type="number" name="kredit[]" class="form-control form-control-sm text-right kredit" min="0" step="any" value="' . h($kredit) . '" placeholder="0"></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}