<?php

const KOLOM_URUT_AWAL_PEMBELIAN = 2;

const KOLOM_TABEL_PEMBELIAN = [
    ['judul' => 'No', 'lebar' => '50px', 'urut' => false],
    ['judul' => 'Tanggal'],
    ['judul' => 'Kode'],
    ['judul' => 'Pemasok'],
    ['judul' => 'Admin'],
    ['judul' => 'Detail', 'urut' => false],
    ['judul' => 'Total', 'rata' => 'right'],
    ['judul' => 'Timestamp'],
    ['judul' => 'Aksi', 'lebar' => '120px', 'rata' => 'center', 'urut' => false],
];

const LABEL_RINGKASAN_NOMINAL_PEMBELIAN = [
    'Subtotal' => 'subtotal',
    'Pajak'    => 'pajak',
    'Ongkir'   => 'ongkir',
    'Biaya'    => 'biaya',
    'Diskon'   => 'diskon',
];

const AKUN_PERSEDIAAN_BELUM_DITERIMA = 3;
const AKUN_HUTANG_PEMASOK            = 5;
const AKUN_PAJAK_MASUKAN             = 9;
const AKUN_BEBAN_ANGKUT              = 10;
const AKUN_BEBAN_OPERASIONAL_LAIN    = 11;
const AKUN_DISKON_PEMBELIAN          = 12;

function ambilHeaderPembelianDariForm(array $form): array
{
    return [
        'tanggal'    => ambilTeksDariForm($form, 'tanggal'),
        'kode'       => ambilTeksDariForm($form, 'kode'),
        'pemasok_id' => ambilIdDariForm($form, 'pemasok_id'),
        'pajak'      => round(ambilAngkaDariForm($form, 'pajak'), 2),
        'ongkir'     => round(ambilAngkaDariForm($form, 'ongkir'), 2),
        'biaya'      => round(ambilAngkaDariForm($form, 'biaya'), 2),
        'diskon'     => round(ambilAngkaDariForm($form, 'diskon'), 2),
    ];
}

function pastikanHeaderPembelianValid(array $header): void
{
    pastikanFormatTanggalValid($header['tanggal']);
    pastikanTidakKosong($header['kode'], 'Kode pembelian');
    pastikanTerpilih($header['pemasok_id'], 'Pemasok');
    pastikanTidakNegatif($header['pajak'], 'Pajak');
    pastikanTidakNegatif($header['ongkir'], 'Ongkir');
    pastikanTidakNegatif($header['biaya'], 'Biaya');
    pastikanTidakNegatif($header['diskon'], 'Diskon');
}

function ambilBarisProdukPembelianDariForm(array $form): array
{
    $produkIds = $form['produk_id'] ?? [];
    $qtys      = $form['qty'] ?? [];
    $hargas    = $form['harga'] ?? [];

    if (!is_array($produkIds) || !is_array($qtys) || !is_array($hargas)) {
        throw new DomainException('Struktur data produk tidak valid.');
    }

    $barisProduk = [];

    foreach ($produkIds as $i => $produkId) {
        if ((int)$produkId <= 0) {
            continue;
        }

        $qty   = (float)($qtys[$i] ?? 0);
        $harga = (float)($hargas[$i] ?? 0);

        pastikanTidakNegatif($qty, 'Qty');
        pastikanTidakNegatif($harga, 'Harga');

        $barisProduk[] = [
            'produk_id' => (int)$produkId,
            'qty'       => $qty,
            'harga'     => $harga,
            'total'     => round($qty * $harga, 2),
        ];
    }

    if (empty($barisProduk)) {
        throw new DomainException('Minimal satu produk harus diisi.');
    }

    return $barisProduk;
}

function hitungGrandTotalPembelian(array $header, float $subtotal): float
{
    return round($subtotal + $header['pajak'] + $header['ongkir'] + $header['biaya'] - $header['diskon'], 2);
}

function pastikanGrandTotalTidakNegatif(float $grandTotal): void
{
    if ($grandTotal < 0) {
        throw new DomainException('Diskon tidak boleh melebihi total pembelian.');
    }
}

function susunBarisAkunPembelian(array $header, float $subtotal, float $grandTotal): array
{
    return hanyaBarisBernilai([
        barisAkunDebit(AKUN_PERSEDIAAN_BELUM_DITERIMA, $subtotal),
        barisAkunDebit(AKUN_PAJAK_MASUKAN, $header['pajak']),
        barisAkunDebit(AKUN_BEBAN_ANGKUT, $header['ongkir']),
        barisAkunDebit(AKUN_BEBAN_OPERASIONAL_LAIN, $header['biaya']),
        barisAkunKredit(AKUN_DISKON_PEMBELIAN, $header['diskon']),
        barisAkunKredit(AKUN_HUTANG_PEMASOK, $grandTotal),
    ]);
}

