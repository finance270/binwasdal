<?php

/**
 * Master jenis naskah/regulasi rumah sakit.
 *
 * Sumber utama: "Pedoman Tata Naskah RS Khusus THT SS Medika" —
 * tingkatan regulasi, rumus penomoran, singkatan bagian, serta susunan
 * naskah (kepala, pembukaan, batang tubuh, kaki).
 *
 * Dilengkapi jenis naskah yang belum diatur di pedoman tersebut namun lazim
 * diperlukan rumah sakit (surat tugas, nota dinas, berita acara, dan lain-lain),
 * mengikuti kelaziman tata naskah dinas terbaru: huruf Bookman Old Style 12
 * untuk naskah pengaturan/penetapan dan Arial 12 untuk naskah penugasan,
 * korespondensi, serta naskah khusus; kertas A4 HVS.
 *
 * Penanda "tambahan" menyatakan jenis yang merupakan penyempurnaan, bukan
 * berasal dari pedoman internal yang berlaku sekarang.
 */
class Naskah
{
    /** Arketipe tata letak yang tersedia. */
    public const ARKETIPE = [
        'arahan'   => 'Naskah pengaturan/penetapan (konsiderans + diktum)',
        'spo'      => 'Standar Prosedur Operasional (kepala tabel + batang tubuh)',
        'pedoman'  => 'Pedoman/Panduan (bab bertingkat)',
        'surat'    => 'Surat (kop, nomor, tujuan, isi, kaki)',
        'formulir' => 'Formulir/blanko (tabel bebas)',
    ];

    /** Singkatan bagian sesuai pedoman (Bab VI). */
    public static function bagian(): array
    {
        return [
            'DIR' => 'Direktur',
            'SDM' => 'Sumber Daya Manusia',
            'KEP' => 'Keperawatan',
            'RM'  => 'Rekam Medis',
            'FAR' => 'Farmasi',
            'KEU' => 'Keuangan',
            'MKT' => 'Marketing',
            'UM'  => 'Umum',
            // penyempurnaan — unit yang lazim menerbitkan naskah
            'YAN' => 'Pelayanan Medis',
            'PMKP' => 'Mutu dan Keselamatan Pasien',
            'PPI' => 'Pencegahan dan Pengendalian Infeksi',
            'K3'  => 'Kesehatan dan Keselamatan Kerja',
            'IGD' => 'Gawat Darurat',
            'LAB' => 'Laboratorium',
            'RAD' => 'Radiologi',
            'GZ'  => 'Gizi',
        ];
    }

