-- Create database
CREATE DATABASE event_management;

-- Connect to the database
\c event_management

-- Create User table
CREATE TABLE "User" (
    u_UserID SERIAL PRIMARY KEY,
    u_name VARCHAR(100) NOT NULL,
    u_email VARCHAR(255) UNIQUE NOT NULL,
    u_password VARCHAR(255) NOT NULL,
    u_role VARCHAR(20) NOT NULL
);

-- Create Category table
CREATE TABLE "Category" (
    c_CategoryID SERIAL PRIMARY KEY,
    c_Name VARCHAR(80) UNIQUE NOT NULL,
    c_Description TEXT
);

-- Create Event table
CREATE TABLE "Event" (
    e_EventID SERIAL PRIMARY KEY,
    e_title VARCHAR(160) NOT NULL,
    e_description TEXT,
    e_EventDate TIMESTAMP NOT NULL,
    e_location VARCHAR(255) NOT NULL,
    e_status VARCHAR(50) CHECK (e_status IN ('planning','active','completed','archived')),
    e_UserID INTEGER REFERENCES "User"(u_UserID) ON DELETE CASCADE,
    e_CategoryID INTEGER REFERENCES "Category"(c_CategoryID)
);

-- Create Registration table
CREATE TABLE "Registration" (
    r_RegistrationID SERIAL PRIMARY KEY,
    r_UserID INTEGER REFERENCES "User"(u_UserID) ON DELETE CASCADE,
    r_EventID INTEGER REFERENCES "Event"(e_EventID) ON DELETE CASCADE,
    r_RegistrationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    r_AttendanceStatus VARCHAR(20) DEFAULT 'registered'
);

-- Create Analytics table
CREATE TABLE "Analytics" (
    a_UserID INTEGER PRIMARY KEY REFERENCES "User"(u_UserID) ON DELETE CASCADE,
    a_TotalEventsOrganized INTEGER DEFAULT 0,
    a_TotalEventsAttended INTEGER DEFAULT 0,
    a_CancelledRegistrations INTEGER DEFAULT 0,
    a_LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
