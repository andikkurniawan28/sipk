<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

$columns = [
    0 => 'j.id',
    1 => 'j.tanggal',
    2 => 'j.kode',
    3 => 'u.nama',
    4 => 'j.keterangan',
    5 => 'j.timestamp'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 2;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'j.tanggal';

$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (j.kode LIKE '%$safeSearch%' OR j.keterangan LIKE '%$safeSearch%' OR u.nama LIKE '%$safeSearch%')";
}

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM jurnal j");
$totalRecords = $totalQuery->fetch_assoc()['total'];

$filteredQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM jurnal j 
    JOIN `user` u ON u.id = j.user_id 
    $where
");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

$sql = "SELECT j.id, j.tanggal, j.kode, j.keterangan, j.timestamp, u.nama AS nama_admin 
        FROM jurnal j 
        JOIN `user` u ON u.id = j.user_id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $jurnal_id = $row['id'];

    // Ambil detail akun jurnal (debit / kredit)
    $detailQuery = $conn->query("
        SELECT a.kode AS kode_akun, a.nama AS nama_akun, jd.debit, jd.kredit 
        FROM jurnal_detail jd 
        JOIN akun a ON a.id = jd.akun_id 
        WHERE jd.jurnal_id = $jurnal_id
    ");

    $htmlList = '<ul style="list-style-type: none; padding-left: 0; margin-bottom: 0;">';
    while ($det = $detailQuery->fetch_assoc()) {
        $debitVal = (float)$det['debit'];
        $kreditVal = (float)$det['kredit'];
        
        $nominalStr = '';
        if ($debitVal > 0) {
            $nominalStr = '<span class="text-success">D: ' . rp($debitVal) . '</span>';
        } else {
            $nominalStr = '<span class="text-danger">K: ' . rp($kreditVal) . '</span>';
        }

        $htmlList .= '<li><small><strong>' . h($det['kode_akun'] . ' - ' . $det['nama_akun']) . '</strong>: ' . $nominalStr . '</small></li>';
    }
    $htmlList .= '</ul>';

    $aksi = '
        <a href="jurnal_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat Detail">
            <i class="fas fa-eye"></i>
        </a>
        <a href="jurnal_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode']),
        h($row['nama_admin']),
        h($row['keterangan'] ?: '-'),
        $htmlList,
        h($row['timestamp']),
        $aksi
    ];
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($filteredRecords),
    "data" => $data
]);