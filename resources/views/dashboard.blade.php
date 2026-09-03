@extends('layouts.app')

@section('title', 'PayVerify — Platform Verifikasi Donasi')

@section('content')

{{-- ADMIN LOGIN PAGE --}}
<template x-if="currentPage === 'login'">
    <div class="flex items-center justify-center min-h-[75vh]" x-data="{ email: 'owner@test.com', password: 'password', error: false }">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <div class="w-10 h-10 rounded-lg bg-zinc-100 text-zinc-950 font-bold flex items-center justify-center text-xs tracking-wider mx-auto mb-3">PV</div>
                <h2 class="text-xl font-semibold text-zinc-100 tracking-tight">Portal Login Platform</h2>
                <p class="text-xs text-zinc-400 mt-1">Masuk sebagai Admin Verifikator atau Donatur / Customer</p>
            </div>
            
            <div class="panel p-6 space-y-4">
                <div class="flex gap-2 p-1 bg-zinc-900 rounded-lg border border-zinc-800 text-xs">
                    <button @click="email='owner@test.com'" class="flex-1 py-1.5 rounded font-medium transition-all" :class="email==='owner@test.com' ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-400 hover:text-zinc-200'">Admin Panel</button>
                    <button @click="email='donor@test.com'" class="flex-1 py-1.5 rounded font-medium transition-all" :class="email==='donor@test.com' ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-400 hover:text-zinc-200'">Donatur / User</button>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-zinc-400 uppercase tracking-wider mb-1.5">Email Pengguna</label>
                    <input type="email" x-model="email" class="input-clean" placeholder="email@test.com">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-zinc-400 uppercase tracking-wider mb-1.5">Password</label>
                    <input type="password" x-model="password" @keyup.enter="error = !(await login(email, password))" class="input-clean" placeholder="••••••••">
                </div>
                
                <p x-show="error" class="text-xs text-rose-400">Email atau password tidak sesuai.</p>

                <button @click="error = !(await login(email, password))" class="w-full btn-clean-primary py-2.5 mt-2" :disabled="loading">
                    <span x-show="!loading" x-text="email === 'donor@test.com' ? 'Masuk Dashboard Donatur' : 'Masuk ke Admin Panel'"></span>
                    <span x-show="loading">Memeriksa Kredensial...</span>
                </button>

                <div class="pt-3 border-t border-zinc-800 text-center">
                    <button @click="currentPage='customer_upload'" class="text-xs text-zinc-400 hover:text-zinc-200 underline">Buka Portal Pengiriman Struk &rarr;</button>
                </div>
            </div>
        </div>
    </div>
</template>

