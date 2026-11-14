<?php
$page_title = "Home";
include 'config.php';
include 'header.php';
?>

<h1 class="h3 mb-4 text-gray-800"><?= h($page_title) ?></h1>

<div id="dash-error" class="alert alert-danger d-none"></div>

<!-- Baris 1: data master -->
<!-- <div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Jumlah Pemasok</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-pemasok">-</div>
                </div>
                <div class="col-auto"><i class="fas fa-truck fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Jumlah Pelanggan</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-pelanggan">-</div>
                </div>
                <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Jumlah Wilayah</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-wilayah">-</div>
                </div>
                <div class="col-auto"><i class="fas fa-map-marked-alt fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Harga Beli Kelapa Tua Utuh</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-harga-kelapa">-</div>
                    <div class="small mt-1 text-muted" id="s-harga-kelapa"></div>
                </div>
                <div class="col-auto"><i class="fas fa-tag fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>
</div> -->

<!-- Baris 2: stok -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Stok Kelapa Tua Utuh</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-stok-kelapa">-</div>
                </div>
                <div class="col-auto"><i class="fas fa-seedling fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Stok VCO</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-stok-vco">-</div>
                </div>
                <div class="col-auto"><i class="fas fa-flask fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Stok Sabut</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-stok-sabut">-</div>
                </div>
                <div class="col-auto"><i class="fas fa-leaf fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Stok Tempurung</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-stok-tempurung">-</div>
                </div>
                <div class="col-auto"><i class="fas fa-cubes fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>
</div>

<!-- Baris produksi hari ini -->
<div class="row">
    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Kelapa Tua Utuh Diolah</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-kelapa-diolah">-</div>
                    <div class="small mt-1 text-muted">Dari pengambilan bahan hari ini</div>
                </div>
                <div class="col-auto"><i class="fas fa-industry fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>

    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2"><div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">VCO Diproduksi</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800" id="c-vco-produksi">-</div>
                    <div class="small mt-1 text-muted">Dari serah terima produksi hari ini</div>
                </div>
                <div class="col-auto"><i class="fas fa-oil-can fa-2x text-gray-300"></i></div>
            </div>
        </div></div>
    </div>
</div>

<!-- Baris 3: grafik pembelian dan penjualan (6 : 6) -->
<div class="row">
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info">Pembelian 12 Bulan Terakhir</h6>
            </div>
            <div class="card-body">
                <div style="position:relative;height:300px"><canvas id="chPembelian"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Penjualan 12 Bulan Terakhir</h6>
            </div>
            <div class="card-body">
                <div style="position:relative;height:300px"><canvas id="chPenjualan"></canvas></div>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
include 'scripts.php';
?>

<script>
(function () {
    const num = n => Number(n || 0).toLocaleString('id-ID', {maximumFractionDigits: 2});
    const rp  = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID', {maximumFractionDigits: 0});
    const el  = id => document.getElementById(id);

    function jumlah(id, v) {
        el(id).textContent = (v === null || v === undefined) ? '-' : num(v);
    }

    function stok(id, p) {
        if (!p || !p.ada) { el(id).textContent = '-'; return; }
        el(id).textContent = num(p.stok) + ' ' + p.satuan;
        if (p.stok < 0) el(id).classList.add('text-danger');
    }

        // Pilih satuan berdasarkan nilai terbesar, supaya angka sumbu selalu wajar
    function satuan(maks) {
        if (maks >= 1e9) return {bagi: 1e9, akhiran: ' M'};
        if (maks >= 1e6) return {bagi: 1e6, akhiran: ' jt'};
        if (maks >= 1e3) return {bagi: 1e3, akhiran: ' rb'};
        return {bagi: 1, akhiran: ''};
    }

    function grafik(id, label, data, warna, labels) {
        const maks = Math.max.apply(null, data.concat([0]));
        const sat  = satuan(maks);

        new Chart(el(id), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{label: label, data: data, backgroundColor: warna}]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            // Angka bulat di sumbu; desimal hanya jika benar-benar perlu (mis. 1,5 jt)
                            callback: v => Number(v / sat.bagi).toLocaleString('id-ID', {maximumFractionDigits: 1}) + sat.akhiran
                        }
                    }
                },
                plugins: {
                    legend: {display: false},
                    tooltip: {callbacks: {label: c => label + ': ' + rp(c.parsed.y)}}
                }
            }
        });
    }

    function render(d) {
        // jumlah('c-pemasok', d.pemasok);
        // jumlah('c-pelanggan', d.pelanggan);
        // jumlah('c-wilayah', d.wilayah);

        // if (d.kelapa && d.kelapa.ada) {
        //     el('c-harga-kelapa').textContent = rp(d.kelapa.harga_beli);
        //     el('s-harga-kelapa').textContent = 'per ' + d.kelapa.satuan + ' (pembelian terakhir)';
        // }

        stok('c-stok-kelapa', d.kelapa);
        stok('c-stok-vco', d.vco);
        stok('c-stok-sabut', d.sabut);
        stok('c-stok-tempurung', d.tempurung);

        el('c-kelapa-diolah').textContent = num(d.hari_ini.kelapa_diolah) + ' ' + (d.kelapa.satuan || '');
        el('c-vco-produksi').textContent  = num(d.hari_ini.vco_diproduksi) + ' ' + (d.vco.satuan || '');
    }

    function muat() {
        fetch('api_dashboard.php', {credentials: 'same-origin'})
            .then(r => r.json().then(j => ({ok: r.ok, j})))
            .then(({ok, j}) => {
                if (!ok) throw new Error(j.detail || j.error || 'Gagal memuat');
                render(j);
                grafik('chPembelian', 'Pembelian', j.tren.pembelian, '#36b9cc', j.tren.label);
                grafik('chPenjualan', 'Penjualan', j.tren.penjualan, '#4e73df', j.tren.label);
            })
            .catch(e => {
                const box = el('dash-error');
                box.textContent = 'Dashboard gagal dimuat: ' + e.message;
                box.classList.remove('d-none');
            });
    }

    // Chart.js v4 dari CDN, dimuat dinamis agar tidak bentrok dengan versi bawaan template
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    s.onload = muat;
    s.onerror = () => {
        el('dash-error').textContent = 'Chart.js gagal dimuat (cek koneksi internet).';
        el('dash-error').classList.remove('d-none');
    };
    document.body.appendChild(s);
})();
</script>