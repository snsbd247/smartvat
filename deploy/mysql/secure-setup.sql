-- PLASTEX ERP — create database + least-privilege application user.
-- Run as root: sudo mysql < deploy/mysql/secure-setup.sql  (edit the password first)
CREATE DATABASE IF NOT EXISTS plastex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'plastex_app'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON plastex.* TO 'plastex_app'@'127.0.0.1';
FLUSH PRIVILEGES;
