BICOL UNIVERSITY ENROLLMENT MANAGEMENT SYSTEM
IT116 - Information Management 2 | Final Project (PHP + MySQL, runs on XAMPP)
=============================================================================

SETUP
-----
1. Copy this whole "bu_enrollment" folder into C:\xampp\htdocs\
   (so you have C:\xampp\htdocs\bu_enrollment\index.php).
2. Open the XAMPP Control Panel and Start Apache and MySQL.
3. Make sure the bu_enrollment database exists in phpMyAdmin
   (http://localhost/phpmyadmin). Use your groupmate's database file.
   No database yet? Import sample_database_for_testing.sql from this folder
   to try the app. It rebuilds bu_enrollment with sample data.
4. Open http://localhost/bu_enrollment in your browser.
5. Log in as a registrar (pick a staff account), or open the Student Portal.
   Sample portal login: Student ID 2023-00123, email ana.santos@bicol-u.edu.ph

If your MySQL has a password, or the database has a different name,
change them in config/db.php.


WHAT THE APP NEEDS FROM THE DATABASE
------------------------------------
The app uses the table and column names from our Week 2 data dictionary:
programs, students, academic_terms, subjects, blocks, rooms,
subject_offerings, class_schedules, registrar_staff, enrollments,
enrollment_details, cor_records, verification_requests.

It also expects:
- One row in academic_terms with is_active = TRUE (the current term).
- Stored procedure sp_add_subject(enrollment_id, offering_id).
  If it doesn't exist, the app does the same checks in PHP instead.
- Triggers on enrollment_details that add 1 to / subtract 1 from
  subject_offerings.current_enrolled when a section is added or dropped,
  and block adding to a full section. WITHOUT these triggers the
  "Enrolled" and "Slots left" numbers will not update.


PAGES (matches the Expected System Workflow)
--------------------------------------------
login.php            Registrar login
index.php            Dashboard: counts by status, open requests, full sections
students.php         Student list (A-Z) with search and filter       -> Step 1
student_form.php     Register / edit a student                       -> Step 1
enrollment_new.php   Create an enrollment (status Pending). Regular
                     students get all their block's sections
                     automatically                                   -> Steps 2-3
enrollment_view.php  Add / drop sections (capacity is checked),
                     Verify, Mark as Enrolled                        -> Steps 3-5
cor.php              Print the COR; saves COR number, date, staff     -> Step 6
portal.php           Student portal: schedule, status, COR status,
                     report a problem                                -> Step 7
requests.php         Registrar resolves student reports              -> Step 8
sections.php         Section capacity and remaining slots
reports.php          Daily enrollment total, block/section capacity,
                     irregular students' schedules, pending verification


SCREENSHOTS FOR WEEK 3
----------------------
 1. Student list, and the Register Student form
 2. New Enrollment form (regular student with a block)
 3. Enrollment page after the block sections were added automatically
 4. Adding a section for an irregular student
 5. Adding a FULL section (IT118-OPEN): red "section is already full"
    message (this is the trigger working)
 6. Sections page before and after adding a student (count changes)
 7. Verify Enrollment, then Mark as Enrolled
 8. Printed COR
 9. Student Portal: schedule and status, and a reported problem
10. Verification Requests: Mark Resolved
11. Each report on the Reports page
12. In phpMyAdmin: the five SQL queries, the view, the stored procedure,
    and the trigger, each with its output
