# Aplikasi Self Assessment Pembinaan & Pengawasan Rumah Sakit (Binwasdal)

Aplikasi PHP untuk mengisi **Dokumen Self Assessment Pembinaan dan Pengawasan Rumah Sakit
di Wilayah Kota Administrasi Jakarta Pusat**, lengkap dengan:

- Tampilan **menyerupai dokumen Word** (kertas A4, Arial 11pt, tabel bergaris) — bukan formulir web biasa.
- **Unggah banyak dokumen sekaligus** pada tiap poin penilaian (seperti aplikasi akreditasi).
- **Folder Google Drive dibuat otomatis** per poin, di dalam folder induk yang Anda tentukan.
  Tautan yang muncul di aplikasi adalah **tautan folder** yang dapat diakses siapa saja yang memilikinya.
- Kotak **keterangan / hasil self assessment** dan **status** (Ada / Sebagian / Tidak Ada / Tidak Berlaku) per poin.
- **Cetak & simpan PDF** langsung dari browser dengan tata letak dokumen resmi.
- Rekap **kemajuan pengisian** per bagian dan **daftar seluruh tautan folder** untuk dilampirkan.

Seluruh isi dokumen (63 poin utama, **920 poin & sub-poin**, 253 baris tabel profil) diambil
langsung dari dokumen Word resmi, termasuk penomoran bertingkat `1 → a. → 1) → (a)`.

---

## 1. Kebutuhan

| Komponen   | Versi yang dipakai                |
|------------|-----------------------------------|
| PHP        | `webdevops/php-apache:8.2`        |
| Database   | `lscr.io/linuxserver/mariadb:11.4.8` |
| phpMyAdmin | `phpmyadmin:latest`               |

Ekstensi PHP yang diperlukan (**sudah tersedia** di image webdevops): `pdo_mysql`, `curl`,
`openssl`, `mbstring`, `fileinfo`, `json`. Tidak memerlukan Composer.

---

## 2. Pemasangan di CasaOS

### a. Salin berkas aplikasi

Letakkan seluruh isi repositori ini di sebuah folder di server, misalnya
`/DATA/AppData/binwasdal`.

### b. Jalankan dengan docker compose

```bash
cd /DATA/AppData/binwasdal
docker compose up -d
```

`docker-compose.yml` sudah berisi ketiga container. Ubah dulu kata sandi database
dan akun admin di dalamnya sebelum dijalankan di jaringan yang bisa diakses orang lain.

| Layanan     | Alamat                  |
|-------------|-------------------------|
| Aplikasi    | `http://<ip-server>:8088` |
| phpMyAdmin  | `http://<ip-server>:8089` |

### c. Bila container sudah Anda buat sendiri di CasaOS

Pastikan container `webdevops/php-apache:8.2` disetel seperti ini:

- **Volume**: folder aplikasi → `/app`
- **Environment**:

  | Variabel | Nilai |
  |---|---|
  | `WEB_DOCUMENT_ROOT` | `/app/public` |
  | `DB_HOST` | nama/IP container MariaDB |
  | `DB_PORT` | `3306` |
  | `DB_NAME` | `binwasdal` |
  | `DB_USER` | `binwasdal` |
  | `DB_PASS` | *(kata sandi database)* |
  | `ADMIN_USER` / `ADMIN_PASS` | akun awal aplikasi |
  | `GDRIVE_ROOT_FOLDER_ID` | `1f-KUg6Rq1CeZ4ZkksrPF7UbPiLI-GJjZ` |
  | `PHP_UPLOAD_MAX_FILESIZE` | `200M` |
  | `PHP_POST_MAX_SIZE` | `220M` |
  | `PHP_MAX_FILE_UPLOADS` | `40` |

> Jika `WEB_DOCUMENT_ROOT` tidak bisa diubah dan tetap `/app`, aplikasi tetap jalan —
> berkas `.htaccess` di akar akan meneruskan permintaan ke `public/`.

