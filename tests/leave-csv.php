<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/services/LeaveCsvService.php';

$applications = [];
foreach (['Casual Leave', 'Leave on Medical Ground', 'Child Care Leave', 'Maternity Leave', 'Study Leave', 'Casual Leave'] as $name) {
    $applications[] = ['leave_type' => $name, 'status' => 'Approved', 'start_date' => '2026-01-02', 'end_date' => '2026-01-03', 'days_count' => 1.5];
}
$applications[] = ['leave_type' => 'Casual Leave', 'status' => 'Approval Pending', 'start_date' => '2026-02-01', 'end_date' => '2026-02-02', 'days_count' => 2];
$applications[] = array_merge($applications[6], ['status' => 'Rejected']);
$stream = fopen('php://temp', 'w+');
(new LeaveCsvService())->write($stream, ['fname' => '=FORMULA()', 'lname' => 'Name, "Test"', 'designation' => '@Principal'], $applications);
rewind($stream);
if (fread($stream, 3) !== "\xEF\xBB\xBF") throw new RuntimeException('Missing UTF-8 BOM');
$rows = [];
while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) $rows[] = $row;
fclose($stream);
if (count($rows) !== 5 || array_unique(array_map('count', $rows)) !== [15]) throw new RuntimeException('Incorrect CSV dimensions');
if ($rows[0][1] !== "'=FORMULA() Name, \"Test\"" || $rows[0][10] !== "'@Principal") throw new RuntimeException('Unsafe spreadsheet cells');
foreach ([0, 3, 6, 9, 12] as $column) {
    if ($rows[3][$column] !== '2026-01-02' || $rows[3][$column + 2] !== '1.5') throw new RuntimeException('Leave category missing');
}
if ($rows[4][0] !== '2026-01-02' || $rows[4][3] !== '') throw new RuntimeException('Uneven categories incorrectly padded');
$stream = fopen('php://temp', 'w+');
(new LeaveCsvService())->write($stream, ['fname' => 'No Leave'], []);
rewind($stream);
$empty = stream_get_contents($stream);
fclose($stream);
if (substr_count($empty, "\r\n") !== 4) throw new RuntimeException('Empty register missing headings or blank row');
echo "CSV checks passed: five categories, approved only, padding, decimals, escaping and empty register.\n";
