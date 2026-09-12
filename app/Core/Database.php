<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper. Every query in the application goes through here and
 * every value is bound - no SQL string concatenation of user input anywhere.
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $transactionDepth = 0;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self(self::makeConnection(Config::get('database', [])));
        }
        return self::$instance;
    }

    public static function isInitialised(): bool
    {
        return self::$instance !== null;
    }

    public static function swap(?Database $db): void
    {
        self::$instance = $db;
    }

    /** @param array<string,mixed> $config */
    public static function fromConfig(array $config): Database
    {
        return new self(self::makeConnection($config));
    }

    /** @param array<string,mixed> $config */
    private static function makeConnection(array $config): PDO
    {
        $driver   = (string) ($config['driver'] ?? 'mysql');
        $host     = (string) ($config['host'] ?? '127.0.0.1');
        $port     = (string) ($config['port'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
        $charset  = (string) ($config['charset'] ?? 'utf8mb4');
        $socket   = (string) ($config['socket'] ?? '');

        if ($socket !== '') {
            $dsn = sprintf('%s:unix_socket=%s;dbname=%s;charset=%s', $driver, $socket, $database, $charset);
        } else {
            $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $driver, $host, $port, $database, $charset);
        }

        try {
            $pdo = new PDO(
                $dsn,
                (string) ($config['username'] ?? ''),
                (string) ($config['password'] ?? ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode());
        }

        $pdo->exec("SET NAMES '{$charset}'");
        $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

        return $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<string|int,mixed> $bindings */
    public function run(string $sql, array $bindings = []): PDOStatement
    {
        [$sql, $bindings] = $this->expandRepeatedPlaceholders($sql, $bindings);

        $statement = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $statement->bindValue($param, $value, $type);
        }
        $statement->execute();
        return $statement;
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array<int,array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll();
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string|int,mixed> $bindings */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->run($sql, $bindings)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map(fn ($c) => '`' . $this->quoteIdentifier($c) . '`', $columns)),
            implode(', ', array_map(fn ($c) => ':' . $c, $columns))
        );
        $this->run($sql, $this->prefixKeys($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = '`' . $this->quoteIdentifier($column) . '` = :d_' . $column;
        }
        $conditions = [];
        foreach (array_keys($where) as $column) {
            $conditions[] = '`' . $this->quoteIdentifier($column) . '` = :w_' . $column;
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $sets),
            implode(' AND ', $conditions)
        );
        $bindings = [];
        foreach ($data as $k => $v) {
            $bindings['d_' . $k] = $v;
        }
        foreach ($where as $k => $v) {
            $bindings['w_' . $k] = $v;
        }
        return $this->run($sql, $bindings)->rowCount();
    }

    /** @param array<string,mixed> $where */
    public function delete(string $table, array $where): int
    {
        $conditions = [];
        foreach (array_keys($where) as $column) {
            $conditions[] = '`' . $this->quoteIdentifier($column) . '` = :' . $column;
        }
        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $this->quoteIdentifier($table),
            implode(' AND ', $conditions)
        );
        return $this->run($sql, $this->prefixKeys($where))->rowCount();
    }

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT trans' . $this->transactionDepth);
        }
        $this->transactionDepth++;
    }

    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec('RELEASE SAVEPOINT trans' . $this->transactionDepth);
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } else {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactionDepth);
        }
    }

    /**
     * Run a closure inside a transaction, rolling back on any exception.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        $row = $this->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
        return (int) ($row['c'] ?? 0) > 0;
    }

    public function columnExists(string $table, string $column): bool
    {
        $row = $this->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );
        return (int) ($row['c'] ?? 0) > 0;
    }

    /** @return array<int,string> */
    public function tables(): array
    {
        $rows = $this->select(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = "BASE TABLE" ORDER BY table_name'
        );
        return array_map(static fn ($r) => (string) $r['t'], $rows);
    }

    public function databaseName(): string
    {
        return (string) $this->scalar('SELECT DATABASE()');
    }

    /**
     * Real (non-emulated) prepared statements require one placeholder per
     * bound value, so a query that reuses `:search` in several conditions
     * would fail. This rewrites the repeats to unique names and duplicates
     * the binding, keeping every query fully parameterised.
     *
     * @param array<string|int,mixed> $bindings
     * @return array{0:string,1:array<string|int,mixed>}
     */
    private function expandRepeatedPlaceholders(string $sql, array $bindings): array
    {
        if ($bindings === [] || array_is_list($bindings)) {
            return [$sql, $bindings];
        }

        $expanded = $bindings;

        foreach ($bindings as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            $name = ltrim((string) $key, ':');
            if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                continue;
            }

            $pattern = '/:' . preg_quote($name, '/') . '\b/';
            if (preg_match_all($pattern, $sql) <= 1) {
                continue;
            }

            $occurrence = 0;
            $sql = (string) preg_replace_callback(
                $pattern,
                static function () use (&$occurrence, &$expanded, $name, $value): string {
                    $occurrence++;
                    if ($occurrence === 1) {
                        return ':' . $name;
                    }
                    $alias = $name . '__r' . $occurrence;
                    $expanded[$alias] = $value;
                    return ':' . $alias;
                },
                $sql
            );
        }

        return [$sql, $expanded];
    }

    /**
     * Identifiers never come from user input, but they are still filtered so a
     * programming mistake cannot become an injection point.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $identifier) ?? '';
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function prefixKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[(string) $key] = $value;
        }
        return $out;
    }
}