    /**
     * Daftar jenis naskah.
     *
     *  arketipe     : tata letak yang dipakai
     *  level        : tingkatan regulasi (1–4) sesuai pedoman; 0 = bukan regulasi
     *  format       : rumus penomoran. Penanda yang tersedia:
     *                 {urut} {urut3} {bagian} {rs} {bulan} {tahun}
     *  huruf        : jenis huruf naskah
     *  kertas       : ukuran kertas cetak
     *  blok         : bagian isi yang harus diisi
     *  sumber       : 'pedoman' bila diatur pedoman internal, 'tambahan' bila penyempurnaan
     */
    public static function jenis(): array
    {
        $ttdArahan = [
            'disiapkan' => 'Direktur RS',
            'diperiksa' => 'Pemilik',
            'disahkan'  => 'Direktur RS',
        ];
        $ttdUnit = [
            'disiapkan' => 'Unit',
            'diperiksa' => 'Direktur RS',
            'disahkan'  => 'Direktur RS',
        ];

        return [
            // ---------------------------------------------- regulasi (pedoman)
            'perdir' => [
                'nama' => 'Peraturan Direktur',
                'singkat' => 'PERDIR',
                'arketipe' => 'arahan',
                'level' => 1,
                'format' => '{urut3}/PERDIR/{rs}/{tahun}',
                'huruf' => 'Bookman Old Style',
                'kertas' => 'A4',
                'sumber' => 'pedoman',
                'pengesahan' => $ttdArahan,
                'blok' => [
                    ['kode' => 'menimbang', 'judul' => 'Menimbang', 'tipe' => 'daftar',
                     'petunjuk' => 'Pokok pikiran yang melatarbelakangi. Tiap butir diawali kata "bahwa" dan diakhiri titik koma (;).'],
                    ['kode' => 'mengingat', 'judul' => 'Mengingat', 'tipe' => 'daftar',
                     'petunjuk' => 'Dasar hukum yang tingkatannya sederajat atau lebih tinggi, diakhiri titik koma (;).'],
                    ['kode' => 'menetapkan', 'judul' => 'Menetapkan', 'tipe' => 'teks',
                     'petunjuk' => 'Nama peraturan sesuai judul, huruf kapital.'],
                    ['kode' => 'diktum', 'judul' => 'Diktum', 'tipe' => 'diktum',
                     'petunjuk' => 'Substansi peraturan: KESATU, KEDUA, dan seterusnya.'],
                    ['kode' => 'penutup', 'judul' => 'Ketentuan Penutup', 'tipe' => 'teks',
                     'petunjuk' => 'Saat berlaku, pencabutan ketentuan lama, dan sejenisnya.'],
                ],
            ],
            'sk' => [
                'nama' => 'Surat Keputusan Direktur',
                'singkat' => 'SK',
                'arketipe' => 'arahan',
                'level' => 2,
                'format' => '{urut3}/SK/DIR/{rs}/{tahun}',
                'huruf' => 'Bookman Old Style',
                'kertas' => 'A4',
                'sumber' => 'pedoman',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'menimbang', 'judul' => 'Menimbang', 'tipe' => 'daftar'],
                    ['kode' => 'mengingat', 'judul' => 'Mengingat', 'tipe' => 'daftar'],
                    ['kode' => 'menetapkan', 'judul' => 'Menetapkan', 'tipe' => 'teks'],
                    ['kode' => 'diktum', 'judul' => 'Diktum', 'tipe' => 'diktum'],
                    ['kode' => 'penutup', 'judul' => 'Ketentuan Penutup', 'tipe' => 'teks'],
                ],
            ],
            'pedoman' => [
                'nama' => 'Pedoman / Panduan',
                'singkat' => 'PED',
                'arketipe' => 'pedoman',
                'level' => 3,
                'format' => '{urut3}/PED/{bagian}/{tahun}',
                'huruf' => 'Bookman Old Style',
                'kertas' => 'A4',
                'sumber' => 'pedoman',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'bab', 'judul' => 'Bab', 'tipe' => 'bab',
                     'petunjuk' => 'Susun per bab. Sistematika baku tersedia lewat tombol "Isi kerangka baku".'],
                ],
                'kerangka' => [
                    'Pedoman Pelayanan Unit Kerja' => [
                        'BAB I PENDAHULUAN', 'BAB II STANDAR KETENAGAAN', 'BAB III STANDAR FASILITAS',
                        'BAB IV TATA LAKSANA PELAYANAN', 'BAB V LOGISTIK', 'BAB VI KESELAMATAN PASIEN',
                        'BAB VII KESELAMATAN KERJA', 'BAB VIII PENGENDALIAN MUTU', 'BAB IX PENUTUP',
                    ],
                    'Pedoman Pengorganisasian Unit Kerja' => [
                        'BAB I PENDAHULUAN', 'BAB II GAMBARAN UMUM RS',
                        'BAB III VISI, MISI, FALSAFAH, NILAI DAN TUJUAN RS', 'BAB IV STRUKTUR ORGANISASI RS',
                        'BAB V STRUKTUR ORGANISASI UNIT KERJA', 'BAB VI URAIAN JABATAN',
                        'BAB VII TATA HUBUNGAN KERJA', 'BAB VIII POLA KETENAGAAN DAN KUALIFIKASI PERSONIL',
                        'BAB IX KEGIATAN ORIENTASI', 'BAB X PERTEMUAN/RAPAT', 'BAB XI PELAPORAN',
                    ],
                ],
            ],
            'spo' => [
                'nama' => 'Standar Prosedur Operasional',
                'singkat' => 'SPO',
                'arketipe' => 'spo',
                'level' => 4,
                'format' => '{urut3}/SPO/{bagian}/{tahun}',
                'huruf' => 'Bookman Old Style',
                'kertas' => 'A4',
                'sumber' => 'pedoman',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'pengertian', 'judul' => 'Pengertian', 'tipe' => 'teks'],
                    ['kode' => 'tujuan', 'judul' => 'Tujuan', 'tipe' => 'teks'],
                    ['kode' => 'kebijakan', 'judul' => 'Kebijakan', 'tipe' => 'teks',
                     'petunjuk' => 'Sebutkan Peraturan/SK yang menjadi dasar, mis. "Berdasarkan PERDIR No. …".'],
                    ['kode' => 'prosedur', 'judul' => 'Prosedur', 'tipe' => 'daftar'],
                    ['kode' => 'unit', 'judul' => 'Unit Terkait', 'tipe' => 'daftar'],
                ],
            ],
            'ik' => [
                'nama' => 'Instruksi Kerja',
                'singkat' => 'IK',
                'arketipe' => 'spo',
                'level' => 4,
                'format' => '{urut3}/IK/{bagian}/{tahun}',
                'huruf' => 'Bookman Old Style',
                'kertas' => 'A4',
                'sumber' => 'pedoman',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'tujuan', 'judul' => 'Tujuan', 'tipe' => 'teks'],
                    ['kode' => 'ruang_lingkup', 'judul' => 'Ruang Lingkup', 'tipe' => 'teks'],
                    ['kode' => 'pelaksana', 'judul' => 'Pelaksana', 'tipe' => 'teks'],
                    ['kode' => 'referensi', 'judul' => 'Referensi', 'tipe' => 'daftar'],
                    ['kode' => 'langkah', 'judul' => 'Langkah Kerja', 'tipe' => 'daftar'],
                    ['kode' => 'lampiran', 'judul' => 'Lampiran', 'tipe' => 'teks'],
                ],
            ],
            'formulir' => [
                'nama' => 'Formulir / Blanko',
                'singkat' => 'F',
                'arketipe' => 'formulir',
                'level' => 0,
                'format' => 'F: {urut3}/{rs}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'pedoman',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'pengantar', 'judul' => 'Keterangan Pembuka', 'tipe' => 'teks'],
                    ['kode' => 'isian', 'judul' => 'Isian Identitas', 'tipe' => 'daftar',
                     'petunjuk' => 'Satu baris satu isian, mis. "Nama Petugas", "Tanggal".'],
                    ['kode' => 'tabel', 'judul' => 'Tabel Formulir', 'tipe' => 'tabel',
                     'petunjuk' => 'Baris pertama menjadi judul kolom. Pisahkan kolom dengan tanda | (pipa).'],
                    ['kode' => 'catatan', 'judul' => 'Catatan', 'tipe' => 'teks'],
                ],
            ],

            // ------------------------------------- penyempurnaan (belum diatur)
            'instruksi' => [
                'nama' => 'Instruksi Direktur',
                'singkat' => 'INS',
                'arketipe' => 'arahan',
                'level' => 2,
                'format' => '{urut3}/INS/DIR/{rs}/{tahun}',
                'huruf' => 'Bookman Old Style',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdArahan,
                'blok' => [
                    ['kode' => 'menimbang', 'judul' => 'Menimbang', 'tipe' => 'daftar'],
                    ['kode' => 'mengingat', 'judul' => 'Mengingat', 'tipe' => 'daftar'],
                    ['kode' => 'diktum', 'judul' => 'Instruksi', 'tipe' => 'diktum'],
                ],
            ],
            'edaran' => [
                'nama' => 'Surat Edaran Direktur',
                'singkat' => 'SE',
                'arketipe' => 'arahan',
                'level' => 2,
                'format' => '{urut3}/SE/DIR/{rs}/{tahun}',
                'huruf' => 'Bookman Old Style',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdArahan,
                'blok' => [
                    ['kode' => 'menimbang', 'judul' => 'Latar Belakang', 'tipe' => 'daftar'],
                    ['kode' => 'mengingat', 'judul' => 'Dasar', 'tipe' => 'daftar'],
                    ['kode' => 'diktum', 'judul' => 'Isi Edaran', 'tipe' => 'diktum'],
                ],
            ],
            'surat_tugas' => [
                'nama' => 'Surat Tugas',
                'singkat' => 'S-TGS',
                'arketipe' => 'surat',
                'level' => 0,
                'format' => '{urut3}/S-TGS/{rs}/{bulan}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'dasar', 'judul' => 'Dasar', 'tipe' => 'daftar'],
                    ['kode' => 'kepada', 'judul' => 'Kepada', 'tipe' => 'daftar',
                     'petunjuk' => 'Nama, jabatan, dan NIP/NIK yang ditugaskan.'],
                    ['kode' => 'untuk', 'judul' => 'Untuk', 'tipe' => 'daftar'],
                    ['kode' => 'penutup', 'judul' => 'Penutup', 'tipe' => 'teks'],
                ],
            ],
            'nota_dinas' => [
                'nama' => 'Nota Dinas',
                'singkat' => 'ND',
                'arketipe' => 'surat',
                'level' => 0,
                'format' => '{urut3}/ND/{bagian}/{bulan}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'kepada', 'judul' => 'Kepada Yth.', 'tipe' => 'teks'],
                    ['kode' => 'dari', 'judul' => 'Dari', 'tipe' => 'teks'],
                    ['kode' => 'perihal', 'judul' => 'Perihal', 'tipe' => 'teks'],
                    ['kode' => 'isi', 'judul' => 'Isi', 'tipe' => 'teks'],
                ],
            ],
            'surat_biasa' => [
                'nama' => 'Surat Dinas / Surat Biasa',
                'singkat' => 'SB',
                'arketipe' => 'surat',
                'level' => 0,
                'format' => '{urut3}/SB/{bagian}/{bulan}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'kepada', 'judul' => 'Kepada Yth.', 'tipe' => 'teks'],
                    ['kode' => 'perihal', 'judul' => 'Perihal', 'tipe' => 'teks'],
                    ['kode' => 'lampiran_ket', 'judul' => 'Lampiran', 'tipe' => 'teks'],
                    ['kode' => 'pembuka', 'judul' => 'Kalimat Pembuka', 'tipe' => 'teks'],
                    ['kode' => 'isi', 'judul' => 'Isi Surat', 'tipe' => 'teks'],
                    ['kode' => 'penutup', 'judul' => 'Kalimat Penutup', 'tipe' => 'teks'],
                ],
            ],
            'undangan' => [
                'nama' => 'Surat Undangan',
                'singkat' => 'UND',
                'arketipe' => 'surat',
                'level' => 0,
                'format' => '{urut3}/UND/{bagian}/{bulan}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'kepada', 'judul' => 'Kepada Yth.', 'tipe' => 'daftar'],
                    ['kode' => 'perihal', 'judul' => 'Perihal', 'tipe' => 'teks'],
                    ['kode' => 'pembuka', 'judul' => 'Kalimat Pembuka', 'tipe' => 'teks'],
                    ['kode' => 'acara', 'judul' => 'Hari / Tanggal / Waktu / Tempat / Acara', 'tipe' => 'daftar'],
                    ['kode' => 'penutup', 'judul' => 'Kalimat Penutup', 'tipe' => 'teks'],
                ],
            ],
            'berita_acara' => [
                'nama' => 'Berita Acara',
                'singkat' => 'BA',
                'arketipe' => 'surat',
                'level' => 0,
                'format' => '{urut3}/BA/{bagian}/{bulan}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'pembuka', 'judul' => 'Kalimat Pembuka', 'tipe' => 'teks',
                     'petunjuk' => 'Mis. "Pada hari ini, …, kami yang bertanda tangan di bawah ini:"'],
                    ['kode' => 'pihak', 'judul' => 'Para Pihak', 'tipe' => 'daftar'],
                    ['kode' => 'isi', 'judul' => 'Isi Berita Acara', 'tipe' => 'daftar'],
                    ['kode' => 'penutup', 'judul' => 'Kalimat Penutup', 'tipe' => 'teks'],
                ],
            ],
            'surat_keterangan' => [
                'nama' => 'Surat Keterangan',
                'singkat' => 'S-KTR',
                'arketipe' => 'surat',
                'level' => 0,
                'format' => '{urut3}/S-KTR/{bagian}/{bulan}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'pembuka', 'judul' => 'Yang Menerangkan', 'tipe' => 'daftar'],
                    ['kode' => 'isi', 'judul' => 'Menerangkan Bahwa', 'tipe' => 'teks'],
                    ['kode' => 'penutup', 'judul' => 'Kalimat Penutup', 'tipe' => 'teks'],
                ],
            ],
            'pengumuman' => [
                'nama' => 'Pengumuman',
                'singkat' => 'PUM',
                'arketipe' => 'surat',
                'level' => 0,
                'format' => '{urut3}/PUM/{rs}/{bulan}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'perihal', 'judul' => 'Tentang', 'tipe' => 'teks'],
                    ['kode' => 'isi', 'judul' => 'Isi Pengumuman', 'tipe' => 'daftar'],
                    ['kode' => 'penutup', 'judul' => 'Penutup', 'tipe' => 'teks'],
                ],
            ],
            'surat_pernyataan' => [
                'nama' => 'Surat Pernyataan',
                'singkat' => 'S-PT',
                'arketipe' => 'surat',
                'level' => 0,
                'format' => '{urut3}/S-PT/{bagian}/{bulan}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'pembuka', 'judul' => 'Yang Bertanda Tangan', 'tipe' => 'daftar'],
                    ['kode' => 'isi', 'judul' => 'Menyatakan Bahwa', 'tipe' => 'daftar'],
                    ['kode' => 'penutup', 'judul' => 'Kalimat Penutup', 'tipe' => 'teks'],
                ],
            ],
            'notulen' => [
                'nama' => 'Notulen Rapat',
                'singkat' => 'NOT',
                'arketipe' => 'surat',
                'level' => 0,
                'format' => '{urut3}/NOT/{bagian}/{bulan}/{tahun}',
                'huruf' => 'Arial',
                'kertas' => 'A4',
                'sumber' => 'tambahan',
                'pengesahan' => $ttdUnit,
                'blok' => [
                    ['kode' => 'acara', 'judul' => 'Hari / Tanggal / Waktu / Tempat / Pimpinan', 'tipe' => 'daftar'],
                    ['kode' => 'hadir', 'judul' => 'Peserta Hadir', 'tipe' => 'daftar'],
                    ['kode' => 'tabel', 'judul' => 'Pokok Bahasan / Uraian / Keputusan', 'tipe' => 'tabel'],
                    ['kode' => 'penutup', 'judul' => 'Penutup', 'tipe' => 'teks'],
                ],
            ],
        ];
    }

    public static function get(string $kode): ?array
    {
        $j = self::jenis()[$kode] ?? null;
        if ($j) {
            $j['kode'] = $kode;
        }
        return $j;
    }

    /** Jenis dikelompokkan untuk tampilan daftar. */
    public static function kelompok(): array
    {
        return [
            'Regulasi (diatur Pedoman Tata Naskah)' => ['perdir', 'sk', 'pedoman', 'spo', 'ik', 'formulir'],
            'Naskah Arahan Lain' => ['instruksi', 'edaran'],
            'Naskah Penugasan & Korespondensi' => ['surat_tugas', 'nota_dinas', 'surat_biasa', 'undangan'],
            'Naskah Khusus' => ['berita_acara', 'surat_keterangan', 'pengumuman', 'surat_pernyataan', 'notulen'],
        ];
    }

    public static function statusLabel(string $s): string
    {
        return [
            'draft'     => 'Draf',
            'diperiksa' => 'Diperiksa',
            'disahkan'  => 'Disahkan / Berlaku',
            'dicabut'   => 'Tidak Berlaku (Absolute)',
        ][$s] ?? $s;
    }

    public static function statusOptions(): array
    {
        return ['draft' => 'Draf', 'diperiksa' => 'Diperiksa', 'disahkan' => 'Disahkan / Berlaku', 'dicabut' => 'Tidak Berlaku'];
    }

    /** Klasifikasi salinan sesuai Bab IX pedoman. */
    public static function klasifikasiOptions(): array
    {
        return [
            'master'       => 'Master (naskah asli)',
            'terkendali'   => 'Terkendali (Controlled Copy)',
            'tak_terkendali' => 'Tidak Terkendali',
            'absolute'     => 'Tidak Berlaku (Absolute)',
        ];
    }

    /** Bulan dalam angka romawi, untuk penomoran surat. */
    public static function bulanRomawi(int $bulan): string
    {
        return ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$bulan] ?? '';
    }
}
