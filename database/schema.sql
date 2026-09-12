-- =====================================================================
-- Skema database: Sistem Absensi PT Jafran Indonesia
-- Import file ini lewat phpMyAdmin, atau: mysql -u root -p < schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS absensi_jafran CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE absensi_jafran;

-- ---------------------------------------------------------------------
-- Admin (hanya 1 role: admin/HR). Password default: admin123
-- SEGERA GANTI password ini lewat menu Pengaturan setelah login pertama!
-- ---------------------------------------------------------------------
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Password hash di bawah ini adalah untuk "admin123" (bcrypt).
INSERT INTO admins (username, password_hash, nama) VALUES
('admin', '$2y$10$EHlC5fc5ybEPju69.yo3RO6oTEpXdZDWM1Pyk1zlXvFA5Mj5l83Z2', 'Administrator');

-- ---------------------------------------------------------------------
-- Departemen (mis: Kantor/Office, Processing, dst)
-- ---------------------------------------------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_departemen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

INSERT INTO departments (nama_departemen) VALUES ('Kantor'), ('Processing');

-- ---------------------------------------------------------------------
-- Shift kerja (master jam kerja). hari_kerja disimpan "1,2,3,4,5"
-- artinya Senin(1) s.d. Jumat(5). is_lembur menandai shift malam/lembur.
-- ---------------------------------------------------------------------
CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_shift VARCHAR(100) NOT NULL,
    jam_masuk TIME NOT NULL,
    jam_pulang TIME NOT NULL,
    toleransi_menit INT NOT NULL DEFAULT 15,
    is_lembur TINYINT(1) NOT NULL DEFAULT 0,
    hari_kerja VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
    keterangan VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO shifts (nama_shift, jam_masuk, jam_pulang, toleransi_menit, is_lembur, hari_kerja, keterangan) VALUES
('Shift Kantor', '08:00:00', '17:00:00', 15, 0, '1,2,3,4,5', 'Jam kerja staf kantor'),
('Shift Processing Normal', '08:00:00', '17:00:00', 15, 0, '1,2,3,4,5', 'Jam kerja normal bagian processing'),
('Shift Processing Malam (Lembur)', '20:00:00', '05:00:00', 10, 1, '1,2,3,4,5,6', 'Shift malam, terhitung lembur');

-- ---------------------------------------------------------------------
-- Karyawan
-- ---------------------------------------------------------------------
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(30) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    departemen_id INT DEFAULT NULL,
    jabatan VARCHAR(100) DEFAULT NULL,
    no_hp VARCHAR(20) DEFAULT NULL,
    shift_id INT DEFAULT NULL,
    device_user_id VARCHAR(50) DEFAULT NULL COMMENT 'ID di mesin fingerprint, diisi saat integrasi mesin sudah jalan',
    status_aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (departemen_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Log absensi mentah, nanti diisi otomatis oleh proses sinkronisasi
-- mesin (ZKTeco/EasyLink). Untuk sekarang bisa diisi manual dulu.
-- ---------------------------------------------------------------------
CREATE TABLE attendance_raw_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_user_id VARCHAR(50) NOT NULL,
    scan_time DATETIME NOT NULL,
    verify_type VARCHAR(20) DEFAULT NULL,
    is_processed TINYINT(1) NOT NULL DEFAULT 0,
    synced_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Absensi harian (hasil olahan): 1 baris per karyawan per tanggal.
-- ---------------------------------------------------------------------
CREATE TABLE attendance_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk_aktual TIME DEFAULT NULL,
    jam_pulang_aktual TIME DEFAULT NULL,
    status ENUM('hadir','telat','alpha','lembur','izin') NOT NULL DEFAULT 'alpha',
    menit_telat INT NOT NULL DEFAULT 0,
    jam_lembur DECIMAL(4,2) NOT NULL DEFAULT 0,
    catatan VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uniq_employee_tanggal (employee_id, tanggal),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;
