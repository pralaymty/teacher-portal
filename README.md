# Teachers Employee Portal

## Project description
This project is a Core PHP 7.4 employee portal for teachers built on top of the existing `nnv_admin` MySQL database. It is designed to provide attendance, leave management, profile data, admin dashboard, and settings while reusing the existing `user` table and role-based permissions.

## Technology stack
- PHP 7.4
- Core PHP
- MySQL
- Bootstrap 5
- HTML5
- CSS3
- Vanilla JavaScript
- jQuery (only where helpful for AJAX interactions)

## Requirements
- PHP 7.4
- MySQL Server
- Web server such as Apache or Nginx
- Browser with geolocation support

## Installation instructions
1. Place the project in a folder named `teacher-portal` inside the current workspace root.
2. Configure your PHP 7.4 environment and MySQL database.
3. Import or use the existing `nnv_admin` database.
4. Ensure the web server points to the project root.
5. Open the portal in a browser and log in using a valid teacher or admin user account.

## Database configuration
Update the database settings in `app/config.php` if needed.

Default configuration:
- Database name: `nnv_admin`
- Host: `127.0.0.1`
- Port: `3306`
- User: `root`
- Password: empty

## Existing database dependency
This portal reuses the existing `nnv_admin` database and the current `user` table. The application uses the existing `user_type` values:
- `1` = Master Admin
- `3` = Teacher
- `6` = Developer/Admin

## New tables created
The new portal creates only the minimum required tables in `database/teacher_portal_tables.sql`:
- `teacher_attendance`
- `teacher_leave_types`
- `teacher_leave_applications`
- `teacher_leave_history`
- `teacher_notifications`
- `teacher_settings`

## User roles
- Teachers (`user_type = 3`) access the teacher dashboard and features.
- Master Admin (`user_type = 1`) access the full admin dashboard and settings.
- Developer/Admin (`user_type = 6`) access the same admin-level areas.

## Attendance functionality
- Teachers can mark themselves present once per day.
- Geo-location is collected only when marking attendance.
- Attendance is saved with date, time, latitude, longitude, and timestamps.
- Duplicate entries for the same user and date are blocked.

## Leave functionality
- Teachers can apply leave.
- Leave types are configurable via the database.
- Leave application status includes Approval Pending, Approved, and Rejected.
- Special approval explanations are limited to 200 characters.

## SMTP configuration
SMTP settings are not hard-coded. Configure the email settings in the application settings area or by setting environment variables.

## Geolocation requirement
The attendance action requires browser geolocation permission. Without permission, attendance cannot be recorded from the client side.

## Deployment instructions
1. Ensure PHP 7.4 is installed and configured.
2. Ensure the MySQL database is available and accessible.
3. Copy the project to your hosting environment.
4. Ensure `session` and `file` permissions are correct.
5. Add the project to your web root and configure the virtual host.
6. Test teacher login, admin login, attendance, leave submissions, and settings management.
