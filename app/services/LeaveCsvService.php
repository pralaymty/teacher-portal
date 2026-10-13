<?php

declare(strict_types=1);

class LeaveCsvService
{
    /** Write the staff leave register as an Excel-compatible, fifteen-column CSV. */
    public function write($stream, array $user, array $applications): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        $staff = array_fill(0, 15, '');
        $staff[0] = 'NAME OF THE STAFF';
        $staff[1] = getUserFullName($user);
        $staff[9] = 'DESIGNATION';
        $staff[10] = (string) ($user['designation'] ?? '');
        $this->row($stream, $staff);
        $headings = [];
        $columns = [];
        foreach (['CASUAL LEAVE', 'LEAVE ON MEDICAL GROUND', 'CHILD CARE LEAVE', 'MATERNITY LEAVE', 'OTHER LEAVE'] as $heading) {
            array_push($headings, $heading, '', '');
            array_push($columns, 'From', 'To', 'No. of Days');
        }
        $this->row($stream, $headings);
        $this->row($stream, $columns);
        $groups = array_fill(0, 5, []);
        foreach ($applications as $application) {
            if ($application['status'] !== 'Approved') {
                continue;
            }
            $groups[$this->category((string) ($application['leave_type'] ?? ''))][] = [
                (string) $application['start_date'],
                (string) $application['end_date'],
                rtrim(rtrim(number_format((float) $application['days_count'], 1, '.', ''), '0'), '.'),
            ];
        }
        $rowCount = max(1, ...array_map('count', $groups));
        for ($index = 0; $index < $rowCount; $index++) {
            $row = [];
            foreach ($groups as $group) {
                array_push($row, ...($group[$index] ?? ['', '', '']));
            }
            $this->row($stream, $row);
        }
    }

    private function category(string $name): int
    {
        $name = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        // Use names rather than ambiguous codes such as ML (medical or maternity).
        $categories = [
            'casual' => 0, 'casual leave' => 0,
            'medical' => 1, 'medical leave' => 1, 'leave on medical ground' => 1,
            'leave on medical grounds' => 1, 'sick leave' => 1,
            'child care leave' => 2, 'childcare leave' => 2, 'child care' => 2,
            'maternity' => 3, 'maternity leave' => 3,
        ];
        return $categories[$name] ?? 4;
    }

    private function row($stream, array $values): void
    {
        $values = array_map(static function (string $value): string {
            // Keep spreadsheet programs from evaluating user-controlled cells as formulas.
            return preg_match('/^[\s\x{FEFF}]*[=+@-]|^[\t\r\n]/u', $value) ? "'" . $value : $value;
        }, $values);
        // The custom line-ending argument is only available from PHP 8.1.
        // Encode a row separately to preserve CRLF output on PHP 7.4 as well.
        $rowStream = fopen('php://temp', 'w+');
        if ($rowStream === false) {
            throw new RuntimeException('Could not create the CSV row buffer.');
        }
        try {
            if (fputcsv($rowStream, $values, ',', '"', '') === false) {
                throw new RuntimeException('Could not encode the CSV row.');
            }
            rewind($rowStream);
            $encoded = stream_get_contents($rowStream);
            if ($encoded === false) {
                throw new RuntimeException('Could not read the CSV row.');
            }
            fwrite($stream, substr($encoded, 0, -1) . "\r\n");
        } finally {
            fclose($rowStream);
        }
    }
}
