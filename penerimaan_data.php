<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$draw = (int)($_GET['draw'] ?? 0);

if (empty($_SESSION['sipk-user_id'])) {
    http_response_code(401);
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => 'Sesi berakhir, silakan login ulang.',
    ]);
    exit;
}

/** Jalankan query prepared dengan parameter dinamis, kembalikan mysqli_result. */
function jalankan(mysqli $conn, string $sql, string $tipe, array $params): mysqli_result
{
    $stmt = $conn->prepare($sql);
    if ($tipe !== '') {
        $stmt->bind_param($tipe, ...$params);
    }
    $stmt->execute();
    $hasil = $stmt->get_result();
    $stmt->close();
    return $hasil;
}

if (!function_exists('h')) {
    function h($teks): string
    {
        return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
    }
}

try {
    // --- Parameter dari DataTables ---
    $start  = max(0, (int)($_GET['start'] ?? 0));
    $length = (int)($_GET['length'] ?? 10);
    if ($length < 1 || $length > 100) {
        $length = 100;
    }
    $cari = trim((string)($_GET['search']['value'] ?? ''));

    // --- Urutan: whitelist kolom (indeks = urutan kolom di tabel) ---
    // 0 No | 1 Tanggal | 2 Kode | 3 Proposal | 4 Gudang | 5 Dibuat Oleh | 6 Aksi
    $kolomUrut = [
        1 => 'p.tanggal',
        2 => 'p.kode',
        3 => 'pr.kode',
        4 => 'g.nama',
        5 => 'u.nama',
    ];
    $idxUrut = (int)($_GET['order'][0]['column'] ?? 1);
    $arah    = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $kolom   = $kolomUrut[$idxUrut] ?? 'p.tanggal';
    $urutSql = "$kolom $arah, p.id $arah";

    // --- Relasi: proposal (+ petani), gudang, user ---
    $dariSql = "FROM penerimaan p
                JOIN proposal pr ON pr.id = p.proposal_id
                JOIN petani pt   ON pt.id = pr.petani_id
                JOIN gudang g    ON g.id  = p.gudang_id
                JOIN `user` u    ON u.id  = p.user_id";

    // --- Pencarian ---
    $where  = '';
    $tipe   = '';
    $params = [];
    if ($cari !== '') {
        $like   = '%' . addcslashes($cari, '\\%_') . '%';
        $where  = "WHERE (p.kode LIKE ? OR pr.kode LIKE ? OR pt.nama LIKE ?
                          OR g.nama LIKE ? OR u.nama LIKE ?
                          OR DATE_FORMAT(p.tanggal, '%d-%m-%Y') LIKE ?)";
        $tipe   = 'ssssss';
        $params = [$like, $like, $like, $like, $like, $like];
    }

    // --- Jumlah data ---
    $recordsTotal = (int)jalankan($conn, 'SELECT COUNT(*) AS n FROM penerimaan', '', [])
        ->fetch_assoc()['n'];

    $recordsFiltered = (int)jalankan($conn, "SELECT COUNT(*) AS n $dariSql $where", $tipe, $params)
        ->fetch_assoc()['n'];

    // --- Data halaman ini ---
    $sql = "SELECT p.id, p.tanggal, p.kode,
                   pr.kode AS kode_proposal, pt.nama AS petani,
                   g.nama AS gudang, u.nama AS dibuat_oleh
            $dariSql
            $where
            ORDER BY $urutSql
            LIMIT ?, ?";
    $hasil = jalankan($conn, $sql, $tipe . 'ii', array_merge($params, [$start, $length]));

    $data = [];
    $no   = $start + 1;
    while ($row = $hasil->fetch_assoc()) {
        $id = (int)$row['id'];

        $aksi = '<a href="penerimaan_lihat.php?id=' . $id . '" class="btn btn-info btn-sm" title="Detail"><i class="fas fa-eye"></i></a>'
              . ' <a href="penerimaan_hapus.php?id=' . $id . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>';

        $proposal = h($row['kode_proposal'])
                  . '<div class="small text-gray-600">' . h($row['petani']) . '</div>';

        $data[] = [
            $no++,
            date('d-m-Y', strtotime($row['tanggal'])),
            h($row['kode']),
            $proposal,
            h($row['gudang']),
            h($row['dibuat_oleh']),
            '<div class="text-center">' . $aksi . '</div>',
        ];
    }

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('penerimaan_data: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => 'Data tidak dapat dimuat.',
    ]);
}