### d. Pemasangan awal

Buka aplikasi di browser. Halaman **Pemasangan** akan muncul otomatis pada akses pertama.
Tekan **🚀 Mulai Pemasangan** — tabel database dibuat dan seluruh struktur dokumen diisi.

Masuk dengan akun awal (`admin` / `admin123`), lalu **segera ganti kata sandinya**
di menu *Pengaturan → Pengguna*.

---

## 3. Menghubungkan Google Drive

Aplikasi membuat struktur folder berikut secara otomatis:

```
Folder Induk (1f-KUg6Rq1CeZ4ZkksrPF7UbPiLI-GJjZ)
└── Self Assessment 2026 - RS Khusus THT SS Medika
      ├── 00. PROFIL RUMAH SAKIT
      │     └── Legalitas Kelengkapan Perizinan - 4. Izin Mendirikan Bangunan (IMB)
      ├── 01. PENYELENGGARAAN LAYANAN
      │     ├── 1 Pelayanan Rawat Jalan
      │     ├── 1.a Waktu tunggu rawat jalan (Target <60 menit)
      │     └── …
      └── …
```

Setiap folder yang dibuat langsung dibagikan **“siapa saja yang memiliki link dapat melihat”**,
sehingga tautan yang muncul pada tiap poin bisa langsung dibuka Suku Dinas Kesehatan.

### Cara A — OAuth akun Google *(disarankan)*

Folder induk berada di My Drive milik akun Google Anda, jadi kuota penyimpanan memakai
kuota akun tersebut.

