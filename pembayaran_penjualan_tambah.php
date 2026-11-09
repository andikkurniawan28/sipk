<?php
$page_title = "Tambah Pembayaran Penjualan";
include 'config.php';

function bayar_kode_baru(mysqli $conn): string {
    $prefix = 'PLS' . date('ymd');
    $stmt = $conn->prepare("SELECT kode FROM pembayaran_penjualan WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $noUrut = $res ? (int)substr($res['kode'], -5) + 1 : 1;
    return $prefix . str_pad($noUrut, 5, '0', STR_PAD_LEFT);
}

$tanggal_ini = date('Y-m-d');
$kode_otomatis = bayar_kode_baru($conn);

$penjualanList = $conn->query("
    SELECT pn.id, pn.kode, pn.tanggal, pn.grand_total,
           COALESCE(b.bayar, 0) AS sudah_dibayar,
           pn.grand_total - COALESCE(b.bayar, 0) AS sisa,
           plg.nama AS nama_pelanggan
    FROM penjualan pn
    JOIN pelanggan plg ON plg.id = pn.pelanggan_id
    LEFT JOIN (
        SELECT penjualan_id, SUM(total) AS bayar
        FROM pembayaran_penjualan
        GROUP BY penjualan_id
    ) b ON b.penjualan_id = pn.id
    WHERE pn.grand_total - COALESCE(b.bayar, 0) > 0
    ORDER BY pn.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Ambil akun kas/bank (kelompok Aset, kode 101.xx)
$akunList = $conn->query("
    SELECT id, kode, nama
    FROM akun
    WHERE kelompok = 'Aset' AND kode LIKE '101.%'
    ORDER BY kode ASC
")->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<form action="pembayaran_penjualan_simpan.php" method="post" autocomplete="off" id="form-pembayaran">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Informasi</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Kode</label>
                    <input type="text" class="form-control" name="kode" value="<?= h($kode_otomatis) ?>" required readonly>
                </div>
                <div class="form-group col-md-3">
                    <label>Tanggal</label>
                    <input type="date" class="form-control" name="tanggal" value="<?= h($tanggal_ini) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Faktur</label>
                    <select class="form-control select2" id="penjualan_id" name="penjualan_id" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($penjualanList as $pn): ?>
                            <option value="<?= (int)$pn['id'] ?>" data-sisa="<?= h($pn['sisa']) ?>">
                                <?= h($pn['kode'] . ' - ' . $pn['nama_pelanggan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Akun</label>
                    <select class="form-control select2" name="akun_id" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($akunList as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= h($a['kode'] . ' - ' . $a['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Sisa Tagihan -->
    <div class="card shadow mb-4" id="card-info" style="display: none;">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Status</h6>
        </div>
        <div class="card-body" id="info-container">
            <!-- Info total tagihan, sudah dibayar, sisa tagihan -->
        </div>
    </div>

    <div class="card shadow mb-4" id="card-nominal" style="display: none;">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Nominal</h6>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Jumlah</label>
                    <input type="number" class="form-control text-right" name="total" id="total" min="0.01" step="any" required>
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

    <div class="mb-4">
        <button type="submit" class="btn btn-primary btn-sm" id="btn-simpan" style="display: none;"><i class="fas fa-save"></i> Simpan</button>
        <a href="pembayaran_penjualan_index.php" class="btn btn-secondary btn-sm">Batal</a>
    </div>
</form>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
window.addEventListener('load', function () {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    var sisaMax = 0;
    var xhrStatus = null;
    var elTotal = document.getElementById('total');

    function formatRupiah(angka) {
        var n = parseFloat(angka);
        if (isNaN(n) || n < 0) n = 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    // Nilai yang diketik TIDAK diubah. Hanya tampilan terformat dan validasi yang diperbarui.
    $('#total').on('input change', function () {
        var v = parseFloat(this.value);

        this.setCustomValidity('');
        $('#total_format').text(formatRupiah(v));

        if (sisaMax > 0 && v > sisaMax) {
            this.setCustomValidity('Jumlah tidak boleh melebihi sisa tagihan (' + formatRupiah(sisaMax) + ').');
            $('#total_warning').removeClass('d-none');
        } else {
            $('#total_warning').addClass('d-none');
        }
    });

    $('#penjualan_id').on('change', function () {
        var penjualanId = $(this).val();

        if (xhrStatus) { xhrStatus.abort(); xhrStatus = null; }

        // Reset nominal setiap ganti faktur
        $('#total').val('');
        elTotal.setCustomValidity('');
        $('#total_format').text('Rp 0');
        $('#total_warning').addClass('d-none');

        if (!penjualanId) {
            sisaMax = 0;
            $('#total').removeAttr('max');
            $('#card-info, #card-nominal, #btn-simpan').hide();
            return;
        }

        // Sisa diambil dari atribut option (string "40000.00")
        sisaMax = parseFloat($('#penjualan_id option:selected').attr('data-sisa')) || 0;
        $('#total').attr('max', sisaMax.toFixed(2));
        $('#total_max_label').text(formatRupiah(sisaMax));

        // Nominal dan tombol Simpan langsung tampil, tidak menunggu AJAX
        $('#card-nominal, #btn-simpan').show();

        // Kartu Status dimuat terpisah
        $('#info-container').html('<span class="text-muted">Memuat...</span>');
        $('#card-info').show();

        xhrStatus = $.ajax({
            url: 'get_piutang_status.php',
            type: 'GET',
            dataType: 'html',
            data: { penjualan_id: penjualanId }
        })
        .done(function (res) {
            $('#info-container').html(res);
        })
        .fail(function (xhr, status) {
            if (status === 'abort') return;
            $('#info-container').html('<div class="text-danger">Gagal memuat status tagihan.</div>');
        })
        .always(function () {
            xhrStatus = null;
        });
    });
});
</script>