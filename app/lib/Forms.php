<?php

/**
 * Definisi tabel-tabel profil rumah sakit (mengikuti dokumen Word resmi).
 *
 *  cols       : daftar kolom (kode kolom -> judul kolom di header tabel)
 *  fixed      : kolom yang isinya bawaan dokumen (tidak bisa diubah, mis. nama layanan)
 *  editable   : kolom yang diisi rumah sakit
 *  widths     : lebar kolom untuk tampilan (persen)
 *  upload     : true bila tiap baris punya tombol unggah dokumen + folder Drive
 *  align      : perataan teks per kolom
 */
class Forms
{
    public static function all(): array
    {
        return [
            'kompetensi' => [
                'title'  => 'Data Kompetensi Layanan',
                'note'   => 'Sumber : Data Layanan Kompetensi Rumah Sakit di RS Online',
                'cols'   => ['no' => 'No', 'layanan' => 'Jenis Layanan', 'kompetensi' => 'Kompetensi'],
                'fixed'  => ['no', 'layanan'],
                'editable' => ['kompetensi'],
                'widths' => ['no' => 8, 'layanan' => 62, 'kompetensi' => 30],
                'align'  => ['no' => 'center', 'kompetensi' => 'center'],
                'upload' => false,
            ],
            'tempat_tidur' => [
                'title'  => 'Kapasitas Tempat Tidur',
                'cols'   => ['no' => 'No', 'jenis' => 'Jenis Tempat Tidur', 'jumlah' => 'Jumlah'],
                'fixed'  => ['no', 'jenis'],
                'editable' => ['jumlah'],
                'widths' => ['no' => 8, 'jenis' => 62, 'jumlah' => 30],
                'align'  => ['no' => 'center', 'jumlah' => 'center'],
                'upload' => false,
            ],
            'perizinan' => [
                'title'  => 'Legalitas Kelengkapan Perizinan',
                'cols'   => ['no' => 'No', 'dokumen' => 'Dokumen', 'nomor' => 'Nomor', 'tahun' => 'Tahun'],
                'fixed'  => ['no', 'dokumen'],
                'editable' => ['nomor', 'tahun'],
                'widths' => ['no' => 5, 'dokumen' => 21, 'nomor' => 27, 'tahun' => 10],
                'align'  => ['no' => 'center', 'tahun' => 'center'],
                'upload' => true,
                'upload_width' => 37,
                'upload_title' => 'Berkas Pendukung',
            ],
            'sarana' => [
                'title'  => 'Sarana dan Prasarana',
                'cols'   => ['no' => 'No', 'uraian' => 'Uraian', 'isi' => 'Keterangan'],
                'fixed'  => ['no', 'uraian'],
                'editable' => ['isi'],
                'widths' => ['no' => 5, 'uraian' => 27, 'isi' => 30],
                'align'  => ['no' => 'center'],
                'upload' => true,
                'upload_width' => 38,
                'upload_title' => 'Berkas Pendukung',
            ],
            'kinerja' => [
                'title'  => 'Kinerja Rumah Sakit 2 (Dua) Tahun Terakhir',
                'cols'   => ['no' => 'No', 'kegiatan' => 'Data Kegiatan', 'thn1' => '2025', 'thn2' => 'Jan s.d Jul 2026'],
                'fixed'  => ['no', 'kegiatan'],
                'editable' => ['thn1', 'thn2'],
                'header_editable' => ['thn1', 'thn2'],
                'widths' => ['no' => 6, 'kegiatan' => 54, 'thn1' => 20, 'thn2' => 20],
                'align'  => ['no' => 'center', 'thn1' => 'center', 'thn2' => 'center'],
                'upload' => false,
            ],
            'penyakit_rj' => [
                'title'  => 'Jumlah 10 Penyakit Terbanyak Rawat Jalan',
                'cols'   => ['no' => 'No', 'diagnosa' => 'Diagnosa Penyakit (ICD-X)', 'jumlah' => 'Jumlah'],
                'fixed'  => ['no'],
                'editable' => ['diagnosa', 'jumlah'],
                'widths' => ['no' => 8, 'diagnosa' => 67, 'jumlah' => 25],
                'align'  => ['no' => 'center', 'jumlah' => 'center'],
                'upload' => false,
            ],
            'penyakit_ri' => [
                'title'  => 'Jumlah 10 Penyakit Terbanyak Rawat Inap',
                'cols'   => ['no' => 'No', 'diagnosa' => 'Diagnosa Penyakit (ICD-X)', 'jumlah' => 'Jumlah'],
                'fixed'  => ['no'],
                'editable' => ['diagnosa', 'jumlah'],
                'widths' => ['no' => 8, 'diagnosa' => 67, 'jumlah' => 25],
                'align'  => ['no' => 'center', 'jumlah' => 'center'],
                'upload' => false,
            ],
            'sdm_detail' => [
                'title'  => 'Sumber Daya Manusia / Ketenagakerjaan RS',
                'cols'   => [
                    'no' => 'No', 'jenis' => 'Jenis Layanan', 'standar' => 'Standar',
                    'jumlah' => 'Jumlah', 'purna' => 'Purna Waktu', 'paruh' => 'Paruh Waktu',
                ],
                'fixed'  => ['no', 'jenis', 'standar'],
                'editable' => ['jumlah', 'purna', 'paruh'],
                'widths' => ['no' => 5, 'jenis' => 43, 'standar' => 10, 'jumlah' => 12, 'purna' => 15, 'paruh' => 15],
                'align'  => ['no' => 'center', 'standar' => 'center', 'jumlah' => 'center', 'purna' => 'center', 'paruh' => 'center'],
                'upload' => false,
            ],
            'rekap_sdm' => [
                'title'  => 'Rekapitulasi SDM Rumah Sakit',
                'cols'   => ['no' => 'No', 'tenaga' => 'Tenaga', 'jumlah' => 'Jumlah', 'part' => 'Part Time', 'full' => 'Full Time'],
                'fixed'  => ['no', 'tenaga'],
                'editable' => ['jumlah', 'part', 'full'],
                'widths' => ['no' => 6, 'tenaga' => 40, 'jumlah' => 18, 'part' => 18, 'full' => 18],
                'align'  => ['no' => 'center', 'jumlah' => 'center', 'part' => 'center', 'full' => 'center'],
                'upload' => false,
            ],
        ];
    }

    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    /** Tabel yang tampil di halaman "Profil Rumah Sakit". */
    public static function profilTables(): array
    {
        return ['kompetensi', 'tempat_tidur', 'perizinan', 'sarana', 'kinerja', 'penyakit_rj', 'penyakit_ri'];
    }

    /** Tabel yang tampil di halaman "Ketenagaan". */
    public static function sdmTables(): array
    {
        return ['sdm_detail', 'rekap_sdm'];
    }
}
