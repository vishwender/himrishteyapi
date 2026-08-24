<?php

namespace App\Services\Api\V1;

use App\Models\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplicationDatabaseService
{
    private string $connectionName = 'application';

    /**
     * Connect to the database belonging to an application.
     */
    public function connect(Application $application): void
    {
        $baseConnection = config('database.default');

        $baseConfig = config(
            "database.connections.{$baseConnection}"
        );

        if (!$baseConfig) {
            throw new RuntimeException(
                "Database connection [{$baseConnection}] is not configured."
            );
        }

        /*
         * Override only the database name.
         *
         * Host, port, username, password, driver, etc.
         * remain the same as the Laravel application's
         * primary database connection.
         */
        $baseConfig['database'] = $application->database;

        Config::set(
            "database.connections.{$this->connectionName}",
            $baseConfig
        );

        /*
         * Important when switching between applications
         * during the lifetime of the application process.
         */
        DB::purge($this->connectionName);

        DB::reconnect($this->connectionName);

        /*
         * Force the connection now so connection errors are
         * caught here instead of later inside a controller.
         */
        DB::connection($this->connectionName)
            ->getPdo();
    }

    /**
     * Get the current application database connection.
     */
    public function connection()
    {
        return DB::connection($this->connectionName);
    }

    /**
     * Get the current database name.
     */
    public function databaseName(): string
    {
        return $this->connection()->getDatabaseName();
    }
}