function simpanHeaderPembelian(mysqli $conn, array $header, float $subtotal, float $grandTotal, int $userId): int
{
    $stmt = $conn->prepare(
        "INSERT INTO pembelian_langsung (tanggal, kode, pemasok_id, user_id, subtotal, pajak, ongkir, biaya, diskon, grand_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'ssiidddddd',
        $header['tanggal'],
        $header['kode'],
        $header['pemasok_id'],
        $userId,
        $subtotal,
        $header['pajak'],
        $header['ongkir'],
        $header['biaya'],
        $header['diskon'],
        $grandTotal
    );
    $stmt->execute();
    $pembelianId = $conn->insert_id;
    $stmt->close();

    return $pembelianId;
}

function simpanDetailPembelian(mysqli $conn, int $pembelianId, array $barisProduk): void
{
    $baris = [];

    foreach ($barisProduk as $produk) {
        $baris[] = [$pembelianId, $produk['produk_id'], $produk['qty'], $produk['harga'], $produk['total']];
    }

    insertBanyakBaris(
        $conn,
        'pembelian_langsung_detail',
        ['pembelian_langsung_id', 'produk_id', 'qty', 'harga', 'total'],
        'iiddd',
        $baris
    );
}

function keteranganBukuBesarPembelian(array $header): string
{
    return "Pembelian Langsung - {$header['kode']}.";
}

const DEFINISI_TABEL_PEMBELIAN = [
    'tabelUtama'  => 'pembelian_langsung',
    'select'      => 'pl.id, pl.tanggal, pl.kode, pl.grand_total, pl.timestamp, p.nama AS nama_pemasok, u.nama AS nama_admin',
    'dari'        => 'FROM pembelian_langsung pl JOIN pemasok p ON p.id = pl.pemasok_id JOIN `user` u ON u.id = pl.user_id',
    'kolomCari'   => ['pl.kode', 'p.nama', 'u.nama'],
    'kolomUrut'   => [
        0 => 'pl.id',
        1 => 'pl.tanggal',
        2 => 'pl.kode',
        3 => 'p.nama',
        4 => 'u.nama',
        6 => 'pl.grand_total',
        7 => 'pl.timestamp',
    ],
    'urutDefault' => 'pl.tanggal',
    'kolomId'     => 'pl.id',
];

