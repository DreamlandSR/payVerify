<p align="center">
  <a href="#">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="320" alt="PayVerify Logo">
  </a>
</p>

<h1 align="center">PayVerify — Platform SaaS AI Payment & Donation Verification</h1>

<p align="center">
  <strong>Sistem Verifikasi Pembayaran & Donasi Berbasis AI Multimodal (Google Gemini 2.5 Flash Vision) dengan Pendekatan Human-in-the-Loop (HITL).</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/AI%20Engine-Gemini%202.5%20Flash-4285F4?style=for-the-badge&logo=google" alt="Google Gemini AI">
  <img src="https://img.shields.io/badge/Tests-42%20Passed-emerald?style=for-the-badge" alt="Tests Passed">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="License MIT">
</p>

---

## 📌 1. Pengenalan (Overview)

**PayVerify** adalah platform *Multi-Tenant SaaS* modern yang dirancang untuk mengotomatisasi dan mempercepat proses verifikasi bukti transfer pembayaran serta donasi menggunakan **AI Multimodal (Google Gemini 2.5 Flash Vision)**. 

Sistem ini menerapkan pendekatan **Human-in-the-Loop (HITL)**:
1. **Otomasi AI**: AI membaca visual piksel foto struk transfer (BCA, Mandiri, BRI, BNI, GoPay, OVO, DANA, ShopeePay, QRIS), mengekstraksi nominal donasi, tanggal, jam, nomor referensi/RRN, dan menilai tingkat risiko penipuan (*fraud risk scoring*).
2. **Keputusan Manusia (Admin)**: Pengelola/Admin tetap memegang kendali penuh dalam mengambil keputusan persetujuan akhir (*Approve* / *Reject*) melalui *Interactive HITL Workbench*.

---

## ✨ 2. Fitur-Fitur Utama

### 🤖 Core AI Vision & Verification Engine
- **Instant AI OCR Detection**: Menggunakan model `gemini-2.5-flash` untuk mendeteksi teks dan nominal donasi dari piksel foto struk transfer secara *real-time* (< 2 detik).
- **Auto Image Optimization**: Fitur presisi & kompresi gambar otomatis (maks 1200px) sebelum dikirim ke API Cloud untuk mencegah *cURL timeout* pada foto kamera HP resolusi tinggi.
- **Detector Struk Palsu / Gambar Acak**: AI otomatis mengenali dan menolak gambar non-struk (seperti logo, foto pemandangan, meme, avatar) dengan memberikan pesan peringatan jelas.
- **Risk Analysis & Fraud Protection**: Deteksi duplikasi struk berbasis *SHA256 File Hashing*, pembacaan kecocokan tanggal, dan skor risiko (*LOW*, *MEDIUM*, *HIGH*).

### 👥 Portal & Manajemen Role (RBAC)
- **Admin & Verifikator Panel** (`owner@test.com`):
  - **Dashboard Analytics**: Ringkasan total donasi, metrik verifikasi, chart pendapatan, dan aktivitas *audit log*.
  - **Invoices & Campaign QRIS**: Buat kampanye donasi/invoice baru lengkap dengan QRIS generator.
  - **Verifikasi Struk AI Workbench**: Antrean peninjauan struk *side-by-side* (Foto Struk vs Ekstraksi AI).
- **Portal Donatur / Customer** (`donor@test.com`):
  - **Portal Upload Donasi Nominal Bebas**: Donatur dapat transfer nominal bebas via QRIS dan mengunggah foto struk tanpa harus memasukkan nomor invoice manual.
  - **Umpan Balik AI Instant**: Donatur langsung melihat kartu status validasi nominal yang dibaca oleh AI saat foto diunggah.
  - **Dashboard Donasi Saya**: Pelacakan status verifikasi secara *live* (`🟢 Terverifikasi`, `🔵 Menunggu Admin`, `🔴 Ditolak`).
  - **Kwitansi Digital Resmi**: Modal dan fitur cetak/unduh PDF kwitansi donasi resmi bertanda tangan verifikasi.

---

## 🛠️ 3. Persyaratan Sistem (Prerequisites)

Sebelum menginstal proyek, pastikan lingkungan server/komputer Anda memenuhi persyaratan berikut:

