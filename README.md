# Aplikasi Self Assessment Pembinaan & Pengawasan Rumah Sakit (Binwasdal)

Aplikasi PHP untuk mengisi **Dokumen Self Assessment Pembinaan dan Pengawasan Rumah Sakit
di Wilayah Kota Administrasi Jakarta Pusat**, lengkap dengan:

- Tampilan **menyerupai dokumen Word** (kertas A4, Arial 11pt, tabel bergaris) — bukan formulir web biasa.
  Kolom hasil hanya menampilkan penanda ringkas (**Ada / Belum Ada / kosong**), sehingga tabel tetap bersih
  seperti dokumen aslinya.
- **Jendela popup per poin** untuk memilih status, menulis keterangan, mengunggah dokumen, dan melihat
  berkas yang sudah terkirim — dibuka dengan mengklik kolom hasil pada baris mana pun.
- **Unggah banyak dokumen sekaligus** pada tiap poin penilaian (seperti aplikasi akreditasi).
- **Folder Google Drive dibuat otomatis** per poin, di dalam folder induk yang Anda tentukan.
  Tautan yang muncul di aplikasi adalah **tautan folder** yang dapat diakses siapa saja yang memilikinya.
- **Status** per poin (Ada / Sesuai, Sebagian, Belum Ada, Tidak Berlaku) beserta keterangan bebas.
- **Cetak & simpan PDF** langsung dari browser dengan tata letak dokumen resmi.
- **Unduh Word (.docx)** — berkas Word asli (A4, Arial, tabel bergaris, tautan folder Drive
  dapat diklik) yang masih bisa diedit dan ditandatangani.
- Rekap **kemajuan pengisian** per bagian dan **daftar seluruh tautan folder** untuk dilampirkan.

Seluruh isi dokumen (63 poin utama, **920 poin & sub-poin**, 253 baris tabel profil) diambil
langsung dari dokumen Word resmi. **Penomoran tiap poin dibaca dari definisi penomoran
dokumen aslinya** (`word/numbering.xml`), sehingga daftar yang di dokumen bernomor
`1) 2) 3)` tidak berubah menjadi `a. b. c.` — termasuk daftar berbutir `-` dan
penomoran yang berlanjut lintas halaman.

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

### c-bis. Memperbarui aplikasi tanpa git (unduh ZIP + unggah manual)

Cara ini yang dipakai bila Anda mengunduh **Code → Download ZIP** dari GitHub lalu
menyalin berkasnya lewat *code-server* di CasaOS.

1. Di GitHub, pastikan branch yang tampil adalah branch aplikasi, lalu **Code → Download ZIP**.
2. Buka ZIP-nya. Isinya berada di dalam satu folder pembungkus (mis. `binwasdal-main/`).
   Yang disalin ke server adalah **isi** folder itu (`app/`, `public/`, `db/`, …),
   bukan folder pembungkusnya.
3. Salin menimpa ke folder aplikasi di server. Pastikan **semua** folder ikut tergantikan —
   terutama `app/` dan `public/`.
4. Buka aplikasi → menu **Pengaturan** → kartu **🧾 Versi Aplikasi**:
   - Cocokkan **Versi** dengan yang tertulis di `app/version.php` pada ZIP.
   - Cocokkan **waktu berkas** dengan waktu Anda menyalin. Kalau waktunya masih lama,
     berarti berkas belum tergantikan — biasanya tersalin ke folder lain.
5. Bila waktu berkas sudah baru tetapi tampilan belum berubah, klik
   **🔄 Muat ulang kode program** pada kartu yang sama.

> **Kenapa perlu langkah 5?** Image `webdevops/php-apache` memakai **OPcache**: hasil kompilasi
> berkas PHP disimpan di memori. Bila `opcache.validate_timestamps=0`, berkas PHP yang baru
> diunggah **diabaikan** sampai cache dikosongkan atau container di-restart — menekan
> `Ctrl+Shift+R` di peramban tidak menolong karena masalahnya di sisi server, bukan peramban.
>
> Agar tidak berulang, setel environment berikut pada container aplikasi
> (sudah disertakan di `docker-compose.yml`):
>
> | Variabel | Nilai |
> |---|---|
> | `PHP_OPCACHE_VALIDATE_TIMESTAMPS` | `1` |
> | `PHP_OPCACHE_REVALIDATE_FREQ` | `0` |
>
> Kartu **Versi Aplikasi** menampilkan status OPcache yang sedang berlaku, sehingga
> kondisi ini langsung terlihat.

Berkas CSS dan JavaScript **tidak perlu** `Ctrl+Shift+R`: alamatnya otomatis membawa
penanda waktu berkas, jadi peramban selalu mengambil versi terbaru begitu berkasnya berganti.

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
| **Unduh Word (.docx)** | Berkas Word asli — seluruh dokumen atau per bagian |
| **Daftar Tautan Folder** | Rekap seluruh tautan folder Drive, bisa disalin sekaligus |
| **Pengaturan** | Versi aplikasi &amp; muat ulang kode, Google Drive, periode penilaian, pengguna, log aktivitas |

### Mengisi sebuah poin

Kolom **Hasil Self Assessment** sengaja dibuat ringkas: hanya berisi penanda status
(*Ada / Sesuai*, *Sebagian*, *Belum Ada*, *Tidak Berlaku*), jumlah berkas terlampir, dan
ringkasan keterangan. Poin yang belum diisi tampil kosong, persis seperti dokumen Word.

Klik kolom tersebut pada baris mana pun untuk membuka **jendela pengisian**:

