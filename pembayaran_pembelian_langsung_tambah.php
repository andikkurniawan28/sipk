<?php
$page_title = "Tambah Pembayaran Pembelian Langsung";
include 'config.php';

function ppbl_kode_baru(mysqli $conn): string {
    $prefix = 'BBL' . date('ymd');
    $stmt = $conn->prepare("SELECT kode FROM pembayaran_pembelian_langsung WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;
    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

$tanggal_ini = date('Y-m-d');
$kode_otomatis = ppbl_kode_baru($conn);

// Penerimaan yang BELUM LUNAS (sisa > 0), termasuk yang sudah dibayar sebagian
$penerimaanList = $conn->query("
    SELECT ppl.id, ppl.kode AS kode_penerimaan, ppl.tanggal, pl.kode AS kode_pembelian,
           t.total_tagihan,
           COALESCE(b.bayar, 0) AS sudah_dibayar,
           ROUND(t.total_tagihan - COALESCE(b.bayar, 0), 2) AS sisa
    FROM penerimaan_pembelian_langsung ppl
    JOIN pembelian_langsung pl ON pl.id = ppl.pembelian_langsung_id
    JOIN (
        SELECT ppld.penerimaan_pembelian_langsung_id,
               SUM(ppld.qty * pld.harga) AS total_tagihan
        FROM penerimaan_pembelian_langsung_detail ppld
        JOIN penerimaan_pembelian_langsung p2 ON p2.id = ppld.penerimaan_pembelian_langsung_id
        JOIN pembelian_langsung_detail pld
              ON pld.pembelian_langsung_id = p2.pembelian_langsung_id
             AND pld.produk_id = ppld.produk_id
        GROUP BY ppld.penerimaan_pembelian_langsung_id
    ) t ON t.penerimaan_pembelian_langsung_id = ppl.id
    LEFT JOIN (
        SELECT penerimaan_pembelian_langsung_id, SUM(total) AS bayar
        FROM pembayaran_pembelian_langsung
        GROUP BY penerimaan_pembelian_langsung_id
    ) b ON b.penerimaan_pembelian_langsung_id = ppl.id
    WHERE ROUND(t.total_tagihan - COALESCE(b.bayar, 0), 2) > 0
    ORDER BY ppl.tanggal DESC, ppl.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Ambil akun kas/bank
$akunList = $conn->query("
    SELECT id, kode, nama 
    FROM akun 
    WHERE kode LIKE '101%' OR nama LIKE '%Kas%' OR nama LIKE '%Bank%' OR nama LIKE '%Rekening%' 
    ORDER BY kode ASC
")->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<style>
    body { overflow-x: hidden; }
    .container-fluid { padding-left: 1rem; padding-right: 1rem; }
    .card { margin-bottom: 0.75rem !important; }
    .card-body { padding: 0.75rem 1rem !important; }
    .form-group { margin-bottom: 0.5rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="h3 mb-0 text-gray-800"><?= h($page_title) ?></h1>
</div>

<form action="pembayaran_pembelian_langsung_simpan.php" method="post" autocomplete="off" id="form-pembayaran">
    <!-- Informasi -->
    <div class="card shadow">
        <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Informasi</h6></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Kode</label>
                    <input type="text" class="form-control form-control-sm" id="kode" name="kode" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-4">
                    <label>Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Akun</label>
                    <select class="form-control form-control-sm select2" id="akun_id" name="akun_id" required>
                        <option></option>
                        <?php foreach ($akunList as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= h($a['kode'] . ' - ' . $a['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label>Referensi (belum lunas)</label>
                    <select class="form-control form-control-sm select2" id="penerimaan_pembelian_langsung_id" name="penerimaan_pembelian_langsung_id" required>
                        <option></option>
                        <?php foreach ($penerimaanList as $ppl): ?>
                            <option value="<?= (int)$ppl['id'] ?>"
                                    data-tagihan="<?= h($ppl['total_tagihan']) ?>"
                                    data-dibayar="<?= h($ppl['sudah_dibayar']) ?>"
                                    data-sisa="<?= h($ppl['sisa']) ?>">
                                <?= h($ppl['kode_penerimaan'] . ' (Ref. Pembelian: ' . $ppl['kode_pembelian'] . ' - Tgl: ' . tanggal_indo($ppl['tanggal']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Tagihan -->
    <div class="card shadow" id="card-info" style="display: none;">
        <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Status</h6></div>
        <div class="card-body">
            <div class="form-row">
                <div class="col-md-4">Total Tagihan<br><strong id="info_tagihan">Rp 0</strong></div>
                <div class="col-md-4">Sudah Dibayar<br><strong class="text-success" id="info_dibayar">Rp 0</strong></div>
                <div class="col-md-4">Sisa Tagihan<br><strong class="text-danger" id="info_sisa">Rp 0</strong></div>
            </div>
        </div>
    </div>

    <!-- Nominal -->
    <div class="card shadow" id="card-nominal" style="display: none;">
        <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Nominal</h6></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Jumlah</label>
                    <input type="number" class="form-control form-control-sm text-right" name="total" id="total" min="0.01" step="any" required>
                    <small class="form-text text-right text-muted">
                        Terformat: <strong class="text-primary" id="total_format">Rp 0</strong><br>
                        Maksimal (sisa tagihan): <strong class="text-danger" id="total_max_label">Rp 0</strong>
                    </small>
                    <small class="form-text text-right text-danger d-none" id="total_warning">
                        Jumlah melebihi sisa tagihan. Silakan masukkan nominal yang sesuai.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="mt-2 mb-3">
        <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan" style="display: none;"><i class="fas fa-save"></i> Simpan</button>
        <a href="pembayaran_pembelian_langsung_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
$(function () {
    var sisaMax = 0;
    var elTotal = document.getElementById('total');

    function formatRupiah(angka) {
        var n = parseFloat(angka);
        if (isNaN(n) || n < 0) n = 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function validasiTotal() {
        var v = parseFloat(elTotal.value);
        elTotal.setCustomValidity('');
        $('#total_format').text(formatRupiah(v));

        if (sisaMax > 0 && v > sisaMax + 0.005) {
            elTotal.setCustomValidity('Jumlah tidak boleh melebihi sisa tagihan (' + formatRupiah(sisaMax) + ').');
            $('#total_warning').removeClass('d-none');
        } else {
            $('#total_warning').addClass('d-none');
        }
    }

    $('#total').on('input change', validasiTotal);

    $('#penerimaan_pembelian_langsung_id').on('change', function () {
        var id = $(this).val();

        $('#total').val('');
        elTotal.setCustomValidity('');
        $('#total_format').text('Rp 0');
        $('#total_warning').addClass('d-none');

        if (!id) {
            sisaMax = 0;
            $('#total').removeAttr('max');
            $('#card-info, #card-nominal, #btn-simpan').hide();
            return;
        }

        var $opt = $(this).find('option:selected');
        var tagihan = parseFloat($opt.attr('data-tagihan')) || 0;
        var dibayar = parseFloat($opt.attr('data-dibayar')) || 0;
        sisaMax = parseFloat($opt.attr('data-sisa')) || 0;

        $('#info_tagihan').text(formatRupiah(tagihan));
        $('#info_dibayar').text(formatRupiah(dibayar));
        $('#info_sisa').text(formatRupiah(sisaMax));
        $('#total_max_label').text(formatRupiah(sisaMax));

        // Default: bayar lunas sisa; boleh diubah untuk bayar sebagian
        $('#total').attr('max', sisaMax.toFixed(2)).val(sisaMax.toFixed(2));
        validasiTotal();

        $('#card-info, #card-nominal, #btn-simpan').show();
    });

    $('#form-pembayaran').on('submit', function (e) {
        var v = parseFloat(elTotal.value);
        if (!(v > 0) || v > sisaMax + 0.005) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Jumlah tidak valid', text: 'Jumlah harus lebih dari 0 dan tidak melebihi sisa tagihan.' });
        }
    });
});
</script>