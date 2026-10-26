<?php
include 'config.php';

$draw   = intval($_GET['draw'] ?? 1);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$searchValue = $_GET['search']['value'] ?? '';

// Kolom untuk sorting (sesuai urutan kolom pada tabel HTML: No, Tanggal, Kode, Referensi Preparasi, Keterangan, Oleh, Aksi)
$columns = ['p.id', 'p.tanggal', 'p.kode', 'pr.kode', 'p.keterangan', 'u.nama'];
$orderColumnIndex = intval($_GET['order'][0]['column'] ?? 1);
$orderDir = ($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$orderBy = $columns[$orderColumnIndex] ?? 'p.tanggal';

$sqlBase = "
    FROM produksi p
    JOIN preparasi pr ON pr.id = p.preparasi_id
    JOIN `user` u ON u.id = p.user_id
";

$whereClause = "";
$params = [];
$types = "";

if ($searchValue !== '') {
    $whereClause = " WHERE p.kode LIKE ? OR pr.kode LIKE ? OR p.keterangan LIKE ? OR u.nama LIKE ? OR p.tanggal LIKE ?";
    $searchTerm = "%" . $searchValue . "%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = "sssss";
}

$totalRes = $conn->query("SELECT COUNT(*) AS total FROM produksi");
$totalRecords = $totalRes->fetch_assoc()['total'];

$filteredSql = "SELECT COUNT(*) AS total " . $sqlBase . $whereClause;
if (!empty($params)) {
    $stmtFilter = $conn->prepare($filteredSql);
    $stmtFilter->bind_param($types, ...$params);
    $stmtFilter->execute();
    $totalFiltered = $stmtFilter->get_result()->fetch_assoc()['total'];
    $stmtFilter->close();
} else {
    $totalFiltered = $totalRecords;
}

$dataSql = "
    SELECT p.id, p.tanggal, p.kode, p.keterangan, 
           pr.kode AS kode_preparasi, u.nama AS dibuat_oleh
    " . $sqlBase . $whereClause . "
    ORDER BY $orderBy $orderDir
    LIMIT ?, ?
";

$paramsData = $params;
$typesData = $types . "ii";
$paramsData[] = $start;
$paramsData[] = $length;

$stmtData = $conn->prepare($dataSql);
if (!empty($params)) {
    $stmtData->bind_param($typesData, ...$paramsData);
} else {
    $stmtData->bind_param("ii", $start, $length);
}
$stmtData->execute();
$result = $stmtData->get_result();

$data = [];
$no = $start + 1;

while ($row = $result->fetch_assoc()) {
    $aksi = '
        <a href="produksi_lihat.php?id=' . (int)$row['id'] . '" class="btn btn-info btn-sm" title="Detail"><i class="fas fa-eye"></i></a>
        <a href="produksi_hapus.php?id=' . (int)$row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>
    ';

    $data[] = [
        $no++,
        date('d-m-Y', strtotime($row['tanggal'])),
        htmlspecialchars($row['kode'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($row['kode_preparasi'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($row['keterangan'] ?? '-', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($row['dibuat_oleh'], ENT_QUOTES, 'UTF-8'),
        '<div class="text-center">' . $aksi . '</div>'
    ];
}
$stmtData->close();

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);