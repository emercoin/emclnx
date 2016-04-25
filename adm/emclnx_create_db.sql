CREATE USER 'emclnx'@'localhost' IDENTIFIED BY '12345';
create database emclnx;
GRANT ALL PRIVILEGES ON emclnx.* TO 'emclnx'@'localhost';
FLUSH PRIVILEGES;

