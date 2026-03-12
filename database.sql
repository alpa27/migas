-- =============================================
-- DATABASE: db_kinerja_migas
-- Sistem Monitoring Indikator Kinerja Ditjen Migas
-- =============================================

CREATE DATABASE IF NOT EXISTS db_kinerja_migas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_kinerja_migas;

-- ---------------------------------------------
-- Tabel: kelompok
-- ---------------------------------------------
CREATE TABLE kelompok (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(10) NOT NULL UNIQUE,
    nama VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------
-- Tabel: users
-- ---------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    kode_kelompok VARCHAR(10) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kode_kelompok) REFERENCES kelompok(kode) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ---------------------------------------------
-- Tabel: tahun_anggaran
-- ---------------------------------------------
CREATE TABLE tahun_anggaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tahun YEAR NOT NULL UNIQUE,
    is_aktif TINYINT(1) DEFAULT 0,
    tw1_open TINYINT(1) DEFAULT 1,
    tw2_open TINYINT(1) DEFAULT 1,
    tw3_open TINYINT(1) DEFAULT 1,
    tw4_open TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------
-- Tabel: indikator
-- ---------------------------------------------
CREATE TABLE indikator (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tahun_id INT NOT NULL,
    nama_indikator TEXT NOT NULL,
    leveling ENUM('IKSP','IKSK-2','IKSK-3','IKSK-4') NOT NULL,
    satuan VARCHAR(100) NOT NULL,
    pic VARCHAR(10) NOT NULL,
    target_tw1 DECIMAL(15,4) NULL,
    target_tw2 DECIMAL(15,4) NULL,
    target_tw3 DECIMAL(15,4) NULL,
    target_tw4 DECIMAL(15,4) NULL,
    urutan INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tahun_id) REFERENCES tahun_anggaran(id) ON DELETE CASCADE
);