{{-- DASHBOARD ANALYTICS (ADMIN) --}}
<template x-if="currentPage === 'dashboard'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100 tracking-tight">Ringkasan Donasi & Verifikasi</h2>
                <p class="text-xs text-zinc-400">Statistik real-time dana donasi dan performa verifikasi AI</p>
            </div>
            <button @click="fetchStats()" class="btn-clean-secondary">Refresh Data</button>
        </div>

        {{-- Top Key Metrics Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="panel p-5">
                <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider">Total Donasi Terverifikasi</span>
                <p class="text-2xl font-bold text-emerald-400 mt-2 font-mono" x-text="formatCurrency(stats?.summary?.total_revenue)"></p>
                <p class="text-[11px] text-zinc-500 mt-1">Total dana sah terkumpul</p>
            </div>
            <div class="panel p-5">
                <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider">Total Transaksi</span>
                <p class="text-2xl font-bold text-zinc-100 mt-2 font-mono" x-text="stats?.summary?.total_transactions || 0"></p>
                <p class="text-[11px] text-zinc-500 mt-1">Semua kiriman donasi</p>
            </div>
            <div class="panel p-5">
                <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider">Menunggu Verifikasi</span>
                <p class="text-2xl font-bold text-amber-400 mt-2 font-mono" x-text="stats?.summary?.pending_verification || 0"></p>
                <p class="text-[11px] text-zinc-500 mt-1">Perlu tinjauan admin</p>
            </div>
            <div class="panel p-5">
                <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider">Tingkat Akurasi AI</span>
                <p class="text-2xl font-bold text-zinc-100 mt-2 font-mono"><span x-text="stats?.ai_performance?.accuracy_rate_percentage || 0"></span>%</p>
                <p class="text-[11px] text-zinc-500 mt-1">Ekstraksi OCR skor tinggi</p>
            </div>
        </div>

        {{-- Verification Status & Period Breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Status Breakdown --}}
            <div class="panel p-5">
                <h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-4">Rincian Verifikasi Admin</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">Disetujui (VALID)</span>
                        <span class="font-mono font-medium text-emerald-400" x-text="stats?.summary?.verified_payments || 0"></span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">Ditolak (INVALID)</span>
                        <span class="font-mono font-medium text-rose-400" x-text="stats?.summary?.rejected_payments || 0"></span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">Terdeteksi Risiko Tinggi</span>
                        <span class="font-mono font-medium text-amber-400" x-text="stats?.summary?.high_risk_payments || 0"></span>
                    </div>
                    <div class="pt-3 border-t border-zinc-800/80">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="text-zinc-400">Persentase Verifikasi Sukses</span>
                            <span class="font-mono font-semibold text-zinc-200" x-text="(stats?.summary?.verification_rate_percentage || 0) + '%'"></span>
                        </div>
                        <div class="h-1.5 bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full bg-zinc-100 rounded-full transition-all duration-500" :style="'width:' + (stats?.summary?.verification_rate_percentage || 0) + '%'"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Period Activity --}}
            <div class="panel p-5">
                <h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-4">Aktivitas Periode</h3>
                <div class="space-y-3 text-xs">
                    <div class="flex justify-between"><span class="text-zinc-400">Donasi Hari Ini</span><span class="font-mono text-zinc-200" x-text="stats?.period?.today_transactions || 0"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-400">Dana Terkumpul Hari Ini</span><span class="font-mono text-emerald-400" x-text="formatCurrency(stats?.period?.today_revenue)"></span></div>
                    <div class="pt-3 border-t border-zinc-800/80 flex justify-between"><span class="text-zinc-400">Donasi Bulan Ini</span><span class="font-mono text-zinc-200" x-text="stats?.period?.monthly_transactions || 0"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-400">Dana Terkumpul Bulan Ini</span><span class="font-mono text-emerald-400" x-text="formatCurrency(stats?.period?.monthly_revenue)"></span></div>
                </div>
            </div>

            {{-- AI Extraction Performance --}}
            <div class="panel p-5">
                <h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-4">Performa OCR AI</h3>
                <div class="space-y-3 text-xs">
                    <div class="flex justify-between"><span class="text-zinc-400">Total Struk Diisi AI</span><span class="font-mono text-zinc-200" x-text="stats?.ai_performance?.total_extractions || 0"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-400">Kepercayaan Tinggi (&ge; 85%)</span><span class="font-mono text-emerald-400" x-text="stats?.ai_performance?.high_confidence_extractions || 0"></span></div>
                    <div class="pt-3 border-t border-zinc-800/80 flex justify-between items-center">
                        <span class="text-zinc-400">Skor Akurasi</span>
                        <span class="badge-clean-emerald" x-text="(stats?.ai_performance?.accuracy_rate_percentage || 0) + '% Akurat'"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

{{-- PAYMENTS PAGE (ADMIN) --}}
<template x-if="currentPage === 'payments'">
    <div x-init="fetchPayments()" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100 tracking-tight">Data Transaksi Donasi</h2>
                <p class="text-xs text-zinc-400">Seluruh riwayat kiriman donasi dan bukti pembayaran</p>
            </div>
            <button @click="fetchPayments()" class="btn-clean-secondary">Refresh</button>
        </div>

        <div class="panel">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-zinc-900/80 text-zinc-400 uppercase tracking-wider font-semibold border-b border-zinc-800">
                        <tr>
                            <th class="px-5 py-3">Nomor Transaksi</th>
                            <th class="px-5 py-3">Campaign / Invoice</th>
                            <th class="px-5 py-3 text-right">Nominal Donasi</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-center">Aksi Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        <template x-for="p in payments" :key="p.id">
                            <tr class="hover:bg-zinc-800/30 transition-colors">
                                <td class="px-5 py-3.5 font-mono text-zinc-200" x-text="p.payment_number"></td>
                                <td class="px-5 py-3.5 text-zinc-400" x-text="p.invoice?.invoice_number || '—'"></td>
                                <td class="px-5 py-3.5 font-mono font-medium text-zinc-100 text-right" x-text="formatCurrency(p.expected_amount)"></td>
                                <td class="px-5 py-3.5 text-center"><span class="badge-clean-zinc" :class="statusBadge(p.status)" x-text="p.status"></span></td>
                                <td class="px-5 py-3.5 text-center">
                                    <button @click="currentPage='verification'; selectedPaymentId=p.id; fetchAnalysis(p.id)" class="text-xs text-zinc-300 hover:text-white underline cursor-pointer" x-show="p.status === 'WAITING_VERIFICATION'">Tinjau & Verifikasi</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div x-show="payments.length === 0" class="text-center py-10 text-xs text-zinc-500">Belum ada transaksi donasi</div>
            </div>
        </div>
    </div>
</template>

