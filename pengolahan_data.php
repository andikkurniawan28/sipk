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
        3 => 'p.keterangan',
        4 => 'total_input',
        5 => 'total_output',
    ];
    $idxUrut = (int)($_GET['order'][0]['column'] ?? 1);
    $arah    = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $kolom   = $kolomUrut[$idxUrut] ?? 'p.tanggal';
    $urutSql = "$kolom $arah, p.id $arah";

    // --- Pencarian ---
    $where  = '';
    $tipe   = '';
    $params = [];
    if ($cari !== '') {
        $like   = '%' . addcslashes($cari, '\\%_') . '%';
        $where  = "WHERE (p.kode LIKE ? OR p.keterangan LIKE ?
                          OR DATE_FORMAT(p.tanggal, '%d-%m-%Y') LIKE ?)";
        $tipe   = 'sss';
        $params = [$like, $like, $like];
    }

    // --- Jumlah data ---
    $recordsTotal = (int)$conn->query("SELECT COUNT(*) AS n FROM pengolahan")->fetch_assoc()['n'];

    $recordsFiltered = (int)jalankan($conn, "SELECT COUNT(*) AS n FROM pengolahan p $where", $tipe, $params)
        ->fetch_assoc()['n'];

    // --- Data halaman ini (total dihitung dari tabel detail, tidak disimpan di header) ---
    $sql = "SELECT p.id, p.tanggal, p.kode, p.keterangan,
                   (SELECT COALESCE(SUM(i.total), 0) FROM input_pengolahan i WHERE i.pengolahan_id = p.id) AS total_input,
                   (SELECT COALESCE(SUM(o.total), 0) FROM output_pengolahan o WHERE o.pengolahan_id = p.id) AS total_output
            FROM pengolahan p
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
            h(mb_strimwidth((string)$row['keterangan'], 0, 60, '...', 'UTF-8')),
            number_format((float)$row['total_input'], 2, ',', '.'),
            number_format((float)$row['total_output'], 2, ',', '.'),
            '<a href="pengolahan_lihat.php?id=' . $id . '" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a> '
          . '<a href="pengolahan_edit.php?id=' . $id . '" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a> '
          . '<a href="pengolahan_hapus.php?id=' . $id . '" class="btn btn-danger btn-sm btn-hapus"><i class="fas fa-trash"></i></a>',
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