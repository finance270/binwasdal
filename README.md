# Aplikasi Dokumen Rumah Sakit — Binwasdal & Dokumen Internal

Aplikasi PHP untuk RS Khusus THT SS Medika yang terdiri dari **dua modul terpisah**,
dipilih lewat tombol di bilah atas:

| Modul | Isi |
|---|---|
| 📊 **Binwasdal** | Pengisian Dokumen Self Assessment Pembinaan dan Pengawasan Rumah Sakit di Wilayah Kota Administrasi Jakarta Pusat |
| 📁 **Dokumen Internal** | Pembuatan, pengubahan, pencetakan, dan penyimpanan hasil scan regulasi & naskah dinas rumah sakit — Peraturan/Surat Keputusan Direktur, Pedoman, SPO, Formulir, sampai surat tugas dan notulen |

Kedua modul memakai satu basis data, satu akun pengguna, dan satu folder induk Google Drive.

---

## Modul 📊 Binwasdal

Untuk mengisi **Dokumen Self Assessment Pembinaan dan Pengawasan Rumah Sakit
di Wilayah Kota Administrasi Jakarta Pusat**, lengkap dengan:

- Tampilan **menyerupai dokumen Word** (kertas A4, Arial 11pt, tabel bergaris) — bukan formulir web biasa.
  Kolom hasil hanya menampilkan penanda ringkas (**Ada / Belum Ada / kosong**), sehingga tabel tetap bersih
  seperti dokumen aslinya.
- **Jendela popup per poin** untuk memilih status, menulis keterangan, mengunggah dokumen, dan melihat
  berkas yang sudah terkirim — dibuka dengan mengklik kolom hasil pada baris mana pun.
- **Unggah banyak dokumen sekaligus** pada tiap poin penilaian (seperti aplikasi akreditasi) —
  **semua jenis berkas diterima**: dokumen, foto, **video** (mp4/mov/3gp dari ponsel), audio,
  arsip, sampai 1 GB per berkas.
- **Folder Google Drive dibuat otomatis** per poin, di dalam folder induk yang Anda tentukan.
  Tautan yang muncul di aplikasi adalah **tautan folder** yang dapat diakses siapa saja yang memilikinya.
- **Status** per poin (Ada / Sesuai, Sebagian, Belum Ada, Tidak Berlaku) beserta keterangan bebas.
- **Cetak & simpan PDF** langsung dari browser dengan tata letak dokumen resmi.
- **Unduh Word (.docx)** — berkas Word asli (A4, Arial, tabel bergaris, tautan folder Drive
  dapat diklik) yang masih bisa diedit dan ditandatangani.
- Rekap **kemajuan pengisian** per bagian dan **daftar seluruh tautan folder** untuk dilampirkan.
- **Dapat dipakai dari ponsel** (Android maupun iPhone/iPad) — menu menjadi laci geser,
  baris penilaian ditumpuk agar tidak perlu digeser ke samping, dan jendela pengisian
  muncul sebagai lembar dari bawah layar.

Seluruh isi dokumen (63 poin utama, **920 poin & sub-poin**, 253 baris tabel profil) diambil
langsung dari dokumen Word resmi. **Penomoran tiap poin dibaca dari definisi penomoran
dokumen aslinya** (`word/numbering.xml`), sehingga daftar yang di dokumen bernomor
`1) 2) 3)` tidak berubah menjadi `a. b. c.` — termasuk daftar berbutir `-` dan
penomoran yang berlanjut lintas halaman.

---

## Modul 📁 Dokumen Internal

Untuk menyusun regulasi dan naskah dinas rumah sakit sesuai **Pedoman Tata Naskah
RS Khusus THT SS Medika**:

- **16 jenis naskah siap pakai** — Peraturan Direktur, Surat Keputusan Direktur,
  Pedoman/Panduan, SPO, Instruksi Kerja, Formulir, Instruksi & Surat Edaran Direktur,
  Surat Tugas, Nota Dinas, Surat Dinas, Undangan, Berita Acara, Surat Keterangan,
  Pengumuman, Surat Pernyataan, dan Notulen Rapat.
- **Nomor dibuat otomatis** mengikuti rumus penomoran pedoman
  (`001/SK/DIR/SSM/2026`, `001/SPO/KEP/2026`, `F: 001/SSM/2026`, …), direset tiap tahun
  dan dihitung terpisah per bagian untuk jenis yang memakai kode bagian.
