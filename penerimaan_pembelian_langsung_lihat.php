<?php

include 'config.php';
require_once 'penerimaan_pembelian_langsung_core.php';

try {
    $penerimaan   = ambilPenerimaanLengkap($conn, ambilIdDariUrl());
    $daftarProduk = ambilDetailProdukPenerimaan($conn, (int)$penerimaan['id']);
} catch (DomainException $e) {
    alihkanDenganPesanGagal('Tidak ditemukan', $e->getMessage(), 'penerimaan_pembelian_langsung_index.php');
}

$page_title = 'Penerimaan ' . $penerimaan['kode'];
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h1 class="h3 mb-0 text-gray-800">Detail Penerimaan Pembelian Langsung</h1>
    <div>
        <a href="penerimaan_pembelian_langsung_index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body p-5">
        <div class="row mb-4">
            <div class="col-sm-7">
                <h2 class="h3 font-weight-bold text-primary invoice-judul mb-1">BUKTI PENERIMAAN PEMBELIAN</h2>
                <div class="text-gray-800 font-weight-bold"><?= h($penerimaan['kode']) ?></div>
            </div>
            <div class="col-sm-5 text-sm-right">
                <div class="invoice-label">Tanggal</div>
                <div class="font-weight-bold text-gray-800"><?= h(tanggal_indo($penerimaan['tanggal'])) ?></div>
            </div>
        </div>

        <hr>

        <div class="row mb-4">
            <div class="col-sm-4">
                <div class="invoice-label">Faktur Pembelian</div>
                <div class="font-weight-bold text-gray-800"><?= h($penerimaan['kode_pembelian']) ?></div>
            </div>
            <div class="col-sm-4 text-center">
                <div class="invoice-label">Pemasok</div>
                <div class="font-weight-bold text-gray-800"><?= h($penerimaan['pemasok_nama']) ?></div>
            </div>
            <div class="col-sm-4 text-right">
                <div class="invoice-label">Gudang</div>
                <div class="font-weight-bold text-gray-800"><?= h($penerimaan['gudang_nama']) ?></div>
                <?php if ($penerimaan['gudang_alamat']): ?><div class="small text-gray-700"><?= nl2br(h($penerimaan['gudang_alamat'])) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead class="thead-light">
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Produk</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarProduk as $urutan => $produk): ?>
                        <tr>
                            <td><?= $urutan + 1 ?></td>
                            <td><?= h($produk['kode']) ?> - <?= h($produk['nama_produk']) ?></td>
                            <td class="text-right"><?= rp($produk['qty']) . ' ' . h($produk['satuan']) ?></td>
                            <td class="text-right"><?= rp($produk['harga']) ?></td>
                            <td class="text-right"><?= rp($produk['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row">
            <div class="col-md-6 offset-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr class="border-top">
                        <td class="font-weight-bold h5 pt-3">Total</td>
                        <td class="text-right font-weight-bold h5 pt-3 text-primary"><?= rp($penerimaan['total']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <hr class="mt-4">
        <div class="d-flex justify-content-between small text-gray-600">
            <span>Admin: <?= h($penerimaan['dibuat_oleh']) ?></span>
            <span>Dicetak: <?= date('d-m-Y H:i') ?></span>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>