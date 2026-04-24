-- Create the database if it doesn't already exist
CREATE DATABASE IF NOT EXISTS student_registration;

-- Switch to the newly created database
USE student_registration;

-- Create the student table to store registration details
CREATE TABLE IF NOT EXISTS student (
    studentid VARCHAR(50) PRIMARY KEY, -- Unique identifier for each student (Primary Key)
    fullname VARCHAR(100) NOT NULL, -- Full name of the student, cannot be null
    email VARCHAR(100) NOT NULL UNIQUE, -- Email address, must be unique across all students
    yearofregister INT NOT NULL, -- Year the student is registering for
    programid VARCHAR(50) NOT NULL, -- The ID of the program the student is enrolling in
    is_verified TINYINT(1) DEFAULT 0, -- Boolean flag (0=false, 1=true) to check if email is verified
    verification_token VARCHAR(100) -- Unique token sent via email for verification
);
