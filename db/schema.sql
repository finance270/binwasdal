-- =====================================================================
--  Self Assessment Pembinaan & Pengawasan Rumah Sakit (Binwasdal)
--  MariaDB 11.4  |  utf8mb4
-- =====================================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(64)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nama`          VARCHAR(150) NOT NULL DEFAULT '',
  `jabatan`       VARCHAR(150) NOT NULL DEFAULT '',
  `role`          ENUM('admin','editor','viewer') NOT NULL DEFAULT 'editor',
  `aktif`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(64) NOT NULL,
  `v` MEDIUMTEXT  NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Master struktur dokumen (hasil ekstraksi dokumen Word resmi)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sections` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`     VARCHAR(64)  NOT NULL,
  `title`    VARCHAR(255) NOT NULL,
  `subtitle` VARCHAR(255) NOT NULL DEFAULT '',
  `ordering` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sections_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id` INT UNSIGNED NOT NULL,
  `parent_id`  INT UNSIGNED NULL,
  `level`      TINYINT      NOT NULL DEFAULT 0,   -- 0 = poin utama
  `label`      VARCHAR(24)  NOT NULL DEFAULT '',  -- "1", "a.", "1)", "(a)"
  `code`       VARCHAR(64)  NOT NULL DEFAULT '',  -- jalur penomoran, mis. 3.b.2
  `title`      TEXT         NOT NULL,
  `ordering`   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_items_section` (`section_id`,`ordering`),
  KEY `ix_items_parent` (`parent_id`),
  CONSTRAINT `fk_items_section` FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_parent`  FOREIGN KEY (`parent_id`)  REFERENCES `items`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Baris tabel profil (kompetensi layanan, tempat tidur, perizinan, dst.)
CREATE TABLE IF NOT EXISTS `form_rows` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_code` VARCHAR(40)  NOT NULL,
  `row_no`     INT          NOT NULL,
  `data`       TEXT         NOT NULL,   -- JSON label bawaan dari dokumen Word
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_form_rows` (`table_code`,`row_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Data pengisian (per periode penilaian)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assessments` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_rs`           VARCHAR(190) NOT NULL,
  `tahun`             SMALLINT     NOT NULL,
  `wilayah`           VARCHAR(190) NOT NULL DEFAULT 'Wilayah Kota Administrasi Jakarta Pusat',
  `status`            ENUM('draft','final') NOT NULL DEFAULT 'draft',
  `drive_folder_id`   VARCHAR(190) NULL,
  `drive_folder_link` VARCHAR(500) NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assessment` (`nama_rs`,`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data dasar RS (key-value supaya fleksibel)
CREATE TABLE IF NOT EXISTS `profil_values` (
  `assessment_id` INT UNSIGNED NOT NULL,
  `k`             VARCHAR(64)  NOT NULL,
  `label`         VARCHAR(190) NOT NULL DEFAULT '',
  `v`             TEXT         NULL,
  `ordering`      INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`assessment_id`,`k`),
  CONSTRAINT `fk_profil_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Isian sel tabel profil
CREATE TABLE IF NOT EXISTS `form_values` (
  `assessment_id` INT UNSIGNED NOT NULL,
  `table_code`    VARCHAR(40)  NOT NULL,
  `row_no`        INT          NOT NULL,
  `col_code`      VARCHAR(40)  NOT NULL,
  `v`             TEXT         NULL,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`assessment_id`,`table_code`,`row_no`,`col_code`),
  CONSTRAINT `fk_formvalues_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jawaban tiap poin self assessment
CREATE TABLE IF NOT EXISTS `answers` (
  `assessment_id` INT UNSIGNED NOT NULL,
  `item_id`       INT UNSIGNED NOT NULL,
  `status`        ENUM('','ada','sebagian','tidak_ada','na') NOT NULL DEFAULT '',
  `keterangan`    MEDIUMTEXT   NULL,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`    VARCHAR(64)  NOT NULL DEFAULT '',
  PRIMARY KEY (`assessment_id`,`item_id`),
  KEY `ix_answers_item` (`item_id`),
  CONSTRAINT `fk_answers_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_answers_item`       FOREIGN KEY (`item_id`)       REFERENCES `items`(`id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Google Drive: folder per poin + berkas
--   owner_type = 'item'  -> owner_key = id item
--   owner_type = 'row'   -> owner_key = "<table_code>:<row_no>"
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `drive_folders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_id` INT UNSIGNED NOT NULL,
  `owner_type`    ENUM('item','row','section','root') NOT NULL,
  `owner_key`     VARCHAR(190) NOT NULL,
  `nama`          VARCHAR(255) NOT NULL DEFAULT '',
  `drive_id`      VARCHAR(190) NULL,
  `drive_link`    VARCHAR(500) NULL,
  `local_path`    VARCHAR(500) NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_drive_folder` (`assessment_id`,`owner_type`,`owner_key`),
  CONSTRAINT `fk_folder_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documents` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_id` INT UNSIGNED NOT NULL,
  `owner_type`    ENUM('item','row') NOT NULL,
  `owner_key`     VARCHAR(190) NOT NULL,
  `folder_id`     INT UNSIGNED NULL,
  `nama_file`     VARCHAR(255) NOT NULL,
  `mime`          VARCHAR(150) NOT NULL DEFAULT '',
  `ukuran`        BIGINT       NOT NULL DEFAULT 0,
  `drive_id`      VARCHAR(190) NULL,
  `drive_link`    VARCHAR(500) NULL,
  `local_path`    VARCHAR(500) NULL,
  `uploaded_by`   VARCHAR(64)  NOT NULL DEFAULT '',
  `uploaded_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_doc_owner` (`assessment_id`,`owner_type`,`owner_key`),
  CONSTRAINT `fk_doc_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doc_folder`     FOREIGN KEY (`folder_id`)     REFERENCES `drive_folders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(64)  NOT NULL DEFAULT '',
  `aksi`       VARCHAR(64)  NOT NULL DEFAULT '',
  `keterangan` TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