-- ---------------------------------------------
-- Tabel: realisasi
-- ---------------------------------------------
CREATE TABLE realisasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    indikator_id INT NOT NULL,
    tahun_id INT NOT NULL,
    tw1 DECIMAL(15,4) NULL,
    tw2 DECIMAL(15,4) NULL,
    tw3 DECIMAL(15,4) NULL,
    tw4 DECIMAL(15,4) NULL,
    total_realisasi DECIMAL(15,4) GENERATED ALWAYS AS (
        COALESCE(tw1,0) + COALESCE(tw2,0) + COALESCE(tw3,0) + COALESCE(tw4,0)
    ) STORED,
    link_data_dukung TEXT NULL,
    evaluasi TEXT NULL,
    rencana_tindak_lanjut TEXT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_realisasi (indikator_id, tahun_id),
    FOREIGN KEY (indikator_id) REFERENCES indikator(id) ON DELETE CASCADE,
    FOREIGN KEY (tahun_id) REFERENCES tahun_anggaran(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------
-- Data Awal: Kelompok
-- ---------------------------------------------
INSERT INTO kelompok (kode, nama) VALUES
('DJM', 'Direktorat Jenderal Minyak dan Gas Bumi'),
('SDM', 'Sekretariat Ditjen Migas'),
('DMB', 'Direktorat Minyak dan Gas Bumi'),
('DMO', 'Direktorat Manajemen Operasi'),
('DME', 'Direktorat Manajemen Eksplorasi'),
('DMT', 'Direktorat Manajemen Teknik'),
('DMI', 'Direktorat Manajemen Infrastruktur'),
('SDMK', 'Bagian Keuangan'),
('SDMP', 'Bagian Perbendaharaan'),
('SDMKN', 'Kekayaan Negara'),
('SDMA', 'Akuntansi'),
('SDML', 'Bagian Rencana dan Laporan'),
('SDMLA', 'Penyiapan Rencana dan Anggaran'),
('SDMLI', 'Pengelolaan Informasi'),
('SDMLE', 'Evaluasi dan Laporan'),
('SDMH', 'Bagian Hukum'),
('SDMHR', 'Penyusunan Peraturan'),
('SDMHT', 'Pertimbangan Hukum'),
('SDMHI', 'Informasi Hukum'),
('SDMU', 'Bagian Umum'),
('SDMUT', 'Tata Usaha'),
('SDMUL', 'Perlengkapan dan Rumah'),
('SDMPG', 'Kepegawaian'),
('SDMPO', 'Organisasi'),
('DMBS', 'Penyiapan Program Minyak dan Gas Bumi'),
('DMBSK', 'Penyiapan Program Pengembangan Minyak dan Gas Bumi'),
('DMBSM', 'Penyiapan Program Pemanfaatan Minyak dan Gas Bumi'),
('DMBK', 'Kerja Sama Minyak dan Gas Bumi'),
('DMBKB', 'Kerjasama Bilateral dan Dalam Negeri Migas'),
('DMBKM', 'Kerjasama Multilateral dan Regional Migas'),
('DMBP', 'Penerimaan Negara dan Pengelolaan'),
('DMBPH', 'Penerimaan Negara Minyak dan Gas Bumi'),
('DMBPR', 'Pengelolaan Penerimaan Negara Bukan Pajak'),
('DMBI', 'Pengembangan Investasi Minyak dan Gas Bumi'),
('DMBIE', 'Subkoordinator Pengembangan Investasi Usaha'),
('DMBIS', 'Subkoordinator Pengembangan Investasi Usaha'),
('DMBO', 'Pemberdayaan Potensi Dalam Negeri Migas'),
('DMBOT', 'Tata Kelola Kegiatan Migas Bumi dan Pengelolaan'),
('DMOG', 'Pengawasan dan Pengelolaan Usaha Hilir Migas'),
('DMOGI', 'Pelayanan Kegiatan Usaha Hilir Migas'),
('DMOGP', 'Pengawasan Kegiatan Usaha Hilir Migas (+P)'),
('DMOS', 'Harga Bahan Bakar Minyak dan Gas Bumi'),
('DMOHM', 'Harga Bahan Bakar Minyak'),
('DMOHG', 'Harga Gas Bumi'),
('DMOSR', 'Perencanaan Subsidi'),
('DMOSG', 'Pengawasan Subsidi'),
('DMOT', 'Tata Kelola dan Pengelolaan Komoditas Kegiatan'),
('DMOTM', 'Tata Kelola Kegiatan Migas Bumi dan Pengelolaan'),
('DMOTG', 'Tata Kelola Kegiatan Gas Bumi dan Pengelolaan'),
('DMOTD', 'Pengolahan Data Hilir Minyak dan Gas Bumi'),
('DMEW', 'Pengembangan Wilayah Kerja Minyak'),
('DMEWS', 'Penyiapan Wilayah Kerja Minyak'),
('DMEWT', 'Pengawaran Standardisasi Hulu dan Gas Bumi Konvensional'),
('DMEE', 'Eksplorasi Minyak'),
('DMEEL', 'Pelayanan Usaha Eksplorasi'),
('DMEEM', 'Pemantauan dan Evaluasi Usaha'),
('DMED', 'Pengembangan Usaha Hulu'),
('DMEDE', 'Penilaian Kontrak Kerja Sama'),
('DMEDT', 'Penilaian'),
('DMEP', 'Keselamatan Eksplorasi Minyak'),
('DMEPL', 'Pelayanan Usaha Eksplorasi'),
('DMEPP', 'Pemantauan Usaha Eksplorasi'),
('DMEN', 'Pengembangan Wilayah Kerja Minyak'),
('DMENW', 'Penyiapan Wilayah Kerja Minyak'),
('DMENK', 'Penawaran Wilayah Kerja Minyak'),
('DMOM', 'Pelayanan dan Pengawasan Kegiatan Hilir'),
('DMOMI', 'Pelayanan Kegiatan Usaha Hilir Migas'),
('DMOMP', 'Pengawasan Kegiatan Usaha Hilir Migas'),
('DMTS', 'Standardisasi Minyak dan Gas Bumi'),
('DMTSO', 'Penyiapan dan Penerapan Standardisasi Hilir'),
('DMTSE', 'Penyiapan dan Penerapan Standardisasi Hulu'),
('DMTP', 'Usaha Penunjang Minyak dan Gas Bumi'),
('DMTPE', 'Usaha Penunjang Hulu Minyak dan Gas Bumi'),
('DMTPO', 'Usaha Penunjang Hilir Minyak dan Gas Bumi'),
('DMTL', 'Teknik dan Keselamatan Lingkungan Minyak'),
('DMTLT', 'Keselamatan Minyak dan Gas Bumi (HT: Tech)'),
('DMTLS', 'Keselamatan Lingkungan Minyak dan Gas Bumi 5'),
('DMTB', 'Keselamatan Pekerja dan Umum Hulu Migas'),
('DMTBP', 'Subkoordinator Keselamatan Instalasi Hulu Migas'),
('DMTEP', 'Keselamatan Pekerja dan Umum Hulu Minyak'),
('DMTOI', 'Subkoordinator Keselamatan Instalasi Hilir Minyak'),
('DMIB', 'Pelaksanaan Pembangunan'),
('DMIBP', 'Penyiapan Pelaksanaan Pembangunan'),
('DMIBL', 'Pelaksanaan Pekerjaan Pembangunan'),
('DMIR', 'Perencanaan Pembangunan'),
('DMIRR', 'Penyiapan Perencanaan Pembangunan'),
('DMIRB', 'Penyusunan Perencanaan Pembangunan'),
('DMIA', 'Pengawasan Pembangunan'),
('DMIAA', 'Pengawasan Pembangunan Infrastruktur'),
('DMIAO', 'Pengawasan Pengoperasian');

-- ---------------------------------------------
-- Data Awal: Tahun Anggaran
-- ---------------------------------------------
INSERT INTO tahun_anggaran (tahun, is_aktif) VALUES (2025, 1);

-- ---------------------------------------------
-- Data Awal: Admin User
-- password: admin123 (bcrypt)
-- ---------------------------------------------
INSERT INTO users (username, password, nama_lengkap, role, kode_kelompok) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', NULL),
('dmee', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kelompok DMEE', 'user', 'DMEE'),
('dmen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kelompok DMEN', 'user', 'DMEN'),
('dmo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kelompok DMO', 'user', 'DMO');

-- Catatan: Password default = "password" (hash bcrypt Laravel default)
-- Ganti dengan: password_hash('passwordbaru', PASSWORD_BCRYPT)
