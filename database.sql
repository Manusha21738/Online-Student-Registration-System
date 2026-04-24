CREATE DATABASE IF NOT EXISTS student_registration;
USE student_registration;

CREATE TABLE IF NOT EXISTS student (
    studentid VARCHAR(50) PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    yearofregister INT NOT NULL,
    programid VARCHAR(50) NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(100)
);