{{-- INVOICES PAGE (ADMIN) --}}
<template x-if="currentPage === 'invoices'">
    <div x-init="fetchInvoices()" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100 tracking-tight">Daftar Tagihan & Campaign</h2>
                <p class="text-xs text-zinc-400">Program donasi dan status pembayaran</p>
            </div>
            <button @click="fetchInvoices()" class="btn-clean-secondary">Refresh</button>
        </div>

        <div class="panel">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-zinc-900/80 text-zinc-400 uppercase tracking-wider font-semibold border-b border-zinc-800">
                        <tr>
                            <th class="px-5 py-3">Nomor Invoice</th>
                            <th class="px-5 py-3">Nama Donatur</th>
                            <th class="px-5 py-3 text-right">Target / Nominal</th>
                            <th class="px-5 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        <template x-for="inv in invoices" :key="inv.id">
                            <tr class="hover:bg-zinc-800/30 transition-colors">
                                <td class="px-5 py-3.5 font-mono text-zinc-200" x-text="inv.invoice_number"></td>
                                <td class="px-5 py-3.5 text-zinc-300" x-text="inv.customer_name"></td>
                                <td class="px-5 py-3.5 font-mono font-medium text-zinc-100 text-right" x-text="formatCurrency(inv.amount)"></td>
                                <td class="px-5 py-3.5 text-center"><span class="badge-clean-zinc" :class="statusBadge(inv.status)" x-text="inv.status"></span></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div x-show="invoices.length === 0" class="text-center py-10 text-xs text-zinc-500">Belum ada invoice / campaign</div>
            </div>
        </div>
    </div>
</template>

