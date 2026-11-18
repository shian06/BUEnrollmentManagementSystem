-- =====================================================================
-- Bicol University Enrollment Management System
-- IT116 - Information Management 2 | Week 3: SQL Implementation
--
-- How to use (XAMPP):
--   1. Start Apache and MySQL in the XAMPP Control Panel.
--   2. Open http://localhost/phpmyadmin
--   3. Click the "Import" tab, choose this file, and click "Import" (or "Go").
--
-- This file creates the database, all 13 tables, sample data, the view,
-- the stored procedure, and the triggers. The five SQL queries are in
-- Part 6. Run them one at a time in the "SQL" tab and screenshot each
-- statement together with its result.
--
-- Warning: running this file again deletes and rebuilds bu_enrollment.
-- =====================================================================

DROP DATABASE IF EXISTS bu_enrollment;
CREATE DATABASE bu_enrollment CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE bu_enrollment;


-- =====================================================================
-- PART 1: TABLES (3NF design from Week 2)
-- =====================================================================

CREATE TABLE programs (
    program_id    INT AUTO_INCREMENT PRIMARY KEY,
    program_code  VARCHAR(10)  NOT NULL UNIQUE,
    program_name  VARCHAR(100) NOT NULL,
    college       VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE students (
    student_id      VARCHAR(15)  PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    middle_name     VARCHAR(50)  NULL,
    last_name       VARCHAR(50)  NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    contact_number  VARCHAR(20)  NULL,
    program_id      INT          NOT NULL,
    year_level      TINYINT      NOT NULL CHECK (year_level BETWEEN 1 AND 5),
    classification  ENUM('Regular','Irregular') NOT NULL DEFAULT 'Regular',
    CONSTRAINT fk_students_program FOREIGN KEY (program_id) REFERENCES programs(program_id)
) ENGINE=InnoDB;

CREATE TABLE academic_terms (
    term_id           INT AUTO_INCREMENT PRIMARY KEY,
    school_year       VARCHAR(9) NOT NULL,
    semester          ENUM('1st Semester','2nd Semester','Midyear') NOT NULL,
    enrollment_start  DATE NOT NULL,
    enrollment_end    DATE NOT NULL,
    is_active         BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT uq_term UNIQUE (school_year, semester),
    CONSTRAINT chk_term_dates CHECK (enrollment_end >= enrollment_start)
) ENGINE=InnoDB;

CREATE TABLE subjects (
    subject_id     INT AUTO_INCREMENT PRIMARY KEY,
    subject_code   VARCHAR(15)  NOT NULL UNIQUE,
    subject_title  VARCHAR(100) NOT NULL,
    units          DECIMAL(3,1) NOT NULL CHECK (units > 0)
) ENGINE=InnoDB;

CREATE TABLE blocks (
    block_id    INT AUTO_INCREMENT PRIMARY KEY,
    block_code  VARCHAR(20) NOT NULL,
    program_id  INT NOT NULL,
    term_id     INT NOT NULL,
    year_level  TINYINT NOT NULL CHECK (year_level BETWEEN 1 AND 5),
    CONSTRAINT uq_block UNIQUE (block_code, term_id),
    CONSTRAINT fk_blocks_program FOREIGN KEY (program_id) REFERENCES programs(program_id),
    CONSTRAINT fk_blocks_term    FOREIGN KEY (term_id)    REFERENCES academic_terms(term_id)
) ENGINE=InnoDB;

CREATE TABLE rooms (
    room_id        INT AUTO_INCREMENT PRIMARY KEY,
    room_code      VARCHAR(20) NOT NULL UNIQUE,
    building       VARCHAR(60) NOT NULL,
    room_capacity  INT NOT NULL CHECK (room_capacity > 0)
) ENGINE=InnoDB;

CREATE TABLE subject_offerings (
    offering_id       INT AUTO_INCREMENT PRIMARY KEY,
    subject_id        INT NOT NULL,
    term_id           INT NOT NULL,
    block_id          INT NULL,               -- NULL = open section for irregular students
    section_code      VARCHAR(20) NOT NULL,
    max_capacity      INT NOT NULL CHECK (max_capacity > 0),
    current_enrolled  INT NOT NULL DEFAULT 0,
    CONSTRAINT uq_section UNIQUE (subject_id, term_id, section_code),
    CONSTRAINT chk_capacity CHECK (current_enrolled BETWEEN 0 AND max_capacity),
    CONSTRAINT fk_offerings_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    CONSTRAINT fk_offerings_term    FOREIGN KEY (term_id)    REFERENCES academic_terms(term_id),
    CONSTRAINT fk_offerings_block   FOREIGN KEY (block_id)   REFERENCES blocks(block_id)
) ENGINE=InnoDB;

CREATE TABLE class_schedules (
    schedule_id  INT AUTO_INCREMENT PRIMARY KEY,
    offering_id  INT NOT NULL,
    room_id      INT NOT NULL,
    day_of_week  ENUM('Mon','Tue','Wed','Thu','Fri','Sat') NOT NULL,
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    class_type   ENUM('Lecture','Laboratory') NOT NULL DEFAULT 'Lecture',
    CONSTRAINT chk_class_time CHECK (end_time > start_time),
    CONSTRAINT fk_sched_offering FOREIGN KEY (offering_id) REFERENCES subject_offerings(offering_id),
    CONSTRAINT fk_sched_room     FOREIGN KEY (room_id)     REFERENCES rooms(room_id)
) ENGINE=InnoDB;

CREATE TABLE registrar_staff (
    staff_id   INT AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(100) NOT NULL,
    username   VARCHAR(30)  NOT NULL UNIQUE,
    position   VARCHAR(50)  NULL
) ENGINE=InnoDB;

CREATE TABLE enrollments (
    enrollment_id    INT AUTO_INCREMENT PRIMARY KEY,
    student_id       VARCHAR(15) NOT NULL,
    term_id          INT NOT NULL,
    block_id         INT NULL,
    enrollment_date  DATE NOT NULL DEFAULT (CURRENT_DATE),
    status           ENUM('Pending','Verified','Enrolled') NOT NULL DEFAULT 'Pending',
    verified_by      INT NULL,
    last_modified    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_one_enrollment_per_term UNIQUE (student_id, term_id),
    CONSTRAINT fk_enr_student  FOREIGN KEY (student_id)  REFERENCES students(student_id),
    CONSTRAINT fk_enr_term     FOREIGN KEY (term_id)     REFERENCES academic_terms(term_id),
    CONSTRAINT fk_enr_block    FOREIGN KEY (block_id)    REFERENCES blocks(block_id),
    CONSTRAINT fk_enr_verifier FOREIGN KEY (verified_by) REFERENCES registrar_staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE enrollment_details (
    enrollment_detail_id  INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id         INT NOT NULL,
    offering_id           INT NOT NULL,
    date_added            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_no_duplicate_section UNIQUE (enrollment_id, offering_id),
    CONSTRAINT fk_det_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE,
    CONSTRAINT fk_det_offering   FOREIGN KEY (offering_id)   REFERENCES subject_offerings(offering_id)
) ENGINE=InnoDB;

CREATE TABLE cor_records (
    cor_id         INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id  INT NOT NULL,
    cor_number     VARCHAR(20) NOT NULL UNIQUE,
    printed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    printed_by     INT NOT NULL,
    CONSTRAINT fk_cor_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id),
    CONSTRAINT fk_cor_staff      FOREIGN KEY (printed_by)    REFERENCES registrar_staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE verification_requests (
    request_id      INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id   INT NOT NULL,
    concern         VARCHAR(255) NOT NULL,
    date_filed      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    request_status  ENUM('Pending','Resolved') NOT NULL DEFAULT 'Pending',
    resolved_by     INT NULL,
    date_resolved   DATETIME NULL,
    CONSTRAINT fk_req_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id),
    CONSTRAINT fk_req_staff      FOREIGN KEY (resolved_by)   REFERENCES registrar_staff(staff_id)
) ENGINE=InnoDB;


-- =====================================================================
-- PART 2: TRIGGERS
-- These keep section capacity correct (Business Rule 5) and make sure
-- a Regular student always has a block (Business Rule 3).
-- =====================================================================

DELIMITER $$

-- Stops a student from being added to a section that is already full.
CREATE TRIGGER trg_check_section_capacity
BEFORE INSERT ON enrollment_details
FOR EACH ROW
BEGIN
    DECLARE v_current INT;
    DECLARE v_max INT;

    SELECT current_enrolled, max_capacity
      INTO v_current, v_max
      FROM subject_offerings
     WHERE offering_id = NEW.offering_id;

    IF v_current >= v_max THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'This section is already full. Choose another section.';
    END IF;
END$$

-- Adds 1 to the section count when a student takes a section, and marks
-- the enrollment as changed (so an older printed COR shows as outdated).
CREATE TRIGGER trg_after_add_subject
AFTER INSERT ON enrollment_details
FOR EACH ROW
BEGIN
    UPDATE subject_offerings
       SET current_enrolled = current_enrolled + 1
     WHERE offering_id = NEW.offering_id;

    UPDATE enrollments
       SET last_modified = NOW()
     WHERE enrollment_id = NEW.enrollment_id;
END$$

-- Subtracts 1 from the section count when a student drops a section.
CREATE TRIGGER trg_after_drop_subject
AFTER DELETE ON enrollment_details
FOR EACH ROW
BEGIN
    UPDATE subject_offerings
       SET current_enrolled = current_enrolled - 1
     WHERE offering_id = OLD.offering_id;

    UPDATE enrollments
       SET last_modified = NOW()
     WHERE enrollment_id = OLD.enrollment_id;
END$$

-- A Regular student's enrollment must have a block.
CREATE TRIGGER trg_regular_needs_block
BEFORE INSERT ON enrollments
FOR EACH ROW
BEGIN
    DECLARE v_class VARCHAR(10);

    SELECT classification INTO v_class
      FROM students
     WHERE student_id = NEW.student_id;

    IF v_class = 'Regular' AND NEW.block_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A Regular student must be assigned to a block.';
    END IF;
END$$

DELIMITER ;


-- =====================================================================
-- PART 3: SAMPLE DATA
-- current_enrolled is NOT typed in. The triggers count it automatically
-- as enrollment_details rows are inserted.
-- =====================================================================

INSERT INTO programs (program_id, program_code, program_name, college) VALUES
(1, 'BSIT', 'Bachelor of Science in Information Technology', 'College of Science'),
(2, 'BSCS', 'Bachelor of Science in Computer Science',       'College of Science');

INSERT INTO academic_terms (term_id, school_year, semester, enrollment_start, enrollment_end, is_active) VALUES
(1, '2026-2027', '1st Semester', '2026-08-03', '2026-08-14', TRUE),
(2, '2026-2027', '2nd Semester', '2027-01-11', '2027-01-22', FALSE);

INSERT INTO subjects (subject_id, subject_code, subject_title, units) VALUES
(1, 'IT 116', 'Information Management 2',                 3.0),
(2, 'IT 117', 'Integrative Programming and Technologies', 3.0),
(3, 'IT 118', 'Systems Analysis and Design',              3.0);

INSERT INTO blocks (block_id, block_code, program_id, term_id, year_level) VALUES
(1, 'BSIT 3A', 1, 1, 3),
(2, 'BSIT 3B', 1, 1, 3);

INSERT INTO rooms (room_id, room_code, building, room_capacity) VALUES
(1, 'CS-201', 'CS Building', 40),
(2, 'LAB-3',  'CS Building', 40),
(3, 'CS-105', 'CS Building', 35);

INSERT INTO subject_offerings (offering_id, subject_id, term_id, block_id, section_code, max_capacity) VALUES
(101, 1, 1, 1,    'IT116-3A',   40),
(102, 2, 1, NULL, 'IT117-OPEN', 30),
(103, 2, 1, 1,    'IT117-3A',   40),
(104, 3, 1, 1,    'IT118-3A',   40),
(105, 1, 1, 2,    'IT116-3B',   40),
(106, 3, 1, NULL, 'IT118-OPEN',  2);   -- small capacity, used to test the "section full" trigger

INSERT INTO class_schedules (schedule_id, offering_id, room_id, day_of_week, start_time, end_time, class_type) VALUES
(1, 101, 1, 'Mon', '10:30:00', '12:00:00', 'Lecture'),
(2, 101, 2, 'Thu', '13:00:00', '16:00:00', 'Laboratory'),
(3, 102, 1, 'Wed', '08:00:00', '09:30:00', 'Lecture'),
(4, 103, 1, 'Tue', '08:00:00', '09:30:00', 'Lecture'),
(5, 104, 3, 'Fri', '13:00:00', '14:30:00', 'Lecture'),
(6, 105, 3, 'Mon', '13:00:00', '14:30:00', 'Lecture'),
(7, 106, 3, 'Sat', '08:00:00', '11:00:00', 'Lecture');

INSERT INTO registrar_staff (staff_id, full_name, username, position) VALUES
(1, 'Liza P. Cruz',       'lcruz',     'Registrar Clerk'),
(2, 'Ramon T. Dela Peña', 'rdelapena', 'Registrar Officer');

INSERT INTO students (student_id, first_name, middle_name, last_name, email, contact_number, program_id, year_level, classification) VALUES
('2023-00123', 'Ana',   'Molina',  'Santos',     'ana.santos@bicol-u.edu.ph',     '09171234567', 1, 3, 'Regular'),
('2022-00457', 'Mark',  'Dizon',   'Reyes',      'mark.reyes@bicol-u.edu.ph',     '09981234567', 1, 3, 'Irregular'),
('2023-00188', 'Carla', 'Bautista','Lim',        'carla.lim@bicol-u.edu.ph',      '09175550188', 1, 3, 'Regular'),
('2023-00201', 'Joel',  'Ramos',   'Villanueva', 'joel.villanueva@bicol-u.edu.ph','09185550201', 1, 3, 'Regular'),
('2021-00333', 'Paolo', 'Cruz',    'Mendoza',    'paolo.mendoza@bicol-u.edu.ph',  '09195550333', 1, 3, 'Irregular'),
('2023-00250', 'Bea',   'Lopez',   'Garcia',     'bea.garcia@bicol-u.edu.ph',     '09205550250', 1, 3, 'Regular');

INSERT INTO enrollments (enrollment_id, student_id, term_id, block_id, enrollment_date, status, verified_by) VALUES
(5001, '2023-00123', 1, 1,    '2026-08-04', 'Enrolled', 1),
(5002, '2022-00457', 1, NULL, '2026-08-05', 'Pending',  NULL),
(5003, '2023-00188', 1, 1,    '2026-08-04', 'Verified', 1),
(5004, '2023-00201', 1, 2,    '2026-08-05', 'Enrolled', 2),
(5005, '2021-00333', 1, NULL, '2026-08-06', 'Pending',  NULL),
(5006, '2023-00250', 1, 1,    '2026-08-06', 'Pending',  NULL);

INSERT INTO enrollment_details (enrollment_detail_id, enrollment_id, offering_id, date_added) VALUES
(9001, 5001, 101, '2026-08-04 09:10:00'),
(9002, 5002, 102, '2026-08-05 14:20:00'),
(9003, 5001, 103, '2026-08-04 09:11:00'),
(9004, 5001, 104, '2026-08-04 09:12:00'),
(9005, 5002, 106, '2026-08-05 14:22:00'),
(9006, 5003, 101, '2026-08-04 10:05:00'),
(9007, 5003, 103, '2026-08-04 10:06:00'),
(9008, 5003, 104, '2026-08-04 10:07:00'),
(9009, 5004, 105, '2026-08-05 13:40:00'),
(9010, 5005, 106, '2026-08-06 08:15:00'),
(9011, 5006, 101, '2026-08-06 11:00:00'),
(9012, 5006, 103, '2026-08-06 11:01:00'),
(9013, 5006, 104, '2026-08-06 11:02:00');

-- The triggers above set last_modified to "now". Set the historical
-- dates so the sample data tells a realistic story.
UPDATE enrollments SET last_modified = '2026-08-06 09:45:00' WHERE enrollment_id = 5001;
UPDATE enrollments SET last_modified = '2026-08-05 14:22:00' WHERE enrollment_id = 5002;
UPDATE enrollments SET last_modified = '2026-08-04 15:30:00' WHERE enrollment_id = 5003;
UPDATE enrollments SET last_modified = '2026-08-05 14:30:00' WHERE enrollment_id = 5004;
UPDATE enrollments SET last_modified = '2026-08-06 08:15:00' WHERE enrollment_id = 5005;
UPDATE enrollments SET last_modified = '2026-08-06 11:02:00' WHERE enrollment_id = 5006;

INSERT INTO cor_records (cor_id, enrollment_id, cor_number, printed_at, printed_by) VALUES
(7001, 5001, 'COR-2026-0001', '2026-08-04 09:20:00', 1),
(7002, 5001, 'COR-2026-0002', '2026-08-06 10:00:00', 2),
(7003, 5004, 'COR-2026-0003', '2026-08-05 15:00:00', 2);

INSERT INTO verification_requests (request_id, enrollment_id, concern, date_filed, request_status, resolved_by, date_resolved) VALUES
(8001, 5001, 'Portal shows IT 116 lecture on Tuesday, but COR says Monday.', '2026-08-05 08:30:00', 'Resolved', 2,    '2026-08-06 10:00:00'),
(8002, 5002, 'Asking to confirm the IT 117 open section schedule.',          '2026-08-06 11:00:00', 'Pending',  NULL, NULL),
(8003, 5005, 'IT 118 open section is not showing on my portal schedule.',    '2026-08-07 09:15:00', 'Pending',  NULL, NULL);


-- =====================================================================
-- PART 4: VIEW
-- One source for every student's class schedule. Both the printed COR
-- and the student portal read from this view, so they always match.
-- =====================================================================

CREATE VIEW vw_student_schedules AS
SELECT
    e.enrollment_id,
    s.student_id,
    CONCAT(s.last_name, ', ', s.first_name)          AS student_name,
    s.classification,
    CONCAT(t.school_year, ' ', t.semester)           AS term,
    b.block_code,
    sub.subject_code,
    sub.subject_title,
    sub.units,
    o.section_code,
    cs.day_of_week,
    TIME_FORMAT(cs.start_time, '%h:%i %p')           AS start_time,
    TIME_FORMAT(cs.end_time,   '%h:%i %p')           AS end_time,
    cs.class_type,
    r.room_code,
    e.status                                         AS enrollment_status
FROM enrollments e
JOIN students s            ON s.student_id    = e.student_id
JOIN academic_terms t      ON t.term_id       = e.term_id
LEFT JOIN blocks b         ON b.block_id      = e.block_id
JOIN enrollment_details ed ON ed.enrollment_id = e.enrollment_id
JOIN subject_offerings o   ON o.offering_id   = ed.offering_id
JOIN subjects sub          ON sub.subject_id  = o.subject_id
JOIN class_schedules cs    ON cs.offering_id  = o.offering_id
JOIN rooms r               ON r.room_id       = cs.room_id;


-- =====================================================================
-- PART 5: STORED PROCEDURE
-- Adds a subject (section) to a student's enrollment.
-- It checks Business Rule 3: a Regular student may only take sections
-- of their own block, while an Irregular student may take any section
-- offered in the same term. The capacity check (Business Rule 5) is
-- done by the trg_check_section_capacity trigger.
-- =====================================================================

DELIMITER $$

CREATE PROCEDURE sp_add_subject(
    IN p_enrollment_id INT,
    IN p_offering_id   INT
)
BEGIN
    DECLARE v_class       VARCHAR(10);
    DECLARE v_enr_block   INT;
    DECLARE v_enr_term    INT;
    DECLARE v_off_block   INT;
    DECLARE v_off_term    INT;

    SELECT s.classification, e.block_id, e.term_id
      INTO v_class, v_enr_block, v_enr_term
      FROM enrollments e
      JOIN students s ON s.student_id = e.student_id
     WHERE e.enrollment_id = p_enrollment_id;

    IF v_class IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Enrollment record not found.';
    END IF;

    SELECT block_id, term_id
      INTO v_off_block, v_off_term
      FROM subject_offerings
     WHERE offering_id = p_offering_id;

    IF v_off_term IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Section not found.';
    END IF;

    IF v_off_term <> v_enr_term THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This section is not offered in the student''s enrollment term.';
    END IF;

    IF v_class = 'Regular' AND (v_off_block IS NULL OR v_off_block <> v_enr_block) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Regular students can only take sections of their assigned block.';
    END IF;

    INSERT INTO enrollment_details (enrollment_id, offering_id)
    VALUES (p_enrollment_id, p_offering_id);

    -- Show the student's updated schedule
    SELECT subject_code, section_code, day_of_week, start_time, end_time, room_code
      FROM vw_student_schedules
     WHERE enrollment_id = p_enrollment_id
     ORDER BY FIELD(day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat'), start_time;
END$$

DELIMITER ;


-- =====================================================================
-- PART 6: THE FIVE SQL QUERIES
-- Run each one separately in phpMyAdmin's SQL tab and screenshot the
-- statement with its output.
-- =====================================================================

-- Query 1: List all students in alphabetical order, with their program
-- and classification.
SELECT s.student_id,
       CONCAT(s.last_name, ', ', s.first_name, ' ', s.middle_name) AS student_name,
       p.program_code,
       s.year_level,
       s.classification
FROM students s
JOIN programs p ON p.program_id = s.program_id
ORDER BY s.last_name, s.first_name;

-- Query 2: Display enrollments that are not yet fully enrolled
-- (Pending or Verified), with the student name and status.
SELECT e.enrollment_id,
       CONCAT(s.last_name, ', ', s.first_name) AS student_name,
       s.classification,
       IFNULL(b.block_code, 'No block (irregular)') AS block,
       e.enrollment_date,
       e.status
FROM enrollments e
JOIN students s     ON s.student_id = e.student_id
LEFT JOIN blocks b  ON b.block_id   = e.block_id
WHERE e.status IN ('Pending', 'Verified')
ORDER BY e.enrollment_date, student_name;

-- Query 3: Daily enrollment total for the active term.
SELECT e.enrollment_date,
       COUNT(*)                          AS total_enrollments,
       SUM(s.classification = 'Regular')   AS regular_students,
       SUM(s.classification = 'Irregular') AS irregular_students
FROM enrollments e
JOIN students s       ON s.student_id = e.student_id
JOIN academic_terms t ON t.term_id    = e.term_id
WHERE t.is_active = TRUE
GROUP BY e.enrollment_date
ORDER BY e.enrollment_date;

-- Query 4: Section capacity report, showing enrolled students and
-- remaining slots for each section.
SELECT o.section_code,
       sub.subject_code,
       IFNULL(b.block_code, 'Open section') AS block,
       o.max_capacity,
       o.current_enrolled,
       o.max_capacity - o.current_enrolled AS remaining_slots,
       IF(o.current_enrolled >= o.max_capacity, 'FULL', 'Open') AS section_status
FROM subject_offerings o
JOIN subjects sub  ON sub.subject_id = o.subject_id
LEFT JOIN blocks b ON b.block_id     = o.block_id
ORDER BY remaining_slots, o.section_code;

-- Query 5: Class schedules of irregular students.
SELECT student_name, subject_code, section_code, day_of_week,
       start_time, end_time, room_code
FROM vw_student_schedules
WHERE classification = 'Irregular'
ORDER BY student_name, FIELD(day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat');


-- =====================================================================
-- PART 7: DEMO COMMANDS FOR SCREENSHOTS
-- These are commented out so the import doesn't stop on the expected
-- errors. Remove the "-- " and run them one at a time in the SQL tab.
-- =====================================================================

-- (a) VIEW: show Ana's full schedule
-- SELECT * FROM vw_student_schedules WHERE student_id = '2023-00123';

-- (b) STORED PROCEDURE, success: add the IT 117 open section to Paolo (irregular)
-- CALL sp_add_subject(5005, 102);

-- (c) STORED PROCEDURE, rejected: a Regular student tries an open section
--     Expected error: Regular students can only take sections of their assigned block.
-- CALL sp_add_subject(5006, 102);

-- (d) TRIGGER, rejected: IT118-OPEN already has 2 of 2 students
--     Expected error: This section is already full. Choose another section.
-- INSERT INTO enrollment_details (enrollment_id, offering_id) VALUES (5004, 106);

-- (e) TRIGGER, count updates: check current_enrolled before and after (b)
-- SELECT section_code, max_capacity, current_enrolled FROM subject_offerings WHERE offering_id = 102;

-- (f) TRIGGER, rejected: a Regular student with no block
--     Expected error: A Regular student must be assigned to a block.
-- INSERT INTO enrollments (student_id, term_id, block_id) VALUES ('2023-00250', 2, NULL);

-- (g) Outdated COR check: a COR printed before the enrollment last changed
-- SELECT c.cor_number, c.printed_at, e.last_modified,
--        IF(c.printed_at < e.last_modified, 'OUTDATED - reprint', 'Valid') AS cor_status
-- FROM cor_records c JOIN enrollments e ON e.enrollment_id = c.enrollment_id;
