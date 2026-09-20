<?php

namespace Tests\Feature;

use Tests\TestCase;

class SqliteConnectionPragmaConfigTest extends TestCase
{
    private const ENV_KEYS = ['DB_BUSY_TIMEOUT', 'DB_JOURNAL_MODE', 'DB_SYNCHRONOUS'];

    protected function tearDown(): void
    {
        $this->clearPragmaEnv();

        parent::tearDown();
    }

    public function test_sqlite_pragmas_are_null_when_env_is_unset(): void
    {
        $this->clearPragmaEnv();

        $sqlite = $this->sqliteConnectionConfig();

        $this->assertNull($sqlite['busy_timeout']);
        $this->assertNull($sqlite['journal_mode']);
        $this->assertNull($sqlite['synchronous']);
    }

    public function test_sqlite_pragmas_are_null_when_env_values_are_empty_strings(): void
    {
        $this->setPragmaEnv(['DB_BUSY_TIMEOUT' => '', 'DB_JOURNAL_MODE' => '', 'DB_SYNCHRONOUS' => '']);

        $sqlite = $this->sqliteConnectionConfig();

        $this->assertNull($sqlite['busy_timeout']);
        $this->assertNull($sqlite['journal_mode']);
        $this->assertNull($sqlite['synchronous']);
    }

    public function test_sqlite_pragmas_pass_configured_values_through_including_zero(): void
    {
        $this->setPragmaEnv(['DB_BUSY_TIMEOUT' => '5000', 'DB_JOURNAL_MODE' => 'WAL', 'DB_SYNCHRONOUS' => '0']);

        $sqlite = $this->sqliteConnectionConfig();

        $this->assertSame('5000', $sqlite['busy_timeout']);
        $this->assertSame('WAL', $sqlite['journal_mode']);
        $this->assertSame('0', $sqlite['synchronous']);
    }

    /**
     * Re-evaluate config/database.php against the current process environment.
     *
     * @return array<string, mixed>
     */
    private function sqliteConnectionConfig(): array
    {
        $config = require base_path('config/database.php');

        return $config['connections']['sqlite'];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function setPragmaEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
        }
    }

    private function clearPragmaEnv(): void
    {
        foreach (self::ENV_KEYS as $key) {
            unset($_SERVER[$key], $_ENV[$key]);
        }
    }
}
