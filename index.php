<?php
$page_title = "Home";
include 'config.php';
include 'header.php';
?>

    <h1 class="h3 mb-4 text-gray-800"><?= $page_title ?></h1>

    <div id="dash-error" class="alert alert-danger d-none"></div>

    <!-- Content Row: Kartu utama -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2"><div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Penjualan Bulan Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="c-penjualan">-</div>
                        <div class="small mt-1" id="t-penjualan"></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
                </div>
            </div></div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2"><div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Laba Kotor Bulan Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="c-laba">-</div>
                        <div class="small mt-1" id="t-laba"></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div>
                </div>
            </div></div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2"><div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pembelian Bulan Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="c-pembelian">-</div>
                        <div class="small mt-1" id="t-pembelian"></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-truck-loading fa-2x text-gray-300"></i></div>
                </div>
            </div></div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2"><div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Kas &amp; Bank</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="c-kas">-</div>
                        <div class="small mt-1 text-muted">Saldo akun kas/bank</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-wallet fa-2x text-gray-300"></i></div>
                </div>
            </div></div>
        </div>
    </div>

    <!-- Content Row: Kartu kedua -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2"><div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Piutang Berjalan</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800" id="c-piutang">-</div>
                <div class="small mt-1 text-muted">Belum dibayar pelanggan</div>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2"><div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Hutang Pemasok</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800" id="c-hutang">-</div>
                <div class="small mt-1 text-muted">Belum kita bayar</div>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2"><div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Nilai Persediaan</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800" id="c-persediaan">-</div>
                <div class="small mt-1 text-muted">Stok x harga pokok</div>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2"><div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Potensi Penjualan</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800" id="c-potensi">-</div>
                <div class="small mt-1 text-muted">Kebutuhan pelanggan belum closing</div>
            </div></div>
        </div>
    </div>

    <!-- Content Row: Chart tren + pipeline -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Penjualan vs Pembelian vs Laba Kotor dalam 12 Bulan Terakhir</h6></div>
                <div class="card-body"><div style="position:relative;height:320px"><canvas id="chTren"></canvas></div></div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Pipeline Pelanggan</h6></div>
                <div class="card-body"><div style="position:relative;height:320px"><canvas id="chPipeline"></canvas></div></div>
            </div>
        </div>
    </div>

    <!-- Content Row: Top produk + stok gudang -->
    <div class="row">
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">5 Produk Terlaris (12 Bulan, Omzet)</h6></div>
                <div class="card-body"><div style="position:relative;height:280px"><canvas id="chProduk"></canvas></div></div>
            </div>
        </div>
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Nilai Stok per Gudang</h6></div>
                <div class="card-body"><div style="position:relative;height:280px"><canvas id="chGudang"></canvas></div></div>
            </div>
        </div>
    </div>

    <!-- Content Row: Tabel -->
    <div class="row">
        <div class="col-xl-4 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Top Pelanggan dalam 12 Bulan Terakhir</h6></div>
                <div class="card-body"><table class="table table-sm mb-0"><tbody id="tb-pelanggan"></tbody></table></div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Top Pemasok dalam 12 Bulan Terakhir</h6></div>
                <div class="card-body"><table class="table table-sm mb-0"><tbody id="tb-pemasok"></tbody></table></div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger">Piutang Terbesar</h6></div>
                <div class="card-body"><table class="table table-sm mb-0"><tbody id="tb-piutang"></tbody></table></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Penjualan Terbaru</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Tanggal</th><th>Kode</th><th>Pelanggan</th><th class="text-right">Total</th></tr></thead>
                            <tbody id="tb-terbaru"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger">Peringatan: Stok Minus</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Gudang</th><th>Produk</th><th class="text-right">Qty</th></tr></thead>
                            <tbody id="tb-minus"></tbody>
                        </table>
                    </div>
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
    const rp  = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID', {maximumFractionDigits: 0});
    const num = n => Number(n || 0).toLocaleString('id-ID', {maximumFractionDigits: 2});
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const $   = id => document.getElementById(id);
    const warna = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'];

    function tumbuh(el, v, naikBagus) {
        if (v === null || v === undefined) { el.innerHTML = '<span class="text-muted">vs bulan lalu: -</span>'; return; }
        const naik = v >= 0;
        const bagus = naikBagus ? naik : !naik;
        el.innerHTML = '<span class="' + (bagus ? 'text-success' : 'text-danger') + '"><i class="fas fa-arrow-' +
            (naik ? 'up' : 'down') + '"></i> ' + Math.abs(v) + '%</span> <span class="text-muted">vs bulan lalu</span>';
    }

    function tabel(id, data, fn, kosong) {
        $(id).innerHTML = data.length ? data.map(fn).join('') :
            '<tr><td class="text-center text-muted py-3">' + kosong + '</td></tr>';
    }

    function render(d) {
        const k = d.kartu;
        $('c-penjualan').textContent  = rp(k.penjualan.nilai);
        $('c-laba').textContent       = rp(k.laba_kotor.nilai);
        $('c-pembelian').textContent  = rp(k.pembelian.nilai);
        $('c-kas').textContent        = rp(k.kas_bank);
        $('c-piutang').textContent    = rp(k.piutang);
        $('c-hutang').textContent     = rp(k.hutang);
        $('c-persediaan').textContent = rp(k.persediaan);
        $('c-potensi').textContent    = rp(k.potensi);

        tumbuh($('t-penjualan'), k.penjualan.tumbuh, true);
        tumbuh($('t-pembelian'), k.pembelian.tumbuh, false);
        $('t-laba').innerHTML = '<span class="text-muted">Margin ' + k.laba_kotor.margin + '% &middot; ' + k.transaksi + ' transaksi</span>';

        // Tren
        new Chart($('chTren'), {
            type: 'bar',
            data: {
                labels: d.tren.label,
                datasets: [
                    {label: 'Penjualan', data: d.tren.penjualan, backgroundColor: '#4e73df'},
                    {label: 'Pembelian', data: d.tren.pembelian, backgroundColor: '#36b9cc'},
                    {label: 'Laba Kotor', data: d.tren.laba, type: 'line', borderColor: '#1cc88a',
                     backgroundColor: '#1cc88a', tension: .3, fill: false}
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: {mode: 'index', intersect: false},
                scales: {y: {ticks: {callback: v => num(v / 1e6) + ' jt'}}},
                plugins: {tooltip: {callbacks: {label: c => c.dataset.label + ': ' + rp(c.parsed.y)}}}
            }
        });

        // Pipeline
        new Chart($('chPipeline'), {
            type: 'doughnut',
            data: {
                labels: d.pipeline.map(p => p.status),
                datasets: [{data: d.pipeline.map(p => p.jumlah), backgroundColor: warna}]
            },
            options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}
        });

        // Top produk
        new Chart($('chProduk'), {
            type: 'bar',
            data: {
                labels: d.top_produk.map(p => p.nama),
                datasets: [
                    {label: 'Omzet', data: d.top_produk.map(p => p.omzet), backgroundColor: '#4e73df'},
                    {label: 'Laba', data: d.top_produk.map(p => p.laba), backgroundColor: '#1cc88a'}
                ]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                scales: {x: {ticks: {callback: v => num(v / 1e6) + ' jt'}}},
                plugins: {tooltip: {callbacks: {label: c => c.dataset.label + ': ' + rp(c.parsed.x)}}}
            }
        });

        // Stok gudang
        new Chart($('chGudang'), {
            type: 'bar',
            data: {
                labels: d.stok_gudang.map(g => g.nama),
                datasets: [{label: 'Nilai Stok', data: d.stok_gudang.map(g => g.nilai), backgroundColor: '#f6c23e'}]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {y: {ticks: {callback: v => num(v / 1e6) + ' jt'}}},
                plugins: {legend: {display: false}, tooltip: {callbacks: {label: c => rp(c.parsed.y)}}}
            }
        });

        // Tabel
        tabel('tb-pelanggan', d.top_pelanggan, r =>
            '<tr><td>' + esc(r.nama) + '<div class="small text-muted">' + r.transaksi + ' transaksi</div></td><td class="text-right">' + rp(r.total) + '</td></tr>', 'Belum ada data');
        tabel('tb-pemasok', d.top_pemasok, r =>
            '<tr><td>' + esc(r.nama) + '<div class="small text-muted">' + r.transaksi + ' transaksi</div></td><td class="text-right">' + rp(r.total) + '</td></tr>', 'Belum ada data');
        tabel('tb-piutang', d.piutang_list, r =>
            '<tr><td>' + esc(r.nama) + '</td><td class="text-right text-danger">' + rp(r.sisa) + '</td></tr>', 'Tidak ada piutang');
        tabel('tb-terbaru', d.terbaru, r =>
            '<tr><td>' + esc(r.tanggal) + '</td><td>' + esc(r.kode) + '</td><td>' + esc(r.pelanggan) + '</td><td class="text-right">' + rp(r.grand_total) + '</td></tr>', 'Belum ada penjualan');
        tabel('tb-minus', d.stok_minus, r =>
            '<tr><td>' + esc(r.gudang) + '</td><td>' + esc(r.produk) + '</td><td class="text-right text-danger">' + num(r.qty) + ' ' + esc(r.satuan) + '</td></tr>', 'Tidak ada stok minus');
    }

    function muat() {
        fetch('api_dashboard.php', {credentials: 'same-origin'})
            .then(r => r.json().then(j => ({ok: r.ok, j})))
            .then(({ok, j}) => {
                if (!ok) throw new Error(j.detail || j.error || 'Gagal memuat');
                render(j);
            })
            .catch(e => {
                const el = $('dash-error');
                el.textContent = 'Dashboard gagal dimuat: ' + e.message;
                el.classList.remove('d-none');
            });
    }

    // Chart.js v4 dari CDN (dimuat dinamis supaya tidak bentrok dengan versi bawaan template)
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    s.onload = muat;
    s.onerror = () => { $('dash-error').textContent = 'Chart.js gagal dimuat (cek koneksi internet).'; $('dash-error').classList.remove('d-none'); };
    document.body.appendChild(s);
})();
</script>