function ambilDetailProdukPerPembelian(mysqli $conn, array $pembelianIds): array
{
    if (empty($pembelianIds)) {
        return [];
    }

    $daftarId = implode(',', array_map('intval', $pembelianIds));

    $hasil = $conn->query("
        SELECT pld.pembelian_langsung_id, pr.kode, pr.nama AS nama_produk, pr.satuan, pld.qty, pld.harga, pld.total
        FROM pembelian_langsung_detail pld
        JOIN produk pr ON pr.id = pld.produk_id
        WHERE pld.pembelian_langsung_id IN ({$daftarId})
        ORDER BY pld.id
    ");

    return kelompokkanBerdasarkanKolom($hasil, 'pembelian_langsung_id');
}

function htmlDaftarProdukPembelian(array $daftarProduk): string
{
    $itemProduk = '';

    foreach ($daftarProduk as $produk) {
        $itemProduk .= '<li>' . h($produk['kode']) . ' - ' . h($produk['nama_produk'])
                     . ' <span class="text-muted">(' . rp($produk['qty']) . ' ' . h($produk['satuan']) . ')</span></li>';
    }

    return '<ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 0;">' . $itemProduk . '</ul>';
}

function htmlTombolAksiPembelian(int $pembelianId): string
{
    return '<a href="pembelian_langsung_lihat.php?id=' . $pembelianId . '" class="btn btn-info btn-sm" title="Lihat Detail"><i class="fas fa-eye"></i></a> '
         . '<a href="pembelian_langsung_hapus.php?id=' . $pembelianId . '" class="btn btn-danger btn-sm btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>';
}

function barisTabelPembelian(int $nomor, array $pembelian, array $daftarProduk): array
{
    return [
        $nomor,
        h(tanggal_indo($pembelian['tanggal'])),
        h($pembelian['kode']),
        h($pembelian['nama_pemasok']),
        h($pembelian['nama_admin']),
        htmlDaftarProdukPembelian($daftarProduk),
        rp($pembelian['grand_total']),
        h($pembelian['timestamp']),
        htmlTombolAksiPembelian((int)$pembelian['id']),
    ];
}

function ambilPembelianLengkap(mysqli $conn, int $pembelianId): array
{
    $stmt = $conn->prepare("
        SELECT pl.*, p.nama AS pemasok_nama, p.alamat AS pemasok_alamat, p.telepon AS pemasok_telepon, u.nama AS dibuat_oleh
        FROM pembelian_langsung pl
        JOIN pemasok p ON p.id = pl.pemasok_id
        JOIN `user` u ON u.id = pl.user_id
        WHERE pl.id = ? LIMIT 1
    ");
    $stmt->bind_param('i', $pembelianId);
    $stmt->execute();
    $pembelian = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pembelian) {
        throw new DomainException('Data pembelian tidak ada.');
    }

    return $pembelian;
}

function ambilDetailProdukPembelian(mysqli $conn, int $pembelianId): array
{
    return ambilDetailProdukPerPembelian($conn, [$pembelianId])[$pembelianId] ?? [];
}

function kunciBarisPembelian(mysqli $conn, int $pembelianId): void
{
    $stmt = $conn->prepare("SELECT id FROM pembelian_langsung WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $pembelianId);
    $stmt->execute();
    $ditemukan = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$ditemukan) {
        throw new DomainException('Data pembelian tidak ditemukan.');
    }
}

function pastikanPembelianBelumDiterima(mysqli $conn, int $pembelianId): void
{
    $jumlahPenerimaan = hitungBaris(
        $conn,
        'SELECT COUNT(*) FROM penerimaan_pembelian_langsung WHERE pembelian_langsung_id = ?',
        'i',
        [$pembelianId]
    );

    if ($jumlahPenerimaan > 0) {
        throw new DomainException('Pembelian ini sudah diterima gudang, jadi tidak bisa dihapus. Hapus data penerimaannya terlebih dahulu.');
    }
}

function hapusBarisPembelian(mysqli $conn, int $pembelianId): void
{
    $stmt = $conn->prepare("DELETE FROM pembelian_langsung WHERE id = ?");
    $stmt->bind_param('i', $pembelianId);
    $stmt->execute();
    $stmt->close();
}

function hapusPembelian(mysqli $conn, int $pembelianId): void
{
    jalankanDalamTransaksi($conn, function () use ($conn, $pembelianId) {
        kunciBarisPembelian($conn, $pembelianId);
        pastikanPembelianBelumDiterima($conn, $pembelianId);
        hapusBarisPembelian($conn, $pembelianId);
    });
}

const ID_PRODUK_PEMBELIAN_LANGSUNG = [1, 2];

function ambilDaftarPemasok(mysqli $conn): array
{
    return $conn->query("SELECT id, kode, nama FROM pemasok ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
}

function ambilDaftarProdukPembelian(mysqli $conn): array
{
    $daftarId = implode(',', ID_PRODUK_PEMBELIAN_LANGSUNG);

    return $conn->query("
        SELECT id, kode, nama, satuan, harga_beli
        FROM produk
        WHERE id IN ({$daftarId})
        ORDER BY nama DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

function htmlOpsiPemasok(array $daftarPemasok): string
{
    $opsi = '<option></option>';

    foreach ($daftarPemasok as $pemasok) {
        $opsi .= '<option value="' . (int)$pemasok['id'] . '">' . h($pemasok['kode'] . ' - ' . $pemasok['nama']) . '</option>';
    }

    return $opsi;
}

function htmlOpsiProduk(array $daftarProduk): string
{
    $opsi = '<option></option>';

    foreach ($daftarProduk as $produk) {
        $opsi .= '<option value="' . (int)$produk['id'] . '" data-harga="' . h($produk['harga_beli']) . '">'
               . h($produk['kode'] . ' - ' . $produk['nama'] . ' (' . $produk['satuan'] . ')') . '</option>';
    }

    return $opsi;
}

function htmlBarisProdukPembelian(array $daftarProduk): string
{
    return '<tr>'
        . '<td><select name="produk_id[]" class="form-control form-control-sm select2 produk-select" required>' . htmlOpsiProduk($daftarProduk) . '</select></td>'
        . '<td><input type="number" name="qty[]" class="form-control form-control-sm text-right qty" min="0" step="any" value="1" required></td>'
        . '<td><input type="number" name="harga[]" class="form-control form-control-sm text-right harga" min="0" step="any" value="0" required></td>'
        . '<td><input type="text" class="form-control form-control-sm text-right total-baris" value="0" readonly tabindex="-1"></td>'
        . '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-hapus-baris" title="Hapus"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
}