{{-- DONOR DASHBOARD (KHUSUS DONATUR / CUSTOMER LOGGED IN) --}}
<template x-if="currentPage === 'donor_dashboard'">
    <div x-init="fetchDonorData()" x-data="{ selectedReceipt: null }" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100 tracking-tight">Dashboard Donatur Saya</h2>
                <p class="text-xs text-zinc-400">Pantau riwayat donasi Anda dan lacak status verifikasi pembayaran secara real-time</p>
            </div>
            <button @click="fetchDonorData()" class="btn-clean-secondary">Refresh Data</button>
        </div>

        {{-- Impact Metrics Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="panel p-5">
                <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider">Total Kontribusi Donasi</span>
                <p class="text-2xl font-bold text-emerald-400 mt-2 font-mono" x-text="formatCurrency(donorStats?.total_contributed)"></p>
                <p class="text-[11px] text-zinc-500 mt-1">Total dana terverifikasi lunas</p>
            </div>
            <div class="panel p-5">
                <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider">Donasi Terverifikasi</span>
                <p class="text-2xl font-bold text-zinc-100 mt-2 font-mono" x-text="donorStats?.total_verified_donations || 0"></p>
                <p class="text-[11px] text-zinc-500 mt-1">Transaksi donasi sukses</p>
            </div>
            <div class="panel p-5">
                <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider">Menunggu Process AI / Admin</span>
                <p class="text-2xl font-bold text-amber-400 mt-2 font-mono" x-text="donorStats?.total_pending_donations || 0"></p>
                <p class="text-[11px] text-zinc-500 mt-1">Dalam antrean verifikasi</p>
            </div>
        </div>

        {{-- Live Status Tracker & Donations List --}}
        <div class="panel">
            <div class="panel-header">
                <h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider">Riwayat & Status Verifikasi Donasi Saya</h3>
                <button @click="currentPage='customer_upload'" class="btn-clean-primary text-xs">+ Kirim Struk Baru</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-zinc-900/80 text-zinc-400 uppercase tracking-wider font-semibold border-b border-zinc-800">
                        <tr>
                            <th class="px-5 py-3">No. Transaksi</th>
                            <th class="px-5 py-3">Program / Campaign</th>
                            <th class="px-5 py-3 text-right">Nominal Donasi</th>
                            <th class="px-5 py-3 text-center">Status Verifikasi Real-time</th>
                            <th class="px-5 py-3 text-center">Tindakan / Kwitansi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        <template x-for="d in donorDonations" :key="d.id">
                            <tr class="hover:bg-zinc-800/30 transition-colors">
                                <td class="px-5 py-3.5 font-mono text-zinc-200" x-text="d.payment_number"></td>
                                <td class="px-5 py-3.5 text-zinc-300" x-text="d.campaign_name"></td>
                                <td class="px-5 py-3.5 font-mono font-medium text-zinc-100 text-right" x-text="formatCurrency(d.expected_amount)"></td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="badge-clean-zinc" :class="statusBadge(d.status)" x-text="d.status === 'VERIFIED' ? '🟢 Terverifikasi Lunas' : (d.status === 'WAITING_VERIFICATION' ? '🔵 Menunggu Admin' : (d.status === 'REJECTED' ? '🔴 Ditolak' : '🟡 Diproses AI'))"></span>
                                </td>
                                <td class="px-5 py-3.5 text-center space-x-2">
                                    <template x-if="d.status === 'VERIFIED' || d.status === 'PAID'">
                                        <button @click="selectedReceipt = d" class="btn-clean-secondary text-[11px] py-1 px-2.5">Kwitansi Digital 📄</button>
                                    </template>
                                    <template x-if="d.status === 'WAITING_PAYMENT' || d.status === 'REJECTED'">
                                        <button @click="currentPage='customer_upload'; searchNum=d.payment_number;" class="btn-clean-primary text-[11px] py-1 px-2.5">Unggah Struk 📤</button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div x-show="donorDonations.length === 0" class="text-center py-10 text-xs text-zinc-500">Belum ada riwayat donasi</div>
            </div>
        </div>

        {{-- Kwitansi Digital Modal --}}
        <div x-show="selectedReceipt" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="bg-zinc-900 border border-zinc-700 rounded-xl max-w-md w-full p-6 space-y-4 text-zinc-100 relative shadow-2xl">
                <button @click="selectedReceipt = null" class="absolute top-4 right-4 text-zinc-400 hover:text-white text-sm">&times;</button>
                <div class="text-center border-b border-zinc-800 pb-4">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/20 text-emerald-400 font-bold flex items-center justify-center text-sm mx-auto mb-2">PV</div>
                    <h3 class="text-base font-bold tracking-tight text-white">KWITANSI DONASI RESMI</h3>
                    <p class="text-[11px] text-zinc-400">Yayasan Donasi Peduli — Terverifikasi Sistem PayVerify AI</p>
                </div>
                <div class="space-y-3 text-xs">
                    <div class="flex justify-between"><span class="text-zinc-400">Nomor Transaksi:</span><span class="font-mono text-zinc-200 font-semibold" x-text="selectedReceipt?.payment_number"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-400">Program Donasi:</span><span class="text-zinc-200" x-text="selectedReceipt?.campaign_name"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-400">Nama Donatur:</span><span class="text-zinc-200" x-text="user?.name || 'Donatur'"></span></div>
                    <div class="flex justify-between border-t border-b border-zinc-800 py-2 my-2"><span class="text-zinc-400 font-semibold">Nominal Terverifikasi:</span><span class="font-mono text-emerald-400 font-bold text-base" x-text="formatCurrency(selectedReceipt?.expected_amount)"></span></div>
                    <div class="flex justify-between"><span class="text-zinc-400">Status Pembayaran:</span><span class="badge-clean-emerald">TERVERIFIKASI LUNAS ✓</span></div>
                </div>
                <div class="pt-4 border-t border-zinc-800 flex gap-2">
                    <button onclick="window.print()" class="w-full btn-clean-primary py-2 text-xs">Cetak Kwitansi (PDF)</button>
                    <button @click="selectedReceipt = null" class="w-full btn-clean-secondary py-2 text-xs">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</template>

{{-- CUSTOMER / DONOR PAYMENT PROOF UPLOAD PORTAL (KHUSUS PENGGUNA/DONATUR - NOMINAL BEBAS) --}}
<template x-if="currentPage === 'customer_upload'">
    <div x-data="{ searchNum: 'PAY-DONASI-000003', pubPayment: null, uploadFile: null, uploadMsg: '', uploadError: '', aiResult: null, uploading: false }"
         x-init="fetchPublicPayment(searchNum).then(d => pubPayment = d?.payment)"
         class="max-w-2xl mx-auto space-y-6">
        
        <div>
            <h2 class="text-lg font-semibold text-zinc-100 tracking-tight">Portal Upload Bukti Donasi (Nominal Bebas)</h2>
            <p class="text-xs text-zinc-400">Unggah foto/screenshot struk bukti transfer donasi Anda. AI OCR akan otomatis mendeteksi nominal angka yang tertera pada struk.</p>
        </div>

        {{-- Public Payment Details & Upload Box --}}
        <template x-if="pubPayment">
            <div class="space-y-6">
                <div class="panel p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[11px] text-zinc-500 uppercase tracking-wider">Program / Metode Pembayaran</span>
                            <p class="text-base font-semibold text-zinc-100">Donasi Umum via QRIS / Transfer Bank</p>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] text-zinc-500 uppercase tracking-wider block mb-1">Status Verifikasi</span>
                            <span class="badge-clean-zinc" :class="statusBadge(pubPayment.status)" x-text="pubPayment.status === 'VERIFIED' ? '🟢 Terverifikasi' : '🟡 Menunggu Verifikasi'"></span>
                        </div>
                    </div>

                    <template x-if="pubPayment.qr_code_url">
                        <div class="text-center pt-3 border-t border-zinc-800/80">
                            <p class="text-xs text-zinc-400 mb-3">Scan kode QRIS di bawah ini dengan nominal transfer bebas pilihan Anda:</p>
                            <img :src="pubPayment.qr_code_url" alt="Kode QRIS Donasi" class="w-44 h-44 mx-auto rounded-lg bg-white p-2 border border-zinc-700">
                        </div>
                    </template>
                </div>

                {{-- Upload Proof Form & Instant AI Analysis Feedback --}}
                <div class="panel p-6">
                    <h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">Unggah Foto / Screenshot Struk Pembayaran</h3>
                    <p class="text-xs text-zinc-400 mb-4">Pilih foto/screenshot resi transfer donasi Anda (JPG, PNG, WEBP max 5MB). AI OCR akan membaca nominal angka pada struk secara otomatis.</p>

                    {{-- AI Valid Extraction Result Card --}}
                    <div x-show="aiResult && aiResult.is_valid" class="mb-5 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 space-y-2">
                        <div class="flex items-center gap-2 text-emerald-400 font-semibold text-xs">
                            <span class="text-base">✓</span>
                            <span>AI OCR Berhasil Membaca Struk (Nominal Donasi Terdeteksi)</span>
                        </div>
                        <div class="text-xs text-zinc-300 space-y-1.5 pl-6">
                            <p>Nominal Donasi Terdeteksi AI: <strong class="font-mono text-emerald-400 text-sm" x-text="formatCurrency(aiResult?.extracted_amount)"></strong></p>
                            <p>Bank / Provider: <span class="text-zinc-200" x-text="aiResult?.extracted_provider || 'Terdeteksi'"></span> | Tanggal: <span class="font-mono text-zinc-200" x-text="aiResult?.extracted_date || '—'"></span></p>
                            <div class="p-2.5 rounded bg-emerald-950/40 border border-emerald-900/50 text-[11px] font-mono text-emerald-300">
                                Berhasil: Data donasi sebesar <span x-text="formatCurrency(aiResult?.extracted_amount)"></span> telah otomatis dicatat dan dikirim ke antrean verifikasi admin.
                            </div>
                        </div>
                    </div>

                    {{-- AI Invalid / Blur Extraction Warning Banner --}}
                    <div x-show="aiResult && !aiResult.is_valid" class="mb-5 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 space-y-2">
                        <div class="flex items-center gap-2 text-rose-400 font-bold text-xs">
                            <span class="text-base">❌</span>
                            <span>FOTO STRUK TIDAK VALID / TIDAK TERBACA</span>
                        </div>
                        <div class="text-xs text-rose-300 space-y-1.5 pl-6">
                            <p x-text="aiResult?.error_message || 'AI tidak dapat menemukan angka nominal transfer pada foto yang diunggah.'"></p>
                            <div class="p-2.5 rounded bg-rose-950/40 border border-rose-900/50 text-[11px] font-mono text-rose-200">
                                Peringatan: Gambar tidak mengandung informasi nominal transfer yang jelas. Silakan pastikan foto terang, tidak terpotong, dan unggah ulang struk yang sah.
                            </div>
                        </div>
                    </div>

                    <div x-show="uploadError" class="mb-4 p-3 rounded bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs" x-text="uploadError"></div>

                    <div class="space-y-4">
                        <input type="file" @change="uploadFile = $event.target.files[0]; aiResult = null;" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-zinc-800 file:text-zinc-200 hover:file:bg-zinc-700 cursor-pointer">
                        
                        <button @click="
                            if(!uploadFile) { uploadError='Silakan pilih file foto/screenshot struk terlebih dahulu.'; return; }
                            uploading=true; uploadMsg=''; uploadError=''; aiResult=null;
                            const formData = new FormData();
                            formData.append('proof_image', uploadFile);
                            fetch('/api/public/payments/' + pubPayment.payment_number + '/proof', { method: 'POST', body: formData })
                            .then(r => r.json())
                            .then(d => {
                                uploading=false;
                                if(d?.ai_analysis) {
                                    aiResult = d.ai_analysis;
                                    fetchPublicPayment(pubPayment.payment_number).then(p => pubPayment = p?.payment);
                                } else {
                                    uploadError = d?.message || 'Gagal mengunggah foto bukti bayar.';
                                }
                            });
                        " class="w-full btn-clean-primary py-2.5" :disabled="uploading">
                            <span x-show="!uploading">Kirim Struk & Deteksi Nominal AI</span>
                            <span x-show="uploading">Mengunggah & Membaca Nominal Struk dengan AI...</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

{{-- HUMAN VERIFICATION SCREEN (KHUSUS ADMIN/STAFF) --}}
<template x-if="currentPage === 'verification'">
    <div x-data="{ analysis: null, verifyLoading: false, decision: '', reason: '', notes: '', verifySuccess: false, verifyError: '' }"
         x-init="fetchPayments(); if(selectedPaymentId) { fetchAnalysis(selectedPaymentId).then(d => analysis = d) }"
         class="space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100 tracking-tight">Verifikasi Struk AI (Admin Panel)</h2>
                <p class="text-xs text-zinc-400">Tinjau foto resi yang diunggah pengguna dan temuan AI sebelum menyetujui</p>
            </div>
            <button x-show="analysis" @click="analysis=null; selectedPaymentId=null; fetchPayments()" class="btn-clean-secondary">Kembali ke Antrean</button>
        </div>

        {{-- Pending List (if no payment selected) --}}
        <div x-show="!analysis && !selectedPaymentId" class="panel">
            <div class="panel-header">
                <h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider">Antrean Verifikasi Struk Donasi</h3>
                <button @click="fetchPayments()" class="btn-clean-secondary text-[11px] py-1 px-2.5">Refresh Antrean</button>
            </div>
            <div class="divide-y divide-zinc-800/60 text-xs">
                <template x-for="p in payments.filter(p => ['WAITING_VERIFICATION', 'PROOF_UPLOADED', 'AI_PROCESSING', 'AI_PROCESSING_FAILED'].includes(p.status))" :key="p.id">
                    <div class="px-5 py-3.5 flex items-center justify-between hover:bg-zinc-800/30 transition-colors">
                        <div>
                            <span class="font-mono text-zinc-200 font-medium" x-text="p.payment_number"></span>
                            <span class="text-zinc-500 ml-2" x-text="'(' + formatCurrency(p.expected_amount) + ')'"></span>
                            <span class="badge-clean-zinc ml-2" :class="statusBadge(p.status)" x-text="p.status"></span>
                        </div>
                        <button @click="selectedPaymentId=p.id; api('GET', '/payments/' + p.id + '/analysis').then(d => analysis = d)" class="btn-clean-primary">Tinjau Foto Struk & AI</button>
                    </div>
                </template>
            </div>
            <div x-show="payments.filter(p => ['WAITING_VERIFICATION', 'PROOF_UPLOADED', 'AI_PROCESSING', 'AI_PROCESSING_FAILED'].includes(p.status)).length === 0" class="text-center py-10 text-xs text-zinc-500">
                Semua tugas verifikasi donasi telah selesai.
            </div>
        </div>

        {{-- Detailed Verification Side-by-Side --}}
        <div x-show="analysis" class="space-y-6">
            {{-- Proof Image Preview & Data Comparison --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Uploaded Screenshot Preview --}}
                <div class="panel">
                    <div class="panel-header"><h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider">Foto Struk dari Donatur</h3></div>
                    <div class="p-4 flex items-center justify-center bg-zinc-950 min-h-[220px]">
                        <img :src="'/api/payments/' + selectedPaymentId + '/proof'" alt="Foto Bukti Transfer" class="max-h-64 rounded border border-zinc-800 object-contain" onError="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div class="text-xs text-zinc-500 text-center py-8" style="display:none;">
                            Preview foto struk tidak ditemukan
                        </div>
                    </div>
                </div>

                {{-- Expected Invoice --}}
                <div class="panel">
                    <div class="panel-header"><h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider">1. Data Tagihan / Invoice</h3></div>
                    <div class="p-5 space-y-3 text-xs">
                        <div class="flex justify-between border-b border-zinc-800/60 pb-2"><span class="text-zinc-400">Nomor Transaksi</span><span class="font-mono text-zinc-200" x-text="analysis?.payment?.payment_number"></span></div>
                        <div class="flex justify-between border-b border-zinc-800/60 pb-2"><span class="text-zinc-400">Nomor Invoice</span><span class="font-mono text-zinc-200" x-text="analysis?.payment?.invoice?.invoice_number || '—'"></span></div>
                        <div class="flex justify-between border-b border-zinc-800/60 pb-2"><span class="text-zinc-400">Target Nominal</span><span class="font-mono font-bold text-zinc-100 text-sm" x-text="formatCurrency(analysis?.expected?.amount)"></span></div>
                        <div class="flex justify-between"><span class="text-zinc-400">Status Saat Ini</span><span class="badge-clean-zinc" :class="statusBadge(analysis?.payment?.status)" x-text="analysis?.payment?.status"></span></div>
                    </div>
                </div>

                {{-- AI Extraction --}}
                <div class="panel">
                    <div class="panel-header"><h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider">2. Temuan Pembacaan AI</h3></div>
                    <div class="p-5 space-y-3 text-xs">
                        <template x-if="analysis?.extraction">
                            <div class="space-y-3">
                                <div class="flex justify-between border-b border-zinc-800/60 pb-2"><span class="text-zinc-400">Nominal Terdeteksi</span><span class="font-mono font-bold text-sm" :class="analysis?.validation?.is_amount_matched ? 'text-emerald-400' : 'text-rose-400'" x-text="formatCurrency(analysis?.extraction?.extracted_amount)"></span></div>
                                <div class="flex justify-between border-b border-zinc-800/60 pb-2"><span class="text-zinc-400">Tanggal Struk</span><span class="font-mono text-zinc-200" x-text="analysis?.extraction?.extracted_date || '—'"></span></div>
                                <div class="flex justify-between border-b border-zinc-800/60 pb-2"><span class="text-zinc-400">Bank / Provider</span><span class="text-zinc-200" x-text="analysis?.extraction?.extracted_provider || '—'"></span></div>
                                <div class="flex justify-between border-b border-zinc-800/60 pb-2"><span class="text-zinc-400">Nomor Referensi</span><span class="font-mono text-zinc-200" x-text="analysis?.extraction?.extracted_ref_number || '—'"></span></div>
                                <div class="flex justify-between items-center"><span class="text-zinc-400">Skor Kepercayaan AI</span><span class="font-mono font-semibold text-zinc-200" x-text="Math.round((analysis?.extraction?.confidence_score || 0) * 100) + '%' "></span></div>
                            </div>
                        </template>
                        <template x-if="!analysis?.extraction"><p class="text-zinc-500">Belum ada data ekstraksi AI</p></template>
                    </div>
                </div>
            </div>

            {{-- Validation & Risk --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="panel p-5">
                    <h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-4">Pemeriksaan Sistem</h3>
                    <template x-if="analysis?.validation">
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between items-center p-2 rounded bg-zinc-900 border border-zinc-800"><span class="text-zinc-400">Kesesuaian Nominal</span><span class="font-mono" :class="analysis.validation.is_amount_matched ? 'text-emerald-400' : 'text-rose-400'" x-text="analysis.validation.is_amount_matched ? 'COCOK' : 'BEDA'"></span></div>
                            <div class="flex justify-between items-center p-2 rounded bg-zinc-900 border border-zinc-800"><span class="text-zinc-400">Kesesuaian Mata Uang</span><span class="font-mono text-emerald-400" x-text="analysis.validation.is_currency_matched ? 'COCOK' : 'BEDA'"></span></div>
                            <div class="flex justify-between items-center p-2 rounded bg-zinc-900 border border-zinc-800"><span class="text-zinc-400">Rentang Tanggal</span><span class="font-mono text-emerald-400" x-text="analysis.validation.is_date_valid ? 'VALID' : 'INVALID'"></span></div>
                        </div>
                    </template>
                </div>
                <div class="panel p-5">
                    <h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-4">Evaluasi Indikator Risiko</h3>
                    <template x-if="analysis?.risk">
                        <div>
                            <div class="flex items-center justify-between mb-3"><span class="text-xs text-zinc-400">Tingkat Risiko</span><span class="badge-clean-zinc" :class="analysis.risk.risk_level === 'LOW' ? 'badge-clean-emerald' : 'badge-clean-rose'" x-text="analysis.risk.risk_level"></span></div>
                            <div class="space-y-2">
                                <template x-for="factor in (analysis.risk.risk_factors || [])" :key="factor.indicator">
                                    <div class="p-2.5 rounded bg-zinc-900 border border-zinc-800 text-xs text-zinc-300" x-text="factor.message"></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Final Human Decision Panel --}}
            <div class="panel p-6" x-show="analysis?.payment?.status === 'WAITING_VERIFICATION'">
                <h3 class="text-sm font-semibold text-zinc-100 mb-1">Keputusan Akhir Manusia (Human-in-the-Loop)</h3>
                <p class="text-xs text-zinc-400 mb-5">AI telah menyajikan analisis di atas. Keputusan sah/tidaknya donasi sepenuhnya ada di tangan Anda sebagai admin.</p>

                <div x-show="verifySuccess" class="mb-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs">
                    Keputusan verifikasi berhasil disimpan! Status donasi telah diperbarui.
                </div>
                <div x-show="verifyError" class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs" x-text="verifyError"></div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-400 uppercase tracking-wider mb-1.5">Catatan Verifikasi (Opsional)</label>
                        <textarea x-model="notes" rows="2" class="input-clean resize-none" placeholder="Tambahkan catatan verifikasi..."></textarea>
                    </div>
                    <div x-show="decision === 'INVALID'">
                        <label class="block text-[11px] font-medium text-rose-400 uppercase tracking-wider mb-1.5">Alasan Penolakan (Wajib jika INVALID)</label>
                        <textarea x-model="reason" rows="2" class="input-clean resize-none border-rose-500/30" placeholder="Jelaskan alasan penolakan..."></textarea>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button @click="decision='VALID'; verifyLoading=true; api('POST', '/payments/' + selectedPaymentId + '/verify', { decision: 'VALID', verification_notes: notes }).then(d => { verifyLoading=false; if(d?.decision) { verifySuccess=true; fetchStats(); } else { verifyError = d?.message || 'Error'; } })" class="flex-1 btn-clean-success py-2.5 text-xs font-semibold" :disabled="verifyLoading">
                            Setujui (VALID)
                        </button>
                        <button @click="if(decision !== 'INVALID') { decision='INVALID'; return; } if(!reason) { verifyError='Alasan penolakan wajib diisi.'; return; } verifyLoading=true; api('POST', '/payments/' + selectedPaymentId + '/verify', { decision: 'INVALID', rejection_reason: reason, verification_notes: notes }).then(d => { verifyLoading=false; if(d?.decision) { verifySuccess=true; fetchStats(); } else { verifyError = d?.message || 'Error'; } })" class="flex-1 btn-clean-danger py-2.5 text-xs font-semibold" :disabled="verifyLoading">
                            Tolak (INVALID)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

{{-- SUBSCRIPTION PAGE (ADMIN) --}}
<template x-if="currentPage === 'subscription'">
    <div x-init="fetchSubscription()" x-data="{ upgrading: false }" class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-zinc-100 tracking-tight">Langganan & Kuota Verifikasi</h2>
            <p class="text-xs text-zinc-400">Pengelolaan paket langganan dan batas verifikasi bulanan</p>
        </div>

        <div class="panel p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider">Paket Aktif</span>
                    <h3 class="text-xl font-bold text-zinc-100 font-mono mt-0.5" x-text="subscription?.subscription?.plan_name || 'FREE'"></h3>
                </div>
                <span class="badge-clean-zinc" :class="subscription?.usage?.is_limit_reached ? 'badge-clean-rose' : 'badge-clean-emerald'" x-text="subscription?.usage?.is_limit_reached ? 'Kuota Habis' : 'Aktif'"></span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs mt-4 pt-4 border-t border-zinc-800/80">
                <div>
                    <span class="text-zinc-500">Penggunaan Bulanan</span>
                    <p class="text-base font-bold text-zinc-100 font-mono mt-1"><span x-text="subscription?.usage?.current || 0"></span> / <span x-text="subscription?.usage?.limit || 0"></span></p>
                </div>
                <div>
                    <span class="text-zinc-500">Persentase Penggunaan</span>
                    <p class="text-base font-bold text-zinc-100 font-mono mt-1"><span x-text="(subscription?.usage?.percentage || 0) + '%'"></span></p>
                </div>
            </div>
        </div>

        <h3 class="text-sm font-semibold text-zinc-200">Paket Yang Tersedia</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <template x-for="plan in [{name:'FREE',limit:50,price:'Gratis'},{name:'STARTER',limit:500,price:'Rp 99.000'},{name:'BUSINESS',limit:'2.000',price:'Rp 299.000'},{name:'PRO',limit:'10.000',price:'Rp 799.000'}]" :key="plan.name">
                <div class="panel p-5 flex flex-col justify-between" :class="subscription?.subscription?.plan_name === plan.name ? 'border-zinc-500' : ''">
                    <div>
                        <h4 class="text-xs font-semibold text-zinc-400 uppercase tracking-wider" x-text="plan.name"></h4>
                        <p class="text-lg font-bold text-zinc-100 font-mono mt-1" x-text="plan.price"></p>
                        <p class="text-xs text-zinc-400 mt-2"><span class="font-mono text-zinc-200" x-text="plan.limit"></span> verifikasi / bln</p>
                    </div>
                    <div class="mt-5">
                        <button x-show="subscription?.subscription?.plan_name !== plan.name"
                            @click="upgrading=true; api('POST', '/subscription/upgrade', {plan_name: plan.name}).then(d => { upgrading=false; fetchSubscription(); })"
                            class="w-full btn-clean-secondary text-xs" :disabled="upgrading">
                            Upgrade Paket
                        </button>
                        <span x-show="subscription?.subscription?.plan_name === plan.name" class="block text-center text-xs text-zinc-400 font-mono font-medium py-1.5">Paket Aktif</span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

@endsection

@section('scripts')
<script>
    const origApp = app;
    app = function() {
        const base = origApp();
        base.selectedPaymentId = null;
        base.fetchAnalysis = async function(id) {
            const data = await base.api('GET', '/payments/' + id + '/analysis');
            return data;
        };
        base.fetchPendingPayments = async function() {
            await base.fetchPayments();
        };
        base.fetchPublicPayment = async function(num) {
            const res = await fetch('/api/public/payments/' + num);
            return res.json();
        };
        return base;
    };
</script>
@endsection