- **Status** — pilih salah satu tombol; tersimpan otomatis.
- **Keterangan** — ketik bebas, tersimpan otomatis beberapa saat setelah berhenti mengetik.
- **Dokumen pendukung** — klik area bergaris putus-putus untuk memilih **beberapa berkas
  sekaligus**, atau **seret & lepas** berkas ke area itu. Berkas yang jenisnya tidak
  diizinkan atau melebihi batas ukuran ditolak seketika, sebelum dikirim ke server.
- **Daftar berkas** — setiap berkas dapat dibuka atau dihapus (ikut terhapus dari Google Drive).
- **Tautan folder Drive** poin tersebut muncul di jendela ini setelah unggahan pertama.

Tekan **Esc** atau **Simpan & Tutup** untuk kembali. Penanda pada baris langsung diperbarui.

### Saat dokumen diunggah

Selama unggahan berjalan muncul **jendela progres** yang mengunci layar:

- **Persentase dan bilah kemajuan** dari total seluruh berkas, beserta jumlah byte terkirim
- Penunjuk **berkas ke berapa dari berapa**, dan daftar berkas dengan tanda ⏳ / ✅ / ❌
- Jendela **tidak dapat ditutup** — tombol Esc, tombol silang, dan klik di luar jendela
  diabaikan; peramban juga memperingatkan bila halaman hendak ditinggalkan
- Satu-satunya jalan keluar adalah **✕ Batalkan**. Berkas yang sudah terkirim tetap
  tersimpan, sisanya tidak jadi dikirim, dan jumlahnya dilaporkan

Bila semua berkas berhasil, jendela menutup sendiri. Bila ada yang gagal, jendela tetap
terbuka menampilkan sebab kegagalan tiap berkas sampai Anda menekan **Selesai**.

Berkas dikirim satu per satu, sehingga kemajuannya akurat dan unggahan banyak berkas
besar tidak terbentur batas `post_max_size`.

### Mencetak / menyimpan PDF

Buka **Cetak / Simpan PDF** → tombol **🖨️ Cetak**, atau `Ctrl + P`. Pada dialog cetak:

- Tujuan: **Save as PDF** / **Microsoft Print to PDF**
- Ukuran kertas: **A4**, Margin: **Default**
- Centang **Background graphics** agar arsiran tabel ikut tercetak

Tombol/kotak isian otomatis disembunyikan; yang tercetak adalah teks keterangan, status,
nama berkas, dan alamat tautan folder Drive.

### Mengunduh berkas Word

Tombol **📄 Unduh Word (.docx)** tersedia di beranda, menu samping, halaman cetak, dan pada
tiap halaman bagian (untuk mengunduh satu bagian saja). Berkas yang dihasilkan adalah
dokumen Word asli — bukan HTML yang disamarkan — sehingga dapat langsung diedit di
Microsoft Word, LibreOffice, atau Google Docs. Tautan folder Google Drive di dalamnya
tetap bisa diklik.

Fitur ini memerlukan ekstensi PHP `zip` (sudah tersedia pada image `webdevops/php-apache`).

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
  version.php          Nomor versi aplikasi (tampil di menu Pengaturan)
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
    Render.php         Komponen tampilan (sel ringkas, tabel, baris poin)
    DocxWriter.php     Penulis berkas .docx (OOXML) tanpa pustaka luar
    EksporWord.php     Menyusun dokumen Word dari data periode penilaian
  views/               Halaman
db/
  schema.sql           Skema MariaDB
  master.json          Struktur dokumen hasil ekstraksi dokumen Word resmi
  ekstrak_dokumen.py   Skrip yang menghasilkan master.json dari berkas .docx asli
data/uploads/          Penyimpanan lokal (cadangan bila Drive nonaktif)
```

## 7. Basis data

Tabel utama: `users`, `settings`, `sections`, `items`, `form_rows`, `assessments`,
`profil_values`, `form_values`, `answers`, `drive_folders`, `documents`, `activity_log`.
Semuanya dapat dilihat/diedit lewat phpMyAdmin pada database `binwasdal`.

Membuat **periode penilaian baru** (misalnya tahun berikutnya, atau rumah sakit lain)
dilakukan lewat *Pengaturan → Periode Penilaian* — struktur dokumen dipakai bersama,
isian dan dokumennya terpisah per periode.

## 8. Bila terjadi kesalahan

Kegagalan pada jendela pengisian (simpan, unggah, buat folder) selalu dijawab dengan
**pesan yang menyebutkan sebabnya**, bukan sekadar kode kesalahan. Pesan yang sama juga
tercatat di *Pengaturan → Aktivitas Terakhir* dengan aksi `galat`, sehingga masih bisa
ditelusuri setelah jendela ditutup.

Nama folder Google Drive dipotong hingga 150 karakter (pada batas kata, ditandai `…`)
karena beberapa judul poin pada dokumen resmi lebih dari 270 karakter — terlalu panjang
untuk kolom nama di database dan menyulitkan saat dibaca di Google Drive.

## 9. Catatan keamanan

- Kata sandi disimpan dengan `password_hash()`; seluruh aksi POST diperiksa token CSRF.
- Ekstensi berkas yang boleh diunggah dibatasi (`ALLOWED_EXT`), berkas `.php` ditolak.
- Folder `app/`, `db/`, dan `data/uploads/` diberi `.htaccess` penolak akses langsung;
  berkas lokal hanya dapat dibuka melalui aplikasi setelah login.
- Ganti `ADMIN_PASS`, `MYSQL_ROOT_PASSWORD`, dan `MYSQL_PASSWORD` sebelum dipakai di jaringan
  yang lebih luas, dan jangan mengekspos port phpMyAdmin ke internet.
