-- =====================================================================
--  Modul Dokumen Internal — regulasi & naskah dinas rumah sakit
--  Mengikuti Pedoman Tata Naskah RS Khusus THT SS Medika
-- =====================================================================

CREATE TABLE IF NOT EXISTS `dokumen` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jenis`          VARCHAR(32)  NOT NULL,           -- kode jenis pada Naskah::jenis()
  `bagian`         VARCHAR(16)  NOT NULL DEFAULT 'DIR',
  `nomor`          VARCHAR(190) NOT NULL DEFAULT '',
  `nomor_urut`     INT          NOT NULL DEFAULT 0,
  `tahun`          SMALLINT     NOT NULL,
  `judul`          VARCHAR(500) NOT NULL,
  `ringkasan`      TEXT         NULL,
  `revisi`         INT          NOT NULL DEFAULT 0,
  `status`         ENUM('draft','diperiksa','disahkan','dicabut') NOT NULL DEFAULT 'draft',
  `klasifikasi`    ENUM('master','terkendali','tak_terkendali','absolute') NOT NULL DEFAULT 'master',
  `tanggal_terbit` DATE         NULL,
  `tanggal_berlaku` DATE        NULL,
  `tanggal_tinjau` DATE         NULL,               -- rencana peninjauan berikutnya
  `disiapkan_oleh` VARCHAR(190) NOT NULL DEFAULT '',
  `diperiksa_oleh` VARCHAR(190) NOT NULL DEFAULT '',
  `disahkan_oleh`  VARCHAR(190) NOT NULL DEFAULT '',
  `jabatan_pengesah` VARCHAR(190) NOT NULL DEFAULT 'Direktur',
  `dasar_dokumen`  VARCHAR(500) NOT NULL DEFAULT '', -- regulasi induk yang menjadi dasar
  `induk_id`       INT UNSIGNED NULL,                -- dokumen yang direvisi
  `dicabut_oleh_id` INT UNSIGNED NULL,               -- dokumen pengganti
  `created_by`     VARCHAR(64)  NOT NULL DEFAULT '',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_dokumen_jenis` (`jenis`,`tahun`),
  KEY `ix_dokumen_status` (`status`),
  KEY `ix_dokumen_induk` (`induk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dokumen_isi` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dokumen_id`  INT UNSIGNED NOT NULL,
  `blok`        VARCHAR(40)  NOT NULL,
  `judul`       VARCHAR(255) NOT NULL DEFAULT '',
  `isi`         MEDIUMTEXT   NULL,
  `urutan`      INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dokumen_blok` (`dokumen_id`,`blok`,`urutan`),
  CONSTRAINT `fk_isi_dokumen` FOREIGN KEY (`dokumen_id`) REFERENCES `dokumen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dokumen_riwayat` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dokumen_id` INT UNSIGNED NOT NULL,
  `aksi`       VARCHAR(40)  NOT NULL,
  `catatan`    TEXT         NULL,
  `oleh`       VARCHAR(64)  NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_riwayat_dokumen` (`dokumen_id`,`id`),
  CONSTRAINT `fk_riwayat_dokumen` FOREIGN KEY (`dokumen_id`) REFERENCES `dokumen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dokumen_distribusi` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dokumen_id` INT UNSIGNED NOT NULL,
  `unit`       VARCHAR(190) NOT NULL,
  `salinan_ke` VARCHAR(40)  NOT NULL DEFAULT '',
  `penerima`   VARCHAR(190) NOT NULL DEFAULT '',
  `tanggal`    DATE         NULL,
  PRIMARY KEY (`id`),
  KEY `ix_distribusi_dokumen` (`dokumen_id`),
  CONSTRAINT `fk_distribusi_dokumen` FOREIGN KEY (`dokumen_id`) REFERENCES `dokumen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Penyimpanan hasil scan / lampiran naskah di Google Drive
--    owner_type = 'root'    -> owner_key = 'root'
--    owner_type = 'jenis'   -> owner_key = kode jenis naskah
--    owner_type = 'dokumen' -> owner_key = id dokumen
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dokumen_folder` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_type` ENUM('root','jenis','dokumen') NOT NULL,
  `owner_key`  VARCHAR(190) NOT NULL,
  `nama`       VARCHAR(255) NOT NULL DEFAULT '',
  `drive_id`   VARCHAR(190) NULL,
  `drive_link` VARCHAR(500) NULL,
  `local_path` VARCHAR(500) NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dokumen_folder` (`owner_type`,`owner_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dokumen_berkas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dokumen_id`  INT UNSIGNED NOT NULL,
  `kategori`    ENUM('scan','lampiran') NOT NULL DEFAULT 'scan',
  `folder_id`   INT UNSIGNED NULL,
  `nama_file`   VARCHAR(255) NOT NULL,
  `mime`        VARCHAR(150) NOT NULL DEFAULT '',
  `ukuran`      BIGINT       NOT NULL DEFAULT 0,
  `drive_id`    VARCHAR(190) NULL,
  `drive_link`  VARCHAR(500) NULL,
  `local_path`  VARCHAR(500) NULL,
  `uploaded_by` VARCHAR(64)  NOT NULL DEFAULT '',
  `uploaded_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_dokumen_berkas` (`dokumen_id`,`kategori`),
  CONSTRAINT `fk_berkas_dokumen` FOREIGN KEY (`dokumen_id`) REFERENCES `dokumen`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_berkas_folder`  FOREIGN KEY (`folder_id`)  REFERENCES `dokumen_folder`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
