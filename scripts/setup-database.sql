CREATE DATABASE IF NOT EXISTS symfony_practice_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'symfony_practice'@'localhost' IDENTIFIED BY 'symfony-local-only';
GRANT ALL PRIVILEGES ON symfony_practice_test.* TO 'symfony_practice'@'localhost';
