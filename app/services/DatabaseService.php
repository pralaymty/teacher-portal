<?php

declare(strict_types=1);

class DatabaseService
{
    private mysqli $conn;

    public function __construct()
    {
        $config = appConfig()['db'];
        $this->conn = new mysqli(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['name'],
            (int) $config['port']
        );

        if ($this->conn->connect_error) {
            throw new RuntimeException('Database connection failed: ' . $this->conn->connect_error);
        }

        $this->conn->set_charset($config['charset']);
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->prepare($sql, $params);
        if (!$stmt->execute()) {
            throw new RuntimeException('SQL execution failed: ' . $stmt->error);
        }
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql, $params);
        if (!$stmt->execute()) {
            throw new RuntimeException('SQL execution failed: ' . $stmt->error);
        }
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->prepare($sql, $params);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getLastInsertId(): int
    {
        return (int) $this->conn->insert_id;
    }

    public function fetchColumnList(string $sql): array
    {
        $result = $this->conn->query($sql);
        if ($result === false) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_array()) {
            $rows[] = (string) $row[0];
        }

        return $rows;
    }

    private function prepare(string $sql, array $params = []): mysqli_stmt
    {
        // support named parameters like :key by converting them to ? and
        // reordering $params accordingly when an associative array is passed
        if ($params !== [] && $this->isAssoc($params)) {
            // find all named placeholders in order
            if (preg_match_all('/:(\w+)/', $sql, $matches)) {
                $ordered = [];
                foreach ($matches[1] as $name) {
                    if (!array_key_exists($name, $params)) {
                        throw new RuntimeException('Missing SQL parameter: ' . $name);
                    }
                    $ordered[] = $params[$name];
                    // replace the first occurrence only to preserve repeated params
                    $sql = preg_replace('/:' . preg_quote($name, '/') . '/', '?', $sql, 1);
                }
                $params = $ordered;
            }
        }

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('SQL prepare failed: ' . $this->conn->error);
        }

        if ($params !== []) {
            $types = '';
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } elseif (is_bool($value)) {
                    $types .= 'i';
                    $params[$key] = (int) $value;
                } else {
                    $types .= 's';
                }
            }
            // bind_param requires references
            $refs = [];
            foreach ($params as $k => $v) {
                $refs[$k] = &$params[$k];
            }
            array_unshift($refs, $types);
            $stmt->bind_param(...$refs);
        }

        return $stmt;
    }

    private function isAssoc(array $arr): bool
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
