<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PayVerify — AI Payment & Donation Verification Platform">
    <title>@yield('title', 'PayVerify — Payment & Donation Verification')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen font-sans antialiased" x-data="app()" x-init="init()">

    {{-- Minimalist Sidebar --}}
    <aside class="fixed top-0 left-0 h-full w-60 bg-zinc-900/90 backdrop-blur-md border-r border-zinc-800/80 z-40 flex flex-col transition-transform duration-200"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        
        {{-- Brand --}}
        <div class="px-5 py-5 border-b border-zinc-800/80 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-md bg-zinc-100 text-zinc-950 font-bold flex items-center justify-center text-xs tracking-wider">PV</div>
                <div>
                    <h1 class="text-sm font-semibold text-zinc-100 tracking-tight">PayVerify</h1>
                    <p class="text-[10px] text-zinc-500 font-mono">Verifikasi Donasi AI</p>
                </div>
            </div>
        </div>

        {{-- Nav Links --}}
        <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
            {{-- Admin Menu (Shown when logged in as Owner or Staff) --}}
            <template x-if="token && user?.role !== 'customer'">
                <div>
                    <div class="px-3 pb-2 text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Admin Panel</div>
                    
                    <a href="#" @click.prevent="currentPage='dashboard'" class="nav-item" :class="currentPage==='dashboard' ? 'nav-item-active' : 'nav-item-idle'">
                        <span>Dashboard Analytics</span>
                    </a>
                    <a href="#" @click.prevent="currentPage='payments'" class="nav-item" :class="currentPage==='payments' ? 'nav-item-active' : 'nav-item-idle'">
                        <span>Data Transaksi</span>
                    </a>
                    <a href="#" @click.prevent="currentPage='invoices'" class="nav-item" :class="currentPage==='invoices' ? 'nav-item-active' : 'nav-item-idle'">
                        <span>Invoices & Campaign</span>
                    </a>
                    <a href="#" @click.prevent="currentPage='verification'; fetchPayments()" class="nav-item" :class="currentPage==='verification' ? 'nav-item-active' : 'nav-item-idle'">
                        <div class="flex items-center justify-between w-full">
                            <span>Verifikasi Struk AI</span>
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20" x-show="stats?.summary?.pending_verification > 0" x-text="stats?.summary?.pending_verification"></span>
                        </div>
                    </a>

                    <div class="pt-5 px-3 pb-2 text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Pengaturan SaaS</div>
                    
                    <a href="#" @click.prevent="currentPage='subscription'" class="nav-item" :class="currentPage==='subscription' ? 'nav-item-active' : 'nav-item-idle'">
                        <span>Langganan & Kuota</span>
                    </a>
                </div>
            </template>

            {{-- Customer / Donor Menu --}}
            <div class="pt-2">
                <div class="px-3 pb-2 text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Portal Donatur</div>
                <template x-if="token && user?.role === 'customer'">
                    <a href="#" @click.prevent="currentPage='donor_dashboard'; fetchDonorData()" class="nav-item" :class="currentPage==='donor_dashboard' ? 'nav-item-active' : 'nav-item-idle'">
                        <span>Dashboard Donasi Saya</span>
                    </a>
                </template>
                <a href="#" @click.prevent="currentPage='customer_upload'" class="nav-item" :class="currentPage==='customer_upload' ? 'nav-item-active' : 'nav-item-idle'">
                    <span>Kirim Bukti Pembayaran</span>
                </a>
            </div>

            {{-- Login Menu when logged out --}}
            <template x-if="!token">
                <div class="pt-4 border-t border-zinc-800/80 mt-4">
                    <a href="#" @click.prevent="currentPage='login'" class="nav-item" :class="currentPage==='login' ? 'nav-item-active' : 'nav-item-idle'">
                        <span>🔑 Login Admin Panel</span>
                    </a>
                </div>
            </template>
        </nav>

        {{-- User Profile Footer --}}
        <div class="px-4 py-3 border-t border-zinc-800/80 bg-zinc-900/50">
            <div class="flex items-center justify-between">
                <div class="min-w-0 pr-2">
                    <p class="text-xs font-medium text-zinc-200 truncate" x-text="user?.name || (token ? 'User' : 'Belum Login')"></p>
                    <p class="text-[10px] text-zinc-500 truncate" x-text="user?.email || (token ? '' : 'Klik Login Admin')"></p>
                </div>
                <button x-show="token" @click="logout()" class="text-xs text-zinc-400 hover:text-zinc-200 transition-colors p-1" title="Logout">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </div>
        </div>
    </aside>

    {{-- Main Area --}}
    <main class="lg:ml-60 min-h-screen">
        {{-- Topbar --}}
        <header class="h-14 border-b border-zinc-800/80 px-6 flex items-center justify-between bg-zinc-950/60 backdrop-blur-sm sticky top-0 z-30">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-zinc-400 hover:text-zinc-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="flex items-center gap-2 text-xs text-zinc-400">
                    <span class="text-zinc-500">PayVerify</span>
                    <span>/</span>
                    <span class="capitalize text-zinc-200 font-medium" x-text="currentPage"></span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button x-show="!token && currentPage !== 'login'" @click="currentPage = 'login'" class="btn-clean-primary text-[11px] py-1 px-3">Login Admin</button>
                <button x-show="!token && currentPage === 'login'" @click="currentPage = 'customer_upload'" class="btn-clean-secondary text-[11px] py-1 px-3">Portal Donatur</button>
                
                <button x-show="token && user?.role !== 'customer'" @click="currentPage = 'customer_upload'" class="btn-clean-secondary text-[11px] py-1 px-2.5">Portal Donatur</button>
                <button x-show="token && currentPage === 'customer_upload' && user?.role !== 'customer'" @click="currentPage = 'dashboard'" class="btn-clean-primary text-[11px] py-1 px-2.5">Admin Panel</button>
                <span class="text-xs text-zinc-400 bg-zinc-900 border border-zinc-800 px-2.5 py-1 rounded-md font-mono" x-show="user" x-text="user?.role || 'user'"></span>
            </div>
        </header>

        <div class="p-6 lg:p-8 max-w-7xl mx-auto">
            @yield('content')
        </div>
    </main>

    {{-- Overlay for mobile sidebar --}}
    <div x-show="sidebarOpen" @click="sidebarOpen=false" class="fixed inset-0 bg-black/70 z-30 lg:hidden" x-transition.opacity></div>

    <style>
        .nav-item {
            @apply flex items-center px-3 py-2 rounded-lg text-xs font-medium transition-all duration-150 cursor-pointer;
        }
        .nav-item-active {
            @apply bg-zinc-800 text-zinc-100 font-semibold;
        }
        .nav-item-idle {
            @apply text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/50;
        }
        .panel {
            @apply bg-zinc-900/60 border border-zinc-800/80 rounded-xl overflow-hidden;
        }
        .panel-header {
            @apply px-5 py-3.5 border-b border-zinc-800/80 flex items-center justify-between bg-zinc-900/30;
        }
        .input-clean {
            @apply bg-zinc-900 border border-zinc-800 rounded-lg px-3.5 py-2 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-zinc-500 transition-colors w-full;
        }
        .btn-clean-primary {
            @apply bg-zinc-100 hover:bg-white text-zinc-950 px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-150 cursor-pointer disabled:opacity-50;
        }
        .btn-clean-secondary {
            @apply bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 px-4 py-2 rounded-lg text-xs font-medium transition-all duration-150 cursor-pointer;
        }
        .btn-clean-success {
            @apply bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-xs font-medium transition-all duration-150 cursor-pointer;
        }
        .btn-clean-danger {
            @apply bg-rose-600 hover:bg-rose-500 text-white px-4 py-2 rounded-lg text-xs font-medium transition-all duration-150 cursor-pointer;
        }
        .badge-clean-emerald { @apply bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[11px] font-mono px-2 py-0.5 rounded; }
        .badge-clean-rose { @apply bg-rose-500/10 text-rose-400 border border-rose-500/20 text-[11px] font-mono px-2 py-0.5 rounded; }
        .badge-clean-amber { @apply bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[11px] font-mono px-2 py-0.5 rounded; }
        .badge-clean-blue { @apply bg-blue-500/10 text-blue-400 border border-blue-500/20 text-[11px] font-mono px-2 py-0.5 rounded; }
        .badge-clean-zinc { @apply bg-zinc-800 text-zinc-400 border border-zinc-700/50 text-[11px] font-mono px-2 py-0.5 rounded; }
    </style>

    <script>
    function app() {
        return {
            currentPage: 'login',
            sidebarOpen: false,
            token: localStorage.getItem('payverify_token') || '',
            user: null,
            stats: null,
            donorStats: null,
            donorDonations: [],
            payments: [],
            invoices: [],
            subscription: null,
            loading: false,

            async init() {
                if (!this.token) {
                    this.currentPage = 'login';
                    return;
                }
                await this.fetchUser();
                if (this.user?.role === 'customer') {
                    this.currentPage = 'donor_dashboard';
                    await this.fetchDonorData();
                } else {
                    this.currentPage = 'dashboard';
                    await this.fetchStats();
                }
            },

            async fetchDonorData() {
                const stats = await this.api('GET', '/donor/stats');
                if (stats) this.donorStats = stats;
                const donations = await this.api('GET', '/donor/donations');
                if (donations) this.donorDonations = donations.data || [];
            },

            async api(method, url, body = null) {
                const opts = {
                    method,
                    headers: {
                        'Authorization': 'Bearer ' + this.token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                };
                if (body) opts.body = JSON.stringify(body);
                const res = await fetch('/api' + url, opts);
                if (res.status === 401) {
                    this.token = '';
                    localStorage.removeItem('payverify_token');
                    this.currentPage = 'login';
                    return null;
                }
                return res.json();
            },

            async fetchUser() {
                const data = await this.api('GET', '/auth/me');
                if (data) this.user = data.user || data;
            },

            async fetchStats() {
                const data = await this.api('GET', '/dashboard/stats');
                if (data) this.stats = data;
            },

            async fetchPayments() {
                const data = await this.api('GET', '/payments');
                if (data) this.payments = data.data || [];
            },

            async fetchInvoices() {
                const data = await this.api('GET', '/invoices');
                if (data) this.invoices = data.data || [];
            },

            async fetchSubscription() {
                const data = await this.api('GET', '/subscription');
                if (data) this.subscription = data;
            },

            async login(email, password) {
                this.loading = true;
                const res = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password }),
                });
                const data = await res.json();
                this.loading = false;
                if (data.token) {
                    this.token = data.token;
                    localStorage.setItem('payverify_token', data.token);
                    await this.fetchUser();
                    if (this.user?.role === 'customer') {
                        this.currentPage = 'donor_dashboard';
                        await this.fetchDonorData();
                    } else {
                        this.currentPage = 'dashboard';
                        await this.fetchStats();
                    }
                    return true;
                }
                return false;
            },

            async logout() {
                await this.api('POST', '/auth/logout');
                this.token = '';
                localStorage.removeItem('payverify_token');
                this.user = null;
                this.currentPage = 'login';
            },

            formatCurrency(val) {
                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
            },

            statusBadge(status) {
                const map = {
                    'VERIFIED': 'badge-clean-emerald', 'PAID': 'badge-clean-emerald',
                    'REJECTED': 'badge-clean-rose', 'AI_PROCESSING_FAILED': 'badge-clean-rose',
                    'WAITING_VERIFICATION': 'badge-clean-amber', 'AI_PROCESSING': 'badge-clean-blue',
                    'PROOF_UPLOADED': 'badge-clean-blue', 'WAITING_PAYMENT': 'badge-clean-zinc',
                    'PENDING': 'badge-clean-zinc',
                };
                return map[status] || 'badge-clean-zinc';
            },
        }
    }
    </script>

    @yield('scripts')
</body>
</html>
