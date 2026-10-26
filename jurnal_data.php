<?php
include 'config.php';

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

// Kolom untuk sorting
$columns = [
    0 => 'j.id',
    1 => 'j.tanggal',
    2 => 'j.kode',
    3 => 'u.nama',
    4 => 'j.keterangan'
];
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'j.tanggal';

// Base Query
$where = "WHERE 1=1";
if (!empty($searchValue)) {
    $safeSearch = $conn->real_escape_string($searchValue);
    $where .= " AND (j.kode LIKE '%$safeSearch%' OR j.keterangan LIKE '%$safeSearch%' OR u.nama LIKE '%$safeSearch%')";
}

// Total records
$totalQuery = $conn->query("SELECT COUNT(*) as total FROM jurnal j");
$totalRecords = $totalQuery->fetch_assoc()['total'];

// Filtered records
$filteredQuery = $conn->query("SELECT COUNT(*) as total FROM jurnal j JOIN `user` u ON j.user_id = u.id $where");
$filteredRecords = $filteredQuery->fetch_assoc()['total'];

// Data query utama jurnal
$sql = "SELECT j.id, j.tanggal, j.kode, u.nama as nama_user, j.keterangan 
        FROM jurnal j 
        JOIN `user` u ON j.user_id = u.id 
        $where 
        ORDER BY $orderBy $orderDir 
        LIMIT $start, $length";
$result = $conn->query($sql);

$data = [];
$no = $start + 1;
while ($row = $result->fetch_assoc()) {
    $jurnal_id = $row['id'];

    // Ambil detail jurnal untuk menyusun list dengan indentasi kredit
    $detailQuery = $conn->query("
        SELECT a.kode as kode_akun, a.nama as nama_akun, jd.debit, jd.kredit 
        FROM jurnal_detail jd 
        JOIN akun a ON jd.akun_id = a.id 
        WHERE jd.jurnal_id = $jurnal_id
    ");

    $htmlList = '<ul style="list-style-type: none; padding-left: 0; margin-bottom: 0;">';
    while ($det = $detailQuery->fetch_assoc()) {
        $isKredit = (float)$det['kredit'] > 0;
        // Berikan indentasi style (margin-left: 20px) jika akun tersebut di posisi kredit
        $styleIndent = $isKredit ? 'style="margin-left: 25px;"' : '';
        
        $infoNilai = $isKredit 
            ? number_format($det['kredit'], 2, ',', '.') 
            : number_format($det['debit'], 2, ',', '.');

        $htmlList .= '<li ' . $styleIndent . '>' . htmlspecialchars($det['kode_akun'] . ' - ' . $det['nama_akun'], ENT_QUOTES, 'UTF-8') . ': <span class="text-muted">' . $infoNilai . '</span></li>';
    }
    $htmlList .= '</ul>';

    $aksi = '
        <a href="jurnal_lihat.php?id=' . $row['id'] . '" class="btn btn-info btn-sm" title="Lihat Detail">
            <i class="fas fa-eye"></i>
        </a>
        <a href="jurnal_hapus.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Yakin ingin menghapus jurnal ini?\')" title="Hapus">
            <i class="fas fa-trash"></i>
        </a>
    ';

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        htmlspecialchars($row['kode'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($row['nama_user'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($row['keterangan'], ENT_QUOTES, 'UTF-8'),
        $htmlList, // Menampilkan list rincian dengan indentasi kredit
        $aksi
    ];
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($filteredRecords),
    "data" => $data
]);