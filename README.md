# EduPulse LMS - Student Management System (Minor Project)

A complete, production-ready Learning Management System (LMS) with a focus on Student Management, designed for a 1-month college minor project.

## 🛠 Tech Stack
- **Frontend**: React 18 / Vite, Bootstrap 5 (`bootstrap`), Bootstrap Icons (`bootstrap-icons`), Axios
- **Backend**: PHP 8.x REST API using **`mysqli`** with prepared statements and CORS support
- **Database**: MySQL 8.x with 5 normalized tables (`students`, `courses`, `enrollments`, `attendance`, `announcements`)

---

## 🗄 Database Setup
1. Make sure WampServer / MySQL is running on `127.0.0.1:3306`.
2. Schema and seed data are located in `backend/database.sql`.
3. To import via MySQL CLI:
   ```bash
   mysql -u root -p < backend/database.sql
   ```
   *(Default configuration uses user `root` with no password, connecting to database `lms_student_db`).*

---

## 🚀 Running the Project

### 1. Start the PHP Backend API Server
Make sure the project folder is inside your WAMP `www` directory as `C:\wamp64\www\lms-student-management` and start **Apache** and **MySQL** in WAMP.

The backend API is served by WAMP/Apache at:
- Dashboard: `http://localhost/lms-student-management/backend/api/dashboard.php`
- Students: `http://localhost/lms-student-management/backend/api/students.php`
- Courses: `http://localhost/lms-student-management/backend/api/courses.php`
- Enrollments: `http://localhost/lms-student-management/backend/api/enrollments.php`
- Attendance: `http://localhost/lms-student-management/backend/api/attendance.php`
- Notices: `http://localhost/lms-student-management/backend/api/announcements.php`

### 2. Start the React Frontend
In a terminal inside `frontend/`:
```bash
npm install
npm run dev
```
Open your browser at:
`http://localhost:5173`

---

## 🌟 Key Features
- **Dashboard**: Live counters (Students, Courses, Enrollments, Attendance), Department distribution progress bars, Latest enrollments and urgent notices feed.
- **Students Directory**:
  - Full CRUD operations with modal popups.
  - Search by student name, roll code, or email.
  - Filter by department and status (Active, Inactive, Suspended).
  - Detailed Student Academic Profile with enrolled courses, grades, and calculated attendance percentage.
- **Course Catalog**:
  - View all accredited courses with credit hours and instructors.
  - Create, edit, and delete courses.
  - Course Roster modal to inspect all students enrolled in any specific course.
- **Enrollments & Grading**:
  - Assign students to courses.
  - Assign and update letter grades (`A+` to `F`, or `Pending`).
  - Update status (`Enrolled`, `Completed`, `Dropped`).
- **Attendance Tracker**:
  - Select course and lecture date.
  - Batch mark attendance (`Present`, `Late`, `Absent`, `Excused`) with one-click "All Present" and "All Absent" shortcuts.
  - Atomic database transactions using MySQLi prepared statements.
  - Audit log table of all historical attendance entries.
- **Campus Notice Board**:
  - Publish official circulars, exam schedules, and deadlines.
  - Priority badges (`Urgent`, `High`, `Medium`, `Low`).

## Two Login System

This version includes separate Admin and User login pages with PHP session authentication.

### Default accounts
- Admin: `admin` / `Admin@123`
- User: `user` / `User@123`

### Access
- **Admin:** Dashboard, Students, Courses, Enrollments, Attendance, Notices; can create/update/delete management data.
- **User:** Dashboard, Courses and Notices in view mode.

Import `backend/database.sql` again in phpMyAdmin after this update so the `users` table and default accounts are created.

## Admin login fix for an existing database

The current project uses these demo credentials:

- Admin: `admin` / `Admin@123`
- User: `user` / `User@123`

If you already imported an older version of the database and Admin login still says "Invalid username, password or account role", open phpMyAdmin, select `lms_student_db`, open the SQL tab, and import/run `backend/fix_admin_login.sql` once. This updates only the Admin password hash and role/status; you do not need to delete the database.
