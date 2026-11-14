<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

$draw        = (int)($_GET['draw'] ?? 1);
$start       = max(0, (int)($_GET['start'] ?? 0));
$length      = (int)($_GET['length'] ?? 10);
if ($length <= 0 || $length > 500) $length = 500;
$searchValue = trim($_GET['search']['value'] ?? '');

// Indeks kolom tabel => kolom SQL (0 = No, 5 = Detail, 7 = Aksi tidak bisa diurutkan)
$columns = [
    1 => 'stp.tanggal',
    2 => 'stp.kode',
    3 => 'g.nama',
    4 => 'u.nama',
    6 => 'stp.timestamp',
];
$orderColumnIndex = (int)($_GET['order'][0]['column'] ?? 2);
$orderBy  = $columns[$orderColumnIndex] ?? 'stp.tanggal';
$orderDir = (($_GET['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

$dari = "
    FROM serah_terima_produksi stp
    JOIN gudang g ON g.id = stp.gudang_id
    JOIN `user` u ON u.id = stp.user_id
";

$where  = '';
$types  = '';
$params = [];
if ($searchValue !== '') {
    $where  = "WHERE (stp.kode LIKE ? OR g.nama LIKE ? OR u.nama LIKE ?)";
    $like   = '%' . $searchValue . '%';
    $types  = 'sss';
    $params = [$like, $like, $like];
}

$totalRecords = (int)$conn->query("SELECT COUNT(*) FROM serah_terima_produksi")->fetch_row()[0];

if ($where !== '') {
    $stmt = $conn->prepare("SELECT COUNT(*) $dari $where");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $filteredRecords = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
} else {
    $filteredRecords = $totalRecords;
}

$sql = "SELECT stp.id, stp.tanggal, stp.kode AS kode_serah, stp.timestamp,
               g.nama AS nama_gudang, u.nama AS nama_admin
        $dari
        $where
        ORDER BY $orderBy $orderDir, stp.id DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types . 'ii', ...array_merge($params, [$start, $length]));
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Detail produk semua baris halaman ini dalam SATU query (qty saja, tanpa harga/total)
$detailPerSerah = [];
if ($rows) {
    $ids   = array_map(fn($r) => (int)$r['id'], $rows);
    $daftar = implode(',', $ids);
    $hasil = $conn->query("
        SELECT stpd.serah_terima_produksi_id, pr.kode, pr.nama AS nama_produk, pr.satuan, stpd.qty
        FROM serah_terima_produksi_detail stpd
        JOIN produk pr ON pr.id = stpd.produk_id
        WHERE stpd.serah_terima_produksi_id IN ($daftar)
        ORDER BY stpd.id
    ");
    while ($d = $hasil->fetch_assoc()) {
        $detailPerSerah[(int)$d['serah_terima_produksi_id']][] = $d;
    }
}

$bolehHapus = canAccess('serah_terima_produksi_hapus.php');

$data = [];
$no   = $start + 1;
foreach ($rows as $row) {
    $id = (int)$row['id'];

    $htmlList = '<ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 0;">';
    foreach ($detailPerSerah[$id] ?? [] as $det) {
        $htmlList .= '<li>' . h($det['kode']) . ' - ' . h($det['nama_produk'])
                   . ' <span class="text-muted">(' . rp($det['qty']) . ' ' . h($det['satuan']) . ')</span></li>';
    }
    $htmlList .= '</ul>';

    $aksi = '<a href="serah_terima_produksi_lihat.php?id=' . $id . '" class="btn btn-info btn-sm" title="Lihat"><i class="fas fa-eye"></i></a>';
    if ($bolehHapus) {
        $aksi .= ' <a href="serah_terima_produksi_hapus.php?id=' . $id . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>';
    }

    $data[] = [
        $no++,
        h(tanggal_indo($row['tanggal'])),
        h($row['kode_serah']),
        h($row['nama_gudang']),
        h($row['nama_admin']),
        $htmlList,
        h($row['timestamp']),
        $aksi,
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data'            => $data,
]);