- **Isian per blok** sesuai susunan naskah masing-masing — Menimbang, Mengingat, diktum
  KESATU/KEDUA/… untuk naskah penetapan; Pengertian, Tujuan, Kebijakan, Prosedur,
  Unit Terkait untuk SPO. Semuanya tersimpan otomatis saat berhenti mengetik.
- **Kerangka baku pedoman** (BAB I–IX Pelayanan, BAB I–XI Pengorganisasian) dapat
  dimasukkan sekali klik.
- **Cetak / simpan PDF** dengan tata letak naskah sesungguhnya — kop surat, konsiderans,
  diktum bertabel, kaki tanda tangan; kepala SPO memakai tabel identitas seperti formulir aslinya.
- **Unduh Word (.docx)** per naskah, memakai huruf sesuai jenisnya (Bookman Old Style 12
  untuk naskah pengaturan/penetapan, Arial 12 untuk naskah penugasan/korespondensi/khusus).
- **Simpan hasil scan** naskah yang sudah ditandatangani dan dicap — otomatis diunggah ke
  folder Google Drive naskah tersebut, dengan jendela progres berpersentase.
- **Pengendalian dokumen**: status (Draf → Diperiksa → Disahkan → Tidak Berlaku),
  klasifikasi salinan (Master / Terkendali / Tidak Terkendali / Absolute), pencatatan
  distribusi salinan, riwayat perubahan, dan **revisi** yang otomatis menandai naskah
  lama sebagai tidak berlaku.
- **Daftar Induk Dokumen** siap cetak, dikelompokkan per jenis naskah.
- Naskah yang belum disahkan atau sudah dicabut dicetak dengan **cap DRAF /
  BELUM DISAHKAN / TIDAK BERLAKU**, agar tidak keliru dipakai sebagai acuan kerja.

Halaman **Pedoman Tata Naskah** di dalam aplikasi memuat seluruh acuan yang dipakai —
tingkatan regulasi, rumus penomoran, kewenangan pengesahan, klasifikasi salinan,
sistematika baku, serta perbandingan dengan ketentuan tata naskah terbaru
(lihat bagian 12 di bawah).

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

Tombol **📊 Binwasdal / 📁 Dokumen Internal** di bilah atas memindahkan aplikasi antar modul.
Menu samping ikut berganti mengikuti modul yang sedang dibuka.

### Menu modul 📊 Binwasdal

| Menu | Isi |
|---|---|
| **Beranda & Rekapitulasi** | Persentase kelengkapan, jumlah dokumen, tautan folder utama |
| **Data Dasar & Profil RS** | Data dasar, kompetensi layanan, tempat tidur, perizinan (dengan unggah), sarana prasarana, kinerja, 10 penyakit terbanyak |
| **Penilaian Mandiri** (6 bagian) | Seluruh poin self assessment — status, keterangan, unggah dokumen |
| **SDM & Ketenagaan** | Tabel ketenagaan, rekapitulasi SDM, blok tanda tangan |
| **Cetak / Simpan PDF** | Pratinjau dokumen utuh siap cetak |
| **Unduh Word (.docx)** | Berkas Word asli — seluruh dokumen atau per bagian |
| **Daftar Tautan Folder** | Rekap seluruh tautan folder Drive, bisa disalin sekaligus |
| **Pengaturan** | Versi aplikasi &amp; muat ulang kode, identitas kop naskah, Google Drive, periode penilaian, pengguna, log aktivitas |

### Menu modul 📁 Dokumen Internal

| Menu | Isi |
|---|---|
| **Daftar Dokumen** | Rekap, penapis (jenis/bagian/status/tahun/pencarian), formulir buat naskah baru |
| **Daftar Induk Dokumen** | Rekaman pengendalian dokumen siap cetak, dikelompokkan per jenis |
| **Jenis Naskah** | Pintasan penapis ke satu jenis naskah tertentu |
| **Pedoman Tata Naskah** | Acuan yang dipakai aplikasi + perbandingan dengan ketentuan terbaru |

### Membuat sebuah naskah internal

1. Buka **Dokumen Internal → Daftar Dokumen**, isi **Buat Naskah Baru**
   (jenis, bagian penerbit, tahun, judul), lalu klik **Buat & Isi Naskah**.
   Nomor terbentuk sendiri — misalnya `002/SPO/KEP/2026`.
