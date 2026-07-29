-- Database terpisah khusus untuk menjalankan test.
--
-- Test memakai RefreshDatabase yang menghapus seluruh tabel setiap kali
-- dijalankan, jadi ia TIDAK BOLEH memakai database pengembangan.
CREATE DATABASE IF NOT EXISTS learning_system_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON learning_system_test.* TO 'lms_user'@'%';
FLUSH PRIVILEGES;