1. Buka [Google Cloud Console](https://console.cloud.google.com/) → buat/gunakan sebuah project →
   aktifkan **Google Drive API**.
2. **APIs & Services → Credentials → Create Credentials → OAuth client ID** → tipe **Web application**.
3. Pada **Authorized redirect URIs**, isikan alamat halaman pengaturan aplikasi, misalnya:
   `http://192.168.1.10:8088/index.php` — alamat persisnya ditampilkan di halaman
   *Pengaturan → Google Drive*, tinggal disalin.
4. Salin **Client ID** dan **Client Secret** ke halaman *Pengaturan → Google Drive*, lalu **Simpan**.
5. Klik **🔓 Minta izin Google**, setujui akses, lalu salin nilai parameter `code=` dari alamat
   browser dan tempel di kotak **Kode otorisasi** → **🔑 Tukar jadi Refresh Token**.
6. Klik **🔌 Tes koneksi** untuk memastikan folder induk terbaca.

### Cara B — Service Account

Isikan kunci JSON service account di halaman pengaturan, lalu bagikan folder induk ke alamat
`client_email` service account tersebut dengan hak **Editor**.

> Service account tidak memiliki kuota penyimpanan sendiri. Cara ini hanya andal bila folder
> induk berada di **Shared Drive** (Drive Bersama). Untuk folder di My Drive biasa, gunakan Cara A.

### Bila Drive belum dikonfigurasi

Aplikasi tetap berfungsi penuh — berkas disimpan di server pada `data/uploads` dengan struktur
folder yang sama. Setelah Drive dihubungkan, tombol **☁️ Pindahkan ke Google Drive** di halaman
Pengaturan akan memindahkan berkas-berkas tersebut (25 berkas per klik).

---

## 4. Cara pakai

| Menu | Isi |
|---|---|
| **Beranda & Rekapitulasi** | Persentase kelengkapan, jumlah dokumen, tautan folder utama |
| **Data Dasar & Profil RS** | Data dasar, kompetensi layanan, tempat tidur, perizinan (dengan unggah), sarana prasarana, kinerja, 10 penyakit terbanyak |
| **Penilaian Mandiri** (6 bagian) | Seluruh poin self assessment — status, keterangan, unggah dokumen |
| **SDM & Ketenagaan** | Tabel ketenagaan, rekapitulasi SDM, blok tanda tangan |
| **Cetak / Simpan PDF** | Pratinjau dokumen utuh siap cetak |
| **Daftar Tautan Folder** | Rekap seluruh tautan folder Drive, bisa disalin sekaligus |
| **Pengaturan** | Google Drive, periode penilaian, pengguna, log aktivitas |

Pada setiap poin:

- **Kotak keterangan** — ketik langsung, tersimpan otomatis (tanpa tombol simpan).
- **⬆️ Unggah** — pilih beberapa berkas sekaligus; bisa juga **seret & lepas** berkas ke barisnya.
- **📂 Folder** — membuat/membuka folder Drive poin tersebut walau belum ada berkas.
- Tautan folder dan daftar berkas langsung muncul di baris yang sama.

### Mencetak / menyimpan PDF

Buka **Cetak / Simpan PDF** → tombol **🖨️ Cetak**, atau `Ctrl + P`. Pada dialog cetak:

- Tujuan: **Save as PDF** / **Microsoft Print to PDF**
- Ukuran kertas: **A4**, Margin: **Default**
- Centang **Background graphics** agar arsiran tabel ikut tercetak

Tombol/kotak isian otomatis disembunyikan; yang tercetak adalah teks keterangan, status,
nama berkas, dan alamat tautan folder Drive.

---

## 5. Peran pengguna

| Peran | Hak |
|---|---|
| `admin` | Semua, termasuk pengaturan Google Drive dan pengguna |
| `editor` | Mengisi, mengunggah, dan menghapus berkas |
| `viewer` | Hanya melihat dan mencetak |

---

## 6. Struktur berkas

```
docker-compose.yml     Definisi 3 container (app, mariadb, phpmyadmin)
public/                Document root — index.php + aset
app/
  config.php           Konfigurasi (dibaca dari environment variable)
  bootstrap.php
  helpers.php
  lib/
    DB.php             Pembungkus PDO
    Auth.php           Login, peran, CSRF, log aktivitas
    Settings.php       Pengaturan key-value
    Installer.php      Pembuatan skema + pengisian master dokumen
    Forms.php          Definisi tabel-tabel profil
    Assessment.php     Query periode, pohon poin, rekap kemajuan
    GoogleDrive.php    Klien Drive API v3 (OAuth / service account)
    Storage.php        Folder & berkas (Drive atau lokal)
    Render.php         Komponen tampilan (kendali unggah, tabel, baris poin)
  views/               Halaman
db/
  schema.sql           Skema MariaDB
  master.json          Struktur dokumen hasil ekstraksi dokumen Word resmi
data/uploads/          Penyimpanan lokal (cadangan bila Drive nonaktif)
```

## 7. Basis data

Tabel utama: `users`, `settings`, `sections`, `items`, `form_rows`, `assessments`,
`profil_values`, `form_values`, `answers`, `drive_folders`, `documents`, `activity_log`.
Semuanya dapat dilihat/diedit lewat phpMyAdmin pada database `binwasdal`.

Membuat **periode penilaian baru** (misalnya tahun berikutnya, atau rumah sakit lain)
dilakukan lewat *Pengaturan → Periode Penilaian* — struktur dokumen dipakai bersama,
isian dan dokumennya terpisah per periode.

## 8. Catatan keamanan

- Kata sandi disimpan dengan `password_hash()`; seluruh aksi POST diperiksa token CSRF.
- Ekstensi berkas yang boleh diunggah dibatasi (`ALLOWED_EXT`), berkas `.php` ditolak.
- Folder `app/`, `db/`, dan `data/uploads/` diberi `.htaccess` penolak akses langsung;
  berkas lokal hanya dapat dibuka melalui aplikasi setelah login.
- Ganti `ADMIN_PASS`, `MYSQL_ROOT_PASSWORD`, dan `MYSQL_PASSWORD` sebelum dipakai di jaringan
  yang lebih luas, dan jangan mengekspos port phpMyAdmin ke internet.
