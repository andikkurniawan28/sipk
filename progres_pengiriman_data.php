<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

$draw   = (int)($_GET['draw'] ?? 1);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length <= 0 || $length > 500) $length = 500;
$cari   = trim($_GET['search']['value'] ?? '');
$status = $_GET['status'] ?? '';

// Indeks kolom tabel => kolom SQL (0 = No, 8 = Progres, 10 = Surat Jalan tidak bisa diurutkan)
$columns = [
    1 => 'pn.tanggal',
    2 => 'pn.kode',
    3 => 'plg.nama',
    4 => 'pr.nama',
    5 => 'd.qty_pesan',
    6 => 'terkirim',
    7 => 'sisa',
    9 => 'terkirim',
];
$idx      = (int)($_GET['order'][0]['column'] ?? 1);
$orderBy  = $columns[$idx] ?? 'pn.tanggal';
$orderDir = (($_GET['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

// Satu baris per (faktur, produk): dipesan dari penjualan_detail, terkirim dari pengiriman_produk_detail
$dasar = "
    FROM (
        SELECT penjualan_id, produk_id, SUM(qty) AS qty_pesan
        FROM penjualan_detail
        GROUP BY penjualan_id, produk_id
    ) d
    JOIN penjualan pn ON pn.id = d.penjualan_id
    JOIN pelanggan plg ON plg.id = pn.pelanggan_id
    JOIN produk pr ON pr.id = d.produk_id
    LEFT JOIN (
        SELECT pk.penjualan_id, pd.produk_id,
               SUM(pd.qty) AS qty_kirim,
               GROUP_CONCAT(DISTINCT CONCAT(pk.id, ':', pk.kode) ORDER BY pk.id SEPARATOR ',') AS daftar_sj
        FROM pengiriman_produk_detail pd
        JOIN pengiriman_produk pk ON pk.id = pd.pengiriman_produk_id
        GROUP BY pk.penjualan_id, pd.produk_id
    ) k ON k.penjualan_id = d.penjualan_id AND k.produk_id = d.produk_id
";
$terkirimExpr = "COALESCE(k.qty_kirim, 0)";

$where  = "WHERE 1=1";
$types  = '';
$params = [];

if ($cari !== '') {
    $where   .= " AND (pn.kode LIKE ? OR plg.nama LIKE ? OR pr.nama LIKE ? OR pr.kode LIKE ?)";
    $like     = '%' . $cari . '%';
    $types   .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

switch ($status) {
    case 'belum':
        $where .= " AND $terkirimExpr <= 0.0001";
        break;
    case 'sebagian':
        $where .= " AND $terkirimExpr > 0.0001 AND d.qty_pesan - $terkirimExpr > 0.0001";
        break;
    case 'lengkap':
        $where .= " AND d.qty_pesan - $terkirimExpr <= 0.0001";
        break;
    case 'belum_lengkap':
        $where .= " AND d.qty_pesan - $terkirimExpr > 0.0001";
        break;
}

$totalRecords = (int)$conn->query("SELECT COUNT(*) FROM (SELECT 1 FROM penjualan_detail GROUP BY penjualan_id, produk_id) x")->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) $dasar $where");
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$filteredRecords = (int)$stmt->get_result()->fetch_row()[0];
$stmt->close();

$sql = "SELECT pn.id AS penjualan_id, pn.kode, pn.tanggal, plg.nama AS nama_pelanggan,
               pr.kode AS kode_produk, pr.nama AS nama_produk, pr.satuan,
               d.qty_pesan, $terkirimExpr AS terkirim,
               (d.qty_pesan - $terkirimExpr) AS sisa,
               k.daftar_sj
        $dasar $where
        ORDER BY $orderBy $orderDir, pn.id DESC, pr.nama ASC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types . 'ii', ...array_merge($params, [$start, $length]));
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function angka(float $n): string
{
    return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
}

$data = [];
$no   = $start + 1;
foreach ($rows as $r) {
    $pesan    = (float)$r['qty_pesan'];
    $terkirim = (float)$r['terkirim'];
    $sisa     = max(0.0, round($pesan - $terkirim, 2));
    $persen   = $pesan > 0 ? min(100.0, round($terkirim / $pesan * 100, 1)) : 0.0;

    if ($terkirim <= 0.0001) {
        $badge = '<span class="badge badge-secondary">Belum dikirim</span>';
        $warna = 'bg-secondary';
    } elseif ($sisa <= 0.0001) {
        $badge = '<span class="badge badge-success">Lengkap</span>';
        $warna = 'bg-success';
    } else {
        $badge = '<span class="badge badge-warning">Sebagian</span>';
        $warna = 'bg-warning';
    }

    $bar = '<div class="progress" style="height:18px">'
         . '<div class="progress-bar ' . $warna . '" role="progressbar" style="width:' . $persen . '%">'
         . str_replace('.', ',', (string)$persen) . '%</div></div>';

    // Daftar surat jalan yang memuat produk ini
    $linkSj = [];
    if (!empty($r['daftar_sj'])) {
        foreach (explode(',', $r['daftar_sj']) as $sj) {
            [$sjId, $sjKode] = explode(':', $sj, 2) + [1 => ''];
            $linkSj[] = '<a href="pengiriman_produk_lihat.php?id=' . (int)$sjId . '">' . h($sjKode) . '</a>';
        }
    }

    $data[] = [
        $no++,
        h(tanggal_indo($r['tanggal'])),
        h($r['kode']),
        h($r['nama_pelanggan']),
        h($r['kode_produk'] . ' - ' . $r['nama_produk']),
        angka($pesan) . ' ' . h($r['satuan']),
        angka($terkirim),
        '<span class="' . ($sisa > 0 ? 'text-danger font-weight-bold' : '') . '">' . angka($sisa) . '</span>',
        $bar,
        $badge,
        $linkSj ? implode(', ', $linkSj) : '-',
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data'            => $data,
]);