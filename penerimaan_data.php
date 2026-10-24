<?php
include 'config.php';

$draw   = intval($_GET['draw'] ?? 1);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$searchValue = $_GET['search']['value'] ?? '';

// Kolom untuk sorting
$columns = ['p.id', 'p.tanggal', 'p.kode', 'g.nama', 'p.panen_id', 'u.nama'];
$orderColumnIndex = intval($_GET['order'][0]['column'] ?? 1);
$orderDir = ($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$orderBy = $columns[$orderColumnIndex] ?? 'p.tanggal';

// Base Query (murni penerimaan berelasi ke gudang, user, dan panen)
$sqlBase = "
    FROM penerimaan p
    JOIN gudang g ON g.id = p.gudang_id
    JOIN `user` u ON u.id = p.user_id
    LEFT JOIN panen pn ON pn.id = p.panen_id
";

// Kondisi Pencarian (Search)
$whereClause = "";
$params = [];
$types = "";

if ($searchValue !== '') {
    $whereClause = " WHERE p.kode LIKE ? OR g.nama LIKE ? OR u.nama LIKE ? OR pn.kode LIKE ? OR p.tanggal LIKE ?";
    $searchTerm = "%" . $searchValue . "%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = "sssss";
}

// 1. Total records tanpa filter
$totalRes = $conn->query("SELECT COUNT(*) AS total FROM penerimaan");
$totalRecords = $totalRes->fetch_assoc()['total'];

// 2. Total records dengan filter pencarian
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

// 3. Ambil data aktual sesuai halaman dan limit
$dataSql = "
    SELECT p.id, p.tanggal, p.kode, p.panen_id,
           g.nama AS nama_gudang, u.nama AS dibuat_oleh,
           pn.kode AS kode_panen
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
    $sumberText = '-';
    if (!empty($row['panen_id'])) {
        $sumberText = 'Panen (' . htmlspecialchars($row['kode_panen']) . ')';
    }

    $aksi = '
        <a href="penerimaan_lihat.php?id=' . (int)$row['id'] . '" class="btn btn-info btn-sm" title="Detail"><i class="fas fa-eye"></i></a>
        <a href="penerimaan_hapus.php?id=' . (int)$row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>
    ';

    $data[] = [
        $no++,
        date('d-m-Y', strtotime($row['tanggal'])),
        htmlspecialchars($row['kode'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($row['nama_gudang'], ENT_QUOTES, 'UTF-8'),
        $sumberText,
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