- **PHP**: Versi `>= 8.4` (dengan ekstensi `gd`, `pdo_sqlite` / `pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `openssl`)
- **Composer**: Versi `>= 2.x`
- **Node.js**: Versi `>= 18.x` & `npm`
- **Google Gemini API Key**: Dapatkan gratis dari [Google AI Studio](https://aistudio.google.com/app/apikey)

---

## 🚀 4. Langkah-Langkah Instalasi (Setup & Installation)

Ikuti langkah-langkah di bawah ini untuk menjalankan proyek di lingkungan lokal:

### Langkah 1: Clone Repository
```bash
git clone https://github.com/DreamlandSR/payVerify.git
cd payVerify
```

### Langkah 2: Install Dependensi PHP & JavaScript
```bash
composer install
npm install
```

### Langkah 3: Konfigurasi Environment (`.env`)
Salin file `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```

Buka file `.env` dan tambahkan kunci API Gemini Anda:
```env
APP_NAME="PayVerify"
APP_URL="http://localhost:8000"

DB_CONNECTION=sqlite

# Konfigurasi AI Gemini Vision
AI_DRIVER=gemini
GEMINI_API_KEY=AIzaSy... (Masukkan API Key Gemini Anda dari Google AI Studio)
```

### Langkah 4: Generate Application Key & Database Seed
```bash
php artisan key:generate
php artisan migrate:fresh --seed
```
*Seeder otomatis menyiapkan data awal untuk akun Admin (`owner@test.com`), Donatur (`donor@test.com`), serta sampel transaksi donasi.*

### Langkah 5: Build Frontend Assets
```bash
npm run build
```

### Langkah 6: Jalankan Server Lokal
```bash
php artisan serve
```
Aplikasi kini berjalan dan dapat diakses di **`http://127.0.0.1:8000`** (atau port yang tampil di terminal).

---

## 📖 5. Cara Penggunaan (Usage Guide)

### 🔑 Akun Demo Bawaan (Seeded Users)
| Role | Email | Password | Hak Akses Utama |
| :--- | :--- | :--- | :--- |
| **Admin SaaS / Yayasan** | `owner@test.com` | `password` | Akses penuh Admin Panel, Analytics, QRIS, dan Workbench Verifikasi AI |
| **Donatur / Customer** | `donor@test.com` | `password` | Akses Dashboard Donasi Saya, Unggah Struk, Pelacakan Status, & Cetak Kwitansi |

---

### A. Penggunaan Bagi Donatur (Portal Donasi Nominal Bebas)
1. **Login atau Masuk ke Portal**:
   - Buka halaman login di `http://localhost:8000` dan pilih tab **Donatur / User**, atau klik menu **Kirim Bukti Pembayaran** di sidebar.
2. **Scan QRIS & Transfer**:
   - Scan kode QRIS yang tampil di layar menggunakan aplikasi M-Banking atau E-Wallet pilihan Anda dengan nominal donasi bebas.
3. **Unggah Foto Struk Transfer**:
   - Pilih foto/screenshot resi transfer dari galeri HP Anda (`JPG`, `PNG`, `WEBP`).
   - Klik **`Kirim Struk & Deteksi Nominal AI`**.
4. **Menerima Hasil Analisis AI Instant**:
   - **Jika Valid**: Tampil kartu hijau (*✓ AI OCR Berhasil Membaca Struk*) beserta rincian nominal terdeteksi (contoh: `Rp 150.000` / `Rp 1.552.500`), bank provider, dan tanggal.
   - **Jika Tidak Valid / Bukan Struk**: Tampil kartu merah (*❌ FOTO STRUK TIDAK VALID*) memberitahukan bahwa gambar buram atau tidak mengandung angka nominal transfer yang sah.
5. **Cetak Kwitansi Digital**:
   - Buka menu **Dashboard Donasi Saya** dan klik **Lihat Kwitansi** pada transaksi yang telah terverifikasi lunas untuk mengunduh/mencetak kwitansi resmi.

---

### B. Penggunaan Bagi Admin (Persetujuan Human-in-the-Loop)
1. **Login Admin Panel**:
   - Buka `http://localhost:8000`, pilih tab **Admin Panel**, dan login menggunakan `owner@test.com` / `password`.
2. **Buka Menu Verifikasi Struk AI**:
   - Klik menu **Verifikasi Struk AI** pada sidebar sebelah kiri.
3. **Peninjauan Side-by-Side**:
   - Pilih salah satu transaksi di antrean verifikasi untuk membuka layar perbandingan *Side-by-Side*:
     - **Sisi Kiri**: Foto fisik resi transfer yang diunggah donatur.
     - **Sisi Kanan**: Hasil ekstraksi OCR AI Gemini (Nominal, Bank, No. Ref, Tanggal) & Skor Risiko Penipuan.
4. **Pengambilan Keputusan (HITL)**:
   - Klik tombol **`Setujui (Approve)`** untuk memverifikasi transaksi dan menerbitkan kwitansi donasi resmi.
   - Atau klik **`Tolak (Reject)`** dengan memberikan alasan penolakan (misal: "Nominal tidak sesuai" / "Gambar tidak terbaca").

---

## 🧪 6. Pengujian Otomatis (Automated Testing)

Proyek ini dilengkapi dengan suite pengujian automated test yang mencakup controller, enkapsulasi tenant, validasi OCR AI, dan otorisasi:

Gunakan perintah Artisan berikut untuk menjalankan pengujian:
```bash
php artisan test --compact
```
- **Hasil Pengujian saat ini**: **42 / 42 Tests Passed (100% Passing, 190 Assertions)**.

---

## 📐 7. Standar Commit Git (Git Commit Convention)

Proyek ini menerapkan standar penulisan pesan commit Git **Conventional Commits**:
- Format: `<type>(<scope>): <deskripsi singkat>`
- Contoh: `feat(ai): integrate gemini 2.5 flash vision ocr`
- Dokumentasi aturan commit lengkap dapat dilihat di berkas **[.agents/rules/commit-convention.md](file:///.agents/rules/commit-convention.md)**.

---

## 📄 8. Lisensi (License)

Proyek ini dirilis di bawah lisensi **[MIT License](LICENSE)**.
