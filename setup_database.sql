-- Create database
CREATE DATABASE event_management;

-- Connect to the database
\c event_management

-- Create User table
CREATE TABLE "User" (
    u_UserID INT PRIMARY KEY,
    u_name VARCHAR(100) NOT NULL,
    u_email VARCHAR(255) UNIQUE NOT NULL,
    u_password VARCHAR(255) NOT NULL,
    u_role VARCHAR(20) NOT NULL
);

-- Create Category table
CREATE TABLE "Category" (
    c_CategoryID INT PRIMARY KEY,
    c_Name VARCHAR(80) UNIQUE NOT NULL,
    c_Description TEXT
);

-- Create Event table
CREATE TABLE "Event" (
    e_EventID INT PRIMARY KEY,
    e_title VARCHAR(160) NOT NULL,
    e_description TEXT,
    e_EventDate TIMESTAMP NOT NULL,
    e_location VARCHAR(255) NOT NULL,
    e_status VARCHAR(20) NOT NULL,
    e_UserID INT NOT NULL,
    e_CategoryID INT NOT NULL,
    FOREIGN KEY (e_UserID) REFERENCES "User"(u_UserID),
    FOREIGN KEY (e_CategoryID) REFERENCES "Category"(c_CategoryID)
);

-- Create Registration table
CREATE TABLE "Registration" (
    r_RegistrationID INT PRIMARY KEY,
    r_UserID INT NOT NULL,
    r_EventID INT NOT NULL,
    r_RegistrationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    r_AttendanceStatus VARCHAR(20) DEFAULT 'registered',
    FOREIGN KEY (r_UserID) REFERENCES "User"(u_UserID),
    FOREIGN KEY (r_EventID) REFERENCES "Event"(e_EventID)
);

-- Create Analytics table
CREATE TABLE "Analytics" (
    a_UserID INT PRIMARY KEY,
    a_TotalEventsOrganized INT DEFAULT 0,
    a_TotalEventsAttended INT DEFAULT 0,
    a_CancelledRegistrations INT DEFAULT 0,
    a_LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (a_UserID) REFERENCES "User"(u_UserID)
);

-- Insert sample categories
INSERT INTO "Category" (c_Name, c_Description) VALUES
('Technology', 'Tech events and conferences'),
('Sports', 'Sports and fitness events'),
('Music', 'Concerts and music festivals'),
('Education', 'Workshops and seminars'),
('Business', 'Business networking events');

-- Success message
SELECT 'Database setup complete!' as message;
