<?php
declare(strict_types=1);

namespace Cms\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function bootstrap(array $config): void
    {
        $driver = (string) ($config['connection'] ?? 'sqlite');

        if ($driver !== 'sqlite') {
            throw new RuntimeException('Only SQLite is supported in the initial scaffold.');
        }

        $rootPath = (string) ($config['root_path'] ?? dirname(__DIR__, 2));
        $databasePath = self::resolvePath((string) ($config['database'] ?? 'storage/database/cms.sqlite'), $rootPath);
        $schemaPath = self::resolvePath((string) ($config['schema'] ?? 'database/schema.sql'), $rootPath);
        $migrationsPath = self::resolvePath((string) ($config['migrations_path'] ?? 'database/migrations'), $rootPath);

        $databaseDirectory = dirname($databasePath);

        if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0777, true) && !is_dir($databaseDirectory)) {
            throw new RuntimeException('Unable to create the database directory.');
        }

        $databaseExists = is_file($databasePath) && filesize($databasePath) > 0;

        try {
            self::$connection = new PDO('sqlite:' . $databasePath);
            self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$connection->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to open the SQLite database: ' . $exception->getMessage(), 0, $exception);
        }

        if (!$databaseExists || self::requiresSchema(self::$connection)) {
            if (!is_file($schemaPath)) {
                throw new RuntimeException('The SQLite schema file was not found.');
            }

            $schema = file_get_contents($schemaPath);

            if ($schema === false) {
                throw new RuntimeException('The SQLite schema file could not be read.');
            }

            self::$connection->exec($schema);
        }

        self::runMigrations(self::$connection, $migrationsPath);
    }

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            throw new RuntimeException('Database::bootstrap() must be called before requesting the connection.');
        }

        return self::$connection;
    }

    private static function resolvePath(string $path, string $rootPath): string
    {
        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\\\')) {
            return $path;
        }

        return rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private static function requiresSchema(PDO $connection): bool
    {
        $statement = $connection->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");

        return (int) $statement->fetchColumn() === 0;
    }

    private static function runMigrations(PDO $connection, string $migrationsPath): void
    {
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        if (!is_dir($migrationsPath)) {
            return;
        }

        $files = glob(rtrim($migrationsPath, '/\\') . DIRECTORY_SEPARATOR . '*.sql');

        if ($files === false) {
            throw new RuntimeException('Unable to read the migrations directory.');
        }

        sort($files, SORT_STRING);

        $selectMigration = $connection->prepare('SELECT 1 FROM migrations WHERE migration = :migration LIMIT 1');
        $recordMigration = $connection->prepare('INSERT INTO migrations (migration) VALUES (:migration)');

        foreach ($files as $filePath) {
            $migration = basename($filePath);
            $selectMigration->execute(['migration' => $migration]);

            if ($selectMigration->fetchColumn() !== false) {
                continue;
            }

            $sql = file_get_contents($filePath);

            if ($sql === false) {
                throw new RuntimeException('Migration file could not be read: ' . $migration);
            }

            $connection->beginTransaction();

            try {
                self::executeMigrationSql($connection, $sql);
                $recordMigration->execute(['migration' => $migration]);
                $connection->commit();
            } catch (PDOException $exception) {
                $connection->rollBack();

                throw new RuntimeException('Migration failed for ' . $migration . ': ' . $exception->getMessage(), 0, $exception);
            }
        }
    }

    private static function executeMigrationSql(PDO $connection, string $sql): void
    {
        $addColumnStatements = self::extractAddColumnStatements($sql);

        if ($addColumnStatements === null) {
            $connection->exec($sql);
            return;
        }

        foreach ($addColumnStatements as $statement) {
            if (self::columnExists($connection, $statement['table'], $statement['column'])) {
                continue;
            }

            $connection->exec($statement['sql']);
        }
    }

    private static function extractAddColumnStatements(string $sql): ?array
    {
        $normalizedSql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = preg_split('/;\s*(?:\R|$)/', $normalizedSql) ?: [];
        $parsedStatements = [];

        foreach ($statements as $statement) {
            $trimmedStatement = trim($statement);
            $matches = [];

            if ($trimmedStatement === '') {
                continue;
            }

            if (preg_match('/^ALTER\s+TABLE\s+"?([A-Za-z_][A-Za-z0-9_]*)"?\s+ADD\s+COLUMN\s+"?([A-Za-z_][A-Za-z0-9_]*)"?\b[\s\S]*$/i', $trimmedStatement, $matches) !== 1) {
                return null;
            }

            $parsedStatements[] = [
                'table' => $matches[1],
                'column' => $matches[2],
                'sql' => $trimmedStatement . ';',
            ];
        }

        return $parsedStatements;
    }

    private static function columnExists(PDO $connection, string $table, string $column): bool
    {
        $statement = $connection->query('PRAGMA table_info(' . self::quoteIdentifier($table) . ')');

        if ($statement === false) {
            return false;
        }

        while (($row = $statement->fetch()) !== false) {
            if ((string) ($row['name'] ?? '') === $column) {
                return true;
            }
        }

        return false;
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
