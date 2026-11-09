-- KopiKita: jalankan sekali di phpMyAdmin (tab SQL) atau: mysql -u root < schema.sql
CREATE DATABASE IF NOT EXISTS kopikita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_kopikita;

CREATE TABLE users (
  id_user    INT AUTO_INCREMENT PRIMARY KEY,
  nama_user  VARCHAR(100) NOT NULL,
  email_user VARCHAR(100) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,            -- hash bcrypt
  no_hp_user VARCHAR(15)  NOT NULL,
  role       VARCHAR(20)  NOT NULL DEFAULT 'pembeli'   -- pembeli | penjual
) ENGINE=InnoDB;

CREATE TABLE pembeli (
  id_pembeli        INT AUTO_INCREMENT PRIMARY KEY,
  id_user           INT NOT NULL UNIQUE,
  nama_pembeli      VARCHAR(100) NOT NULL,
  no_hp_pembeli     VARCHAR(15)  NOT NULL,
  alamat_pengiriman TEXT NULL,
  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE produk (
  id_produk        INT AUTO_INCREMENT PRIMARY KEY,
  nama_produk      VARCHAR(150) NOT NULL,
  jenis_kopi       VARCHAR(20)  NOT NULL DEFAULT 'Arabika',  -- tambahan (UC-007)
  deskripsi_produk TEXT,
  harga_produk     DECIMAL(12,2) NOT NULL,
  stok             INT NOT NULL DEFAULT 0,
  foto             VARCHAR(255) NULL                          -- tambahan (UC-007)
) ENGINE=InnoDB;

CREATE TABLE item_keranjang (
  id_keranjang INT AUTO_INCREMENT PRIMARY KEY,
  id_user      INT NOT NULL,
  id_produk    INT NOT NULL,
  jumlah       INT NOT NULL,
  UNIQUE (id_user, id_produk),
  FOREIGN KEY (id_user)   REFERENCES users(id_user)    ON DELETE CASCADE,
  FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ekspedisi (
  id_ekspedisi    INT AUTO_INCREMENT PRIMARY KEY,
  nama_ekspedisi  VARCHAR(50) NOT NULL UNIQUE,
  tarif_ekspedisi DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE pesanan (
  id_pesanan        INT AUTO_INCREMENT PRIMARY KEY,
  nomor_pesanan     VARCHAR(30) NOT NULL UNIQUE,
  id_pembeli        INT NOT NULL,
  id_ekspedisi      INT NOT NULL,
  tanggal           DATETIME NOT NULL,
  total_harga       DECIMAL(12,2) NOT NULL,
  status_pesanan    VARCHAR(20) NOT NULL DEFAULT 'Dibayar',   -- tambahan (UC-006, UC-008)
  nomor_resi        VARCHAR(50) NULL,                         -- tambahan (UC-006, UC-008)
  alamat_pengiriman TEXT NOT NULL,                            -- tambahan: alamat saat pesanan dibuat
  FOREIGN KEY (id_pembeli)   REFERENCES pembeli(id_pembeli),
  FOREIGN KEY (id_ekspedisi) REFERENCES ekspedisi(id_ekspedisi)
) ENGINE=InnoDB;

CREATE TABLE detail_pesanan (
  id_detail      INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan     INT NOT NULL,
  id_produk      INT NOT NULL,
  jumlah_pesanan INT NOT NULL,
  harga_satuan   DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE,
  FOREIGN KEY (id_produk)  REFERENCES produk(id_produk)
) ENGINE=InnoDB;

CREATE TABLE pembayaran (
  id_pembayaran      INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan         INT NOT NULL,
  metode_pembayaran  VARCHAR(50) NOT NULL,
  status_pembayaran  VARCHAR(20) NOT NULL,                    -- pending | lunas | gagal
  tanggal_pembayaran DATETIME NOT NULL,
  FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Data awal
INSERT INTO ekspedisi (nama_ekspedisi, tarif_ekspedisi) VALUES ('JNE',15000),('J&T',18000),('SiCepat',12000);

INSERT INTO produk (nama_produk, jenis_kopi, deskripsi_produk, harga_produk, stok) VALUES
('Arabika Gayo 250 g','Arabika','Aroma cokelat dan rempah, tubuh tebal, asam lembut. Cocok untuk V60 dan espresso.',85000,10),
('Robusta Temanggung 250 g','Robusta','Pahit tegas dengan crema tebal. Pilihan untuk kopi susu dan tubruk.',55000,24),
('Toraja Sapan 200 g','Arabika','Rasa buah gelap, rempah, dan manis panjang di akhir seruputan.',98000,6),
('Kintamani Bali 250 g','Arabika','Cerah, sitrus, dan floral. Cocok diseduh manual.',92000,0),
('Flores Bajawa 250 g','Arabika','Manis karamel, aftertaste cokelat bersih.',90000,12),
('Robusta Lampung 500 g','Robusta','Body penuh, sangat cocok untuk campuran espresso harian.',95000,18);

-- Akun contoh (password: Jihan123, Nadin123, Budi123, Toko123)
INSERT INTO users (nama_user,email_user,password,no_hp_user,role) VALUES
('Jihan Putri','jihan123@gmail.com','$2b$10$9xi7VmFq92D4E1p75np4h.fFUpnB3r7iHphhJMzXVWefYYlH1bFDC','081234567890','pembeli'),
('Nadin Safira','nadin@gmail.com','$2b$10$yaqiM1J.DZ2AH0mydcaEHulqcpfSZkC5T4asieW8F3lDSMEc0SjUu','081298765432','pembeli'),
('Budi Santoso','budi.santoso@mail.com','$2b$10$hmnfLPUIh7.waP4mSjHzieIQAq/HYcMfKB1QhW8aDkq1h0fMKbzMi','081377788899','pembeli'),
('Toko Kopi Nusantara','toko@kopikita.id','$2b$10$JOcVTPXGEG3Kjw4ADq.rPODm78d2.DuuDQoUstLtOAmmqqf0nVhhW','081200011122','penjual');
INSERT INTO pembeli (id_user,nama_pembeli,no_hp_pembeli) SELECT id_user,nama_user,no_hp_user FROM users WHERE role='pembeli';
