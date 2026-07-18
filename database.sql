-- Create database if not exists
CREATE DATABASE IF NOT EXISTS student_registration;
USE student_registration;

-- Create the programme table first (no dependencies)
DROP TABLE IF EXISTS programme;
CREATE TABLE IF NOT EXISTS programme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    duration VARCHAR(50) NOT NULL
);

-- Insert some default programmes
INSERT INTO programme (name, duration) VALUES 
('Computer Science', '4 Years'),
('Business Administration', '3 Years'),
('Engineering', '4 Years'),
('Information Technology', '3 Years')
ON DUPLICATE KEY UPDATE name=name;

-- Create the student table next (depends on programme)
DROP TABLE IF EXISTS student;
CREATE TABLE IF NOT EXISTS student (
    id INT AUTO_INCREMENT PRIMARY KEY, -- Auto incrementing ID for generating S0000X
    studentid VARCHAR(50) UNIQUE NOT NULL, -- Unique identifier for each student
    fullname VARCHAR(100) NOT NULL, -- Full name of the student, cannot be null
    email VARCHAR(100) NOT NULL UNIQUE, -- Email address, must be unique across all students
    yearofregister INT NOT NULL, -- Year the student is registering for
    programid INT NOT NULL, -- The ID of the program the student is enrolling in
    is_verified TINYINT(1) DEFAULT 0, -- Boolean flag (0=false, 1=true) to check if email is verified
    verification_token VARCHAR(100), -- Unique token sent via email for verification
    photo VARCHAR(255) DEFAULT NULL, -- Path to student profile photo
    FOREIGN KEY (programid) REFERENCES programme(id)
);

-- Create the users table last (depends on student)
DROP TABLE IF EXISTS users;
CREATE TABLE IF NOT EXISTS users (
    user_id VARCHAR(50) PRIMARY KEY, -- Will match studentid
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'student',
    FOREIGN KEY (user_id) REFERENCES student(studentid) ON DELETE CASCADE
);
