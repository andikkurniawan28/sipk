<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

$draw = (int)($_GET['draw'] ?? 0);

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

function h($teks): string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

try {
    // --- Parameter dari DataTables ---
    $start  = max(0, (int)($_GET['start'] ?? 0));
    $length = (int)($_GET['length'] ?? 10);
    if ($length < 1 || $length > 100) {
        $length = 100; // batas atas agar tidak menarik seluruh tabel sekaligus
    }
    $cari = trim((string)($_GET['search']['value'] ?? ''));

    // --- Urutan: hanya kolom yang diizinkan (whitelist), tidak pernah dari input langsung ---
    $kolomUrut = [
        1 => 'p.tanggal',
        2 => 'p.kode',
        3 => 'pt.nama',
        4 => 'g.nama',
        5 => 'jumlah_item',
        6 => 'p.grand_total',
    ];
    $idxUrut = (int)($_GET['order'][0]['column'] ?? 1);
    $arah    = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $kolom   = $kolomUrut[$idxUrut] ?? 'p.tanggal';
    $urutSql = "$kolom $arah, p.id $arah";

    // --- Pencarian ---
    $dariSql = "FROM panen p
                JOIN petani pt ON pt.id = p.petani_id
                JOIN gudang g ON g.id = p.gudang_id";
    $where  = '';
    $tipe   = '';
    $params = [];
    if ($cari !== '') {
        $like   = '%' . addcslashes($cari, '\\%_') . '%';
        $where  = "WHERE (p.kode LIKE ? OR pt.nama LIKE ? OR g.nama LIKE ?
                          OR DATE_FORMAT(p.tanggal, '%d-%m-%Y') LIKE ?)";
        $tipe   = 'ssss';
        $params = [$like, $like, $like, $like];
    }

    // --- Jumlah data ---
    $recordsTotal = (int)$conn->query("SELECT COUNT(*) AS n FROM panen")->fetch_assoc()['n'];

    $recordsFiltered = (int)jalankan($conn, "SELECT COUNT(*) AS n $dariSql $where", $tipe, $params)
        ->fetch_assoc()['n'];

    // --- Data halaman ini ---
    $sql = "SELECT p.id, p.tanggal, p.kode, p.grand_total,
                   pt.nama AS petani, g.nama AS gudang,
                   (SELECT COUNT(*) FROM panen_detail d WHERE d.panen_id = p.id) AS jumlah_item
            $dariSql
            $where
            ORDER BY $urutSql
            LIMIT ?, ?";
    $hasil = jalankan($conn, $sql, $tipe . 'ii', array_merge($params, [$start, $length]));

    $data = [];
    $no   = $start + 1;
    while ($row = $hasil->fetch_assoc()) {
        $id = (int)$row['id'];
        $data[] = [
            $no++,
            date('d-m-Y', strtotime($row['tanggal'])),
            h($row['kode']),
            h($row['petani']),
            h($row['gudang']),
            (int)$row['jumlah_item'],
            number_format((float)$row['grand_total'], 2, ',', '.'),
            '<a href="panen_lihat.php?id=' . $id . '" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a> '
          . '<a href="panen_edit.php?id=' . $id . '" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a> '
          . '<a href="panen_hapus.php?id=' . $id . '" class="btn btn-danger btn-sm btn-hapus"><i class="fas fa-trash"></i></a>',
        ];
    }

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => 'Data tidak dapat dimuat.',
    ]);
}