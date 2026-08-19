<?php
$judul = 'Pedoman Tata Naskah — ' . $CFG['app']['name'];
$jenis = Naskah::jenis();
$ident = RenderNaskah::identitas();
require __DIR__ . '/partials/head.php';
?>

<div class="aksi-atas no-print">
    <div class="judul">Acuan Tata Naskah yang Dipakai Aplikasi</div>
    <a class="btn" href="?p=dokumen">← Daftar Dokumen</a>
    <button class="btn utama" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
</div>

<div class="kertas">
    <div class="judul-dok">
        Acuan Penyusunan Naskah Dinas &amp; Regulasi<br>
        <span class="sub"><?= e($ident['nama']) ?></span>
    </div>
    <hr class="garis-judul">

    <div class="bab">A. Tingkatan Regulasi dan Rumus Penomoran</div>
    <p>
        Susunan regulasi berikut diambil dari <b>Pedoman Tata Naskah</b> rumah sakit. Nomor naskah
        dibentuk otomatis oleh aplikasi memakai rumus di kolom terakhir dan dihitung ulang mulai
        angka 001 pada setiap awal tahun. Untuk jenis yang memuat penanda <code>{bagian}</code>,
        penghitungan dilakukan terpisah per bagian penerbit.
    </p>
    <table class="w">
        <thead>
            <tr>
                <th style="width:10%">Tingkat</th>
                <th style="width:23%">Jenis Naskah</th>
                <th style="width:25%">Rumus Penomoran</th>
                <th style="width:22%">Contoh</th>
                <th style="width:20%">Sumber</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($jenis as $kode => $j): ?>
            <tr>
                <td class="tengah"><?= $j['level'] > 0 ? (int) $j['level'] : '—' ?></td>
                <td><?= e($j['nama']) ?></td>
                <td><code><?= e($j['format']) ?></code></td>
                <td><?= e(Dokumen::susunNomor($kode, 'KEP', (int) date('Y'), 1)) ?></td>
                <td><?= $j['sumber'] === 'pedoman' ? 'Pedoman Tata Naskah RS' : 'Penyempurnaan' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="bab">B. Kewenangan Penyiapan, Pemeriksaan, dan Pengesahan</div>
    <table class="w">
        <thead>
            <tr>
                <th style="width:34%">Jenis Naskah</th>
                <th style="width:22%">Disiapkan</th>
                <th style="width:22%">Diperiksa</th>
                <th style="width:22%">Disahkan</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($jenis as $j): ?>
            <tr>
                <td><?= e($j['nama']) ?></td>
                <td><?= e($j['pengesahan']['disiapkan']) ?></td>
                <td><?= e($j['pengesahan']['diperiksa']) ?></td>
                <td><?= e($j['pengesahan']['disahkan']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="bab">C. Pengendalian dan Klasifikasi Salinan</div>
    <table class="w">
        <thead><tr><th style="width:30%">Klasifikasi</th><th>Arti dan Perlakuan</th></tr></thead>
        <tbody>
            <tr>
                <td>Master (naskah asli)</td>
                <td>Naskah asli bertanda tangan dan bercap, disimpan oleh pengendali dokumen. Hasil pemindaiannya diunggah pada menu naskah yang bersangkutan.</td>
            </tr>
            <tr>
                <td>Terkendali (<i>Controlled Copy</i>)</td>
                <td>Salinan resmi yang dibagikan ke unit kerja dan wajib ditarik saat direvisi. Penerimanya dicatat pada tabel distribusi tiap naskah.</td>
            </tr>
            <tr>
                <td>Tidak Terkendali</td>
                <td>Salinan untuk keperluan di luar rumah sakit; tidak diperbarui bila naskah direvisi. Dicetak dengan cap <b>TIDAK TERKENDALI</b>.</td>
            </tr>
            <tr>
                <td>Tidak Berlaku (<i>Absolute</i>)</td>
                <td>Naskah yang sudah dicabut atau digantikan revisi baru. Tidak boleh dipakai sebagai acuan kerja dan dicetak dengan cap <b>TIDAK BERLAKU</b>.</td>
            </tr>
        </tbody>
    </table>
    <p>
        Aplikasi menandai naskah yang belum disahkan dengan cap <b>DRAF</b> atau
        <b>BELUM DISAHKAN</b>, sehingga cetakan sementara tidak keliru dipakai sebagai acuan kerja.
    </p>

    <div class="bab">D. Sistematika Baku</div>
    <p><b>1. Standar Prosedur Operasional</b> — Pengertian, Tujuan, Kebijakan, Prosedur, Unit Terkait.</p>
    <p><b>2. Naskah pengaturan/penetapan</b> — kepala (jenis naskah, nomor, TENTANG, judul, jabatan),
       pembukaan (Menimbang, Mengingat), MEMUTUSKAN, Menetapkan, batang tubuh berupa diktum
       KESATU/KEDUA/…, lalu kaki (tempat, tanggal, jabatan, tanda tangan, nama).</p>
    <?php foreach (($jenis['pedoman']['kerangka'] ?? []) as $namaKerangka => $bab): ?>
        <p><b>3. <?= e($namaKerangka) ?></b> — <?= e(implode('; ', $bab)) ?>.</p>
    <?php endforeach; ?>

    <div class="bab">E. Perbandingan dengan Ketentuan Tata Naskah Terbaru</div>
    <p>
        RS Khusus THT SS Medika adalah rumah sakit swasta, sehingga ketentuan tata naskah dinas
        pemerintah <i>tidak mengikat</i> secara hukum. Pedoman internal rumah sakit dan standar
        pengendalian dokumen akreditasi (STARKES) tetap menjadi acuan yang berlaku. Ketentuan
        pemerintah berikut diadopsi sebagai praktik baik untuk melengkapi hal-hal yang belum
        diatur pedoman internal.
    </p>
    <table class="w">
        <thead>
            <tr>
                <th style="width:30%">Belum diatur pedoman internal</th>
                <th style="width:34%">Acuan yang dipakai</th>
                <th style="width:36%">Penyempurnaan pada aplikasi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Jenis huruf, ukuran, dan kertas naskah</td>
                <td>Permendagri No. 1 Tahun 2023: naskah pengaturan dan penetapan memakai
                    Bookman Old Style 12; naskah penugasan, korespondensi, dan naskah khusus
                    memakai Arial 12; kertas A4 HVS.</td>
                <td>Tiap jenis naskah membawa ketentuan hurufnya sendiri, dipakai pada tampilan
                    cetak maupun berkas Word yang diunduh.</td>
            </tr>
            <tr>
                <td>Naskah korespondensi dan naskah khusus</td>
                <td>Permendagri No. 1 Tahun 2023 dan Pergub DKI Jakarta No. 99 Tahun 2021
                    mengenal surat tugas, nota dinas, surat dinas, undangan, berita acara,
                    surat keterangan, pengumuman, surat pernyataan, dan notulen.</td>
                <td>Kesembilan jenis tersebut ditambahkan dengan tata letak surat dan rumus
                    penomoran memakai bulan angka Romawi.</td>
            </tr>
            <tr>
                <td>Naskah arahan selain Peraturan dan Keputusan</td>
                <td>Instruksi dan Surat Edaran termasuk naskah arahan pada ketentuan tata
                    naskah dinas.</td>
                <td>Ditambahkan sebagai jenis tersendiri dengan susunan konsiderans dan diktum.</td>
            </tr>
            <tr>
                <td>Penomoran diulang setiap tahun</td>
                <td>Kelaziman tata naskah dinas: nomor urut dimulai kembali dari 001 pada
                    setiap tahun takwim.</td>
                <td>Nomor urut otomatis direset per tahun, per jenis, dan per bagian penerbit
                    bagi jenis yang memakai kode bagian.</td>
            </tr>
            <tr>
                <td>Daftar induk dan bukti distribusi salinan</td>
                <td>Standar pengendalian dokumen akreditasi rumah sakit.</td>
                <td>Tersedia menu <b>Daftar Induk Dokumen</b> siap cetak serta pencatatan
                    penerima salinan terkendali pada tiap naskah.</td>
            </tr>
            <tr>
                <td>Peninjauan berkala naskah</td>
                <td>Regulasi rumah sakit ditinjau ulang paling lama setiap 3 tahun.</td>
                <td>Tersedia kolom rencana peninjauan; naskah yang lewat tanggalnya ditandai
                    pada halaman daftar dokumen.</td>
            </tr>
        </tbody>
    </table>

    <div class="bab">F. Sumber Rujukan</div>
    <ol>
        <li>Pedoman Tata Naskah RS Khusus THT SS Medika (dokumen internal, acuan utama).</li>
        <li>Peraturan Menteri Dalam Negeri Nomor 1 Tahun 2023 tentang Tata Naskah Dinas
            di Lingkungan Kementerian Dalam Negeri dan Pemerintah Daerah.</li>
        <li>Peraturan Gubernur Provinsi DKI Jakarta Nomor 99 Tahun 2021 tentang Tata Naskah Dinas.</li>
        <li>Peraturan Gubernur Provinsi DKI Jakarta Nomor 123 Tahun 2016 dan Nomor 184 Tahun 2016
            tentang penyelenggaraan naskah dinas di lingkungan Pemerintah Provinsi DKI Jakarta.</li>
        <li>Standar Akreditasi Rumah Sakit (STARKES) — bab Tata Kelola Rumah Sakit,
            khususnya pengendalian dokumen regulasi.</li>
    </ol>
    <p style="font-size:9.5pt;font-style:italic">
        Butir 2–4 mengikat rumah sakit milik pemerintah/daerah. Bagi rumah sakit swasta,
        ketentuan tersebut diadopsi secara sukarela sebagai praktik baik dan tidak
        menggantikan pedoman internal yang telah disahkan Direktur.
    </p>
</div>

<?php require __DIR__ . '/partials/foot_dokumen.php'; ?>
