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
For live-server updates, select the existing portal database in phpMyAdmin and import **`database/all_updates.sql` only**. It includes all portal tables, the M/F gender upgrade, missing attendance coordinate columns, independent leave records per user type, historical leave references, and default settings. Existing `user` and `user_type` tables are required. Upload the updated PHP files along with this database upgrade.

The first gender upgrade initializes existing users to `M`. Re-imports preserve later M/F changes, saved role quotas, attendance windows, school location, and selected user types. School coordinates are configured through Settings, not supplied by the SQL file. The import uses a temporary stored procedure, so the importing database account needs CREATE ROUTINE and ALTER privileges. The older `user_gender_migration.sql` is included in this consolidated upgrade and does not need to be imported separately.

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
- After today's attendance is marked, a Logoff button appears on the dashboard and the user's current calendar entry. Logoff stores a separate time and browser coordinates on that day's record, appears beside Login in both calendars, and does not sign the user out of the portal. Only one logoff is allowed per day, after login, within the role's exit window and school radius (super admin is exempt from time/distance restrictions).
- School Location settings define latitude, longitude, and an allowed distance in metres. Both attendance calendars calculate distance from saved coordinates using the current settings: green within range, bold red outside range, and grey when location data or school settings are missing. Time and distance are shown together, including attendance on holidays.
- Users other than the super admin can mark attendance only within their role's time windows AND the school's allowed distance. Missing school configuration blocks their attendance until configured. The super admin is exempt from distance and time restrictions, but still provides browser coordinates.
- Manual entries capture the super admin's current browser coordinates, including when the selected attendance date is in the past; these coordinates are not the teacher's historical location. Older entries without coordinates remain location unavailable. Browser location accuracy depends on the device; location is requested only when marking attendance.
- Attendance Settings lists roles from `user_type` and saves separate entry/exit time windows for each role in `teacher_settings`.
- Both settings dropdowns initially list roles 3, 4, 5, 10, 11, and 12. Settings > Manage User Types controls this shared list; removing a role from the lists does not delete its users, leave records, or attendance configuration.
- Roles without a saved schedule use the existing general attendance windows.
- Master Admin can mark their own attendance at any time and add manual attendance with a selected date/time. Other users must mark within their role's windows.

## Leave functionality
- Teachers can apply leave.
- Leave types are configurable via the database.
- Leave Settings uses a user-type dropdown. Each leave record belongs to one role: adding, editing, deleting, and restoring affect only that role, including its name, code, gender restriction, and quota.
- Different roles can use the same leave name/code with different quotas. Deletion disables new applications only for the selected role and preserves historical records.
- The automatic `LeaveSettingsMigration` adds `teacher_leave_types.user_type_id` and role-scoped unique indexes. It copies old leave records into independent role records and relinks applications/history using each user's current role. Original shared records and old quota settings are retained.
- Old explicit quota assignments determine which roles keep a leave active. Legacy defaults without explicit assignments are copied to all existing roles; newly added roles start without leave entries.
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
