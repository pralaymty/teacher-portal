<?php

declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/services/DatabaseService.php';

$source = file_get_contents(__DIR__ . '/../profile.php');
if (!preg_match('/UPDATE user SET [^\r\n\x27]+/', $source, $match)) {
    throw new RuntimeException('Profile update query missing.');
}
if (strpos($source, "\$user['phone']") === false || strpos($source, "\$user['mobile']") !== false) {
    throw new RuntimeException('Mobile input must read the phone column.');
}

$db = new DatabaseService();
// Verify the real column names, then test writes only against a temporary table.
$db->fetchAll('SELECT id, dob, designation, academic_qualification, date_of_joining, subject, email, phone FROM user WHERE 1 = 0');
$db->execute('CREATE TEMPORARY TABLE user (
    id INT PRIMARY KEY, dob DATE, designation VARCHAR(100), academic_qualification VARCHAR(255),
    date_of_joining DATE, subject VARCHAR(100), email VARCHAR(255), phone VARCHAR(30)
)');
$db->execute("INSERT INTO user (id, phone) VALUES (1, '0123456789'), (2, '0987654321')");
$values = ['1990-01-02', 'Teacher', 'MA', '2020-03-04', 'English', 'profile@example.test', '0012345678', 1];
if (!$db->execute($match[0], $values)) {
    throw new RuntimeException('Profile update failed.');
}
$saved = $db->fetchOne('SELECT dob, designation, academic_qualification, date_of_joining, subject, email, phone FROM user WHERE id = ?', [1]);
if (array_values($saved) !== array_slice($values, 0, 7)) {
    throw new RuntimeException('Saved profile values do not match.');
}
if ($db->fetchOne('SELECT phone FROM user WHERE id = ?', [2])['phone'] !== '0987654321') {
    throw new RuntimeException('Another user was modified.');
}
echo "Profile column mapping, saved fields, and user isolation checks passed (temporary table only).\n";