2. Lengkapi **isian kepala** (tanggal ditetapkan, mulai berlaku, rencana peninjauan,
   penyiap/pemeriksa/pengesah) dan **blok isi** sesuai jenis naskahnya.
   Semua tersimpan otomatis; tidak ada tombol Simpan yang perlu ditekan.
   Aturan penulisan tiap blok muncul sebagai petunjuk di atas kotak isian:
   - blok **daftar** → satu baris satu butir, penomoran dibuat saat dicetak;
   - blok **diktum** → satu baris satu diktum, label KESATU/KEDUA/… otomatis
     (tulis `LABEL : isi` bila ingin menentukan sendiri);
   - blok **tabel** → baris pertama menjadi judul kolom, kolom dipisah tanda `|`.
3. **Pratinjau** naskah tampil di bagian bawah halaman; tekan **🔄 Perbarui pratinjau**
   setelah mengubah isi.
4. **Cetak / Simpan PDF** atau **Unduh Word** untuk ditandatangani.
5. Setelah ditandatangani dan dicap, unggah hasil pemindaian pada
   **Hasil Scan & Lampiran** — berkas masuk ke folder Drive naskah itu.
6. Ubah **status** menjadi *Disahkan* pada **Pengendalian Dokumen**, lalu catat
   unit penerima salinan terkendali.
7. Bila kelak berubah, tekan **🔁 Buat revisi baru** — naskah lama otomatis menjadi
   *Tidak Berlaku (Absolute)* dan seluruh isinya disalin ke naskah baru dengan
   nomor revisi bertambah satu.

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
    Naskah.php         Master jenis naskah: penomoran, huruf, pengesahan, susunan blok
    Dokumen.php        Dokumen internal: penomoran otomatis, isi, revisi, distribusi
    RenderNaskah.php   Merender naskah menjadi tampilan siap cetak
    EksporNaskah.php   Menyusun berkas Word (.docx) satu naskah internal
  views/               Halaman
db/
  schema.sql           Skema MariaDB (modul Binwasdal)
  schema_dokumen.sql   Skema MariaDB (modul Dokumen Internal)
  master.json          Struktur dokumen hasil ekstraksi dokumen Word resmi
  ekstrak_dokumen.py   Skrip yang menghasilkan master.json dari berkas .docx asli
data/uploads/          Penyimpanan lokal (cadangan bila Drive nonaktif)
```

## 7. Basis data

Modul Binwasdal: `users`, `settings`, `sections`, `items`, `form_rows`, `assessments`,
`profil_values`, `form_values`, `answers`, `drive_folders`, `documents`, `activity_log`.

Modul Dokumen Internal: `dokumen`, `dokumen_isi`, `dokumen_riwayat`, `dokumen_distribusi`,
`dokumen_folder`, `dokumen_berkas`. Tabel-tabel ini dibuat sendiri saat aplikasi pertama
kali dibuka setelah pembaruan — **tidak ada tabel lama yang diubah**, sehingga data
Binwasdal yang sudah ada tetap utuh.

Semuanya dapat dilihat/diedit lewat phpMyAdmin pada database `binwasdal`.

Membuat **periode penilaian baru** (misalnya tahun berikutnya, atau rumah sakit lain)
dilakukan lewat *Pengaturan → Periode Penilaian* — struktur dokumen dipakai bersama,
isian dan dokumennya terpisah per periode.

## 8. Memakai dari ponsel

Aplikasi menyesuaikan diri pada layar kecil, baik di **Chrome Android** maupun
**Safari iOS/iPadOS**:

| Bagian | Di ponsel |
|---|---|
| Menu samping | Menjadi **laci geser**, dibuka lewat tombol ☰ di kiri atas |
| Baris penilaian | **Ditumpuk**: nomor dan uraian di atas, kolom hasil menjadi bidang sentuh di bawahnya — tidak perlu digeser ke samping |
| Data dasar RS | Label di atas, kolom isian di bawahnya, selebar layar |
| Tabel profil (perizinan, SDM, dll.) | Tetap berbentuk tabel dan **digeser ke samping**, dengan petunjuk “← geser …” bila memang lebih lebar dari layar |
| Jendela pengisian &amp; progres unggah | Muncul sebagai **lembar dari bawah layar**, tombol selebar layar |

Penyesuaian khusus iOS:

- Kolom isian memakai huruf 16px sehingga **Safari tidak memperbesar halaman** saat disentuh
- Area aman perangkat berponi (`env(safe-area-inset-*)`) dihormati pada bagian bawah jendela
- Tinggi memakai satuan `dvh` agar tidak terpotong bilah alamat Safari
- Pembesaran teks otomatis saat layar diputar dimatikan

Tampilan layar lebar dan hasil cetak **tidak berubah** — seluruh aturan ponsel
dibatasi ke media `screen` dengan lebar tertentu.

## 9. Jenis dan ukuran berkas

Semua jenis berkas diterima — termasuk **video** hasil rekaman ponsel (`.mp4`, `.mov`,
`.3gp`), **audio** (`.m4a`, `.mp3`, `.amr`), foto (termasuk `.heic` dari iPhone), arsip,
dan format apa pun lainnya. Batas bawaan **1 GB per berkas**.

Yang **ditolak** hanyalah jenis yang dapat dijalankan di server atau dieksekusi peramban
atas nama aplikasi — `.php`, `.sh`, `.exe`, `.html`, `.svg`, dan sejenisnya. Penolakan
terjadi di peramban, sebelum berkas terkirim, jadi tidak membuang waktu.

Daftar ini dapat diubah lewat environment variable:

| Variabel | Arti |
|---|---|
| `ALLOWED_EXT` | `*` (bawaan) berarti semua jenis. Isi daftar dipisah koma bila ingin membatasi |
| `BLOCKED_EXT` | Jenis yang selalu ditolak, apa pun isi `ALLOWED_EXT` |
| `MAX_UPLOAD_SIZE` | Batas ukuran per berkas dalam byte |

Batas yang benar-benar berlaku adalah nilai **terkecil** antara `MAX_UPLOAD_SIZE`,
`PHP_UPLOAD_MAX_FILESIZE`, dan `PHP_POST_MAX_SIZE` — ketiganya perlu dinaikkan bersamaan
bila ingin menerima berkas lebih besar. Nilainya ditampilkan di area unggah.

**Video berukuran besar tidak membebani memori server.** Berkas di atas 5 MB dikirim ke
Google Drive dengan metode *resumable*, mengalir langsung dari disk. Diuji dengan video
300 MB: pemakaian memori puncak hanya 2 MB.

Selama Google Drive belum aktif, berkas tersimpan di server dan **video tetap dapat
diputar langsung** dari aplikasi — penyajiannya mendukung permintaan sebagian
(*HTTP Range*), sehingga posisi putar dapat digeser tanpa mengunduh seluruh berkas.
Jenis yang tidak aman ditampilkan sebagai unduhan, bukan dibuka di halaman.

## 10. Bila terjadi kesalahan

Kegagalan pada jendela pengisian (simpan, unggah, buat folder) selalu dijawab dengan
**pesan yang menyebutkan sebabnya**, bukan sekadar kode kesalahan. Pesan yang sama juga
tercatat di *Pengaturan → Aktivitas Terakhir* dengan aksi `galat`, sehingga masih bisa
ditelusuri setelah jendela ditutup.

Nama folder Google Drive dipotong hingga 150 karakter (pada batas kata, ditandai `…`)
karena beberapa judul poin pada dokumen resmi lebih dari 270 karakter — terlalu panjang
untuk kolom nama di database dan menyulitkan saat dibaca di Google Drive.

## 11. Catatan keamanan

- Kata sandi disimpan dengan `password_hash()`; seluruh aksi POST diperiksa token CSRF.
- Semua jenis berkas diterima kecuali yang dapat dijalankan di server atau dieksekusi
  peramban (`BLOCKED_EXT`); pemeriksaan dilakukan di peramban dan diulang di server.
- Berkas lokal disajikan dengan `X-Content-Type-Options: nosniff`, dan hanya jenis aman
  (PDF, gambar, video, audio, teks) yang ditampilkan langsung — sisanya dipaksa diunduh.
- Folder `app/`, `db/`, dan `data/uploads/` diberi `.htaccess` penolak akses langsung;
  berkas lokal hanya dapat dibuka melalui aplikasi setelah login.
- Ganti `ADMIN_PASS`, `MYSQL_ROOT_PASSWORD`, dan `MYSQL_PASSWORD` sebelum dipakai di jaringan
  yang lebih luas, dan jangan mengekspos port phpMyAdmin ke internet.

---

## 12. Acuan tata naskah dan penyempurnaannya

Acuan utama modul Dokumen Internal adalah **Pedoman Tata Naskah RS Khusus THT SS Medika**.
Dari pedoman itu diambil apa adanya: tingkatan regulasi (1 Peraturan Direktur,
2 Surat Keputusan, 3 Pedoman, 4 Prosedur), rumus penomoran, singkatan bagian
(DIR, SDM, KEP, RM, FAR, KEU, MKT, UM), kewenangan penyiapan–pemeriksaan–pengesahan,
susunan batang tubuh SPO, sistematika baku pedoman, dan klasifikasi salinan
(Master, Terkendali, Tidak Terkendali, Absolute).

**RS Khusus THT SS Medika adalah rumah sakit swasta**, sehingga ketentuan tata naskah dinas
pemerintah tidak mengikat secara hukum. Pedoman internal dan standar pengendalian dokumen
akreditasi (STARKES) tetap menjadi acuan yang berlaku. Ketentuan pemerintah di bawah ini
**diadopsi sebagai praktik baik** untuk melengkapi hal yang belum diatur pedoman internal:

| Belum diatur pedoman internal | Acuan yang dipakai | Penyempurnaan pada aplikasi |
|---|---|---|
| Jenis huruf, ukuran, dan kertas | Permendagri 1/2023: Bookman Old Style 12 untuk naskah pengaturan & penetapan; Arial 12 untuk naskah penugasan, korespondensi, dan naskah khusus; kertas A4 HVS | Tiap jenis naskah membawa ketentuan hurufnya sendiri, dipakai pada tampilan cetak maupun berkas Word |
| Naskah korespondensi & naskah khusus | Permendagri 1/2023 dan Pergub DKI Jakarta 99/2021 | Ditambahkan surat tugas, nota dinas, surat dinas, undangan, berita acara, surat keterangan, pengumuman, surat pernyataan, dan notulen — dengan penomoran memakai bulan angka Romawi |
| Naskah arahan selain Peraturan & Keputusan | Instruksi dan Surat Edaran termasuk naskah arahan | Ditambahkan sebagai jenis tersendiri dengan konsiderans dan diktum |
| Penomoran diulang setiap tahun | Kelaziman tata naskah dinas | Nomor urut direset per tahun, per jenis, dan per bagian penerbit |
| Daftar induk & bukti distribusi salinan | Standar pengendalian dokumen akreditasi RS | Menu **Daftar Induk Dokumen** siap cetak + pencatatan penerima salinan terkendali |
| Peninjauan berkala naskah | Regulasi RS ditinjau paling lama setiap 3 tahun | Kolom rencana peninjauan; naskah yang lewat tanggalnya ditandai di halaman daftar |

Sumber rujukan:

1. Pedoman Tata Naskah RS Khusus THT SS Medika (dokumen internal — acuan utama).
2. [Permendagri No. 1 Tahun 2023](https://peraturan.bpk.go.id/Details/245536/permendagri-no-1-tahun-2023)
   tentang Tata Naskah Dinas di Lingkungan Kemendagri dan Pemerintah Daerah.
3. [Pergub DKI Jakarta No. 99 Tahun 2021](https://peraturan.bpk.go.id/Details/189336/pergub-prov-dki-jakarta-no-99-tahun-2021)
   tentang Tata Naskah Dinas.
4. [Pergub DKI Jakarta No. 123 Tahun 2016](https://peraturan.bpk.go.id/Details/326652/pergub-prov-dki-jakarta-no-123-tahun-2016)
   dan [No. 184 Tahun 2016](https://peraturan.bpk.go.id/Details/327179/pergub-prov-dki-jakarta-no-184-tahun-2016).
5. Standar Akreditasi Rumah Sakit (STARKES) — bab Tata Kelola Rumah Sakit,
   khususnya pengendalian dokumen regulasi.

Ringkasan yang sama tersedia di dalam aplikasi pada menu **Pedoman Tata Naskah**
dan dapat langsung dicetak.

### Menyesuaikan kop naskah

Buka **Pengaturan → Identitas Kop Naskah**. Isian di sana dipakai pada kop surat,
kaki tanda tangan, dan penomoran:

| Isian | Dipakai untuk |
|---|---|
| Nama rumah sakit | Baris besar pada kop dan baris jabatan di kaki naskah |
| Singkatan | Penanda `{rs}` pada nomor naskah, mis. `001/SK/DIR/**SSM**/2026` |
| Badan hukum / yayasan | Baris di atas nama rumah sakit (opsional) |
| Alamat, telepon, surel, situs | Baris kecil di bawah nama rumah sakit |
| Kota penetapan | Baris “Ditetapkan di …” pada kaki naskah |
| Nama direktur & jabatan | Nama penanda tangan bawaan tiap naskah baru |
| Alamat gambar logo | Logo pada kop; bila kosong, tempatnya tetap disediakan |
