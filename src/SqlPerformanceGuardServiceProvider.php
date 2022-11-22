<?php

namespace Verbanent\SqlPerformanceGuard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class SqlPerformanceGuardServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sql-performance-guard.php', 'sql-performance-guard');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/sql-performance-guard.php' => config_path('sql-performance-guard.php'),
        ], 'config');

        DB::listen(function ($query) {
            if (!Str::startsWith($query->sql, 'EXPLAIN')) {
                $bindings = $query->bindings;

                foreach ($bindings as $key => $param) {
                    if (is_string($param)) {
                        $bindings[$key] = "'{$param}'";
                    }

                    if ($param === null) {
                        $bindings[$key] = 'null';
                    }
                }

                $sqlWithBindings = Str::replaceArray('?', $bindings, $query->sql);
                Log::debug(
                    $query->sql,
                    [
                        'bindings' => $query->bindings,
                        'time (ms)' => $query->time,
                    ],
                );

                if (Str::startsWith($query->sql, 'select')) {
                    Log::debug('');
                    Log::debug('=============== EXPLAIN BEGIN ===============');
                    Log::debug('SQL', ['sql' => $sqlWithBindings]);
                    $explain = array_map(fn($row) => (array)$row, DB::select('EXPLAIN ' . $sqlWithBindings));

                    foreach ($explain as $row) {
                        Log::debug('');
                        Log::debug('TABLE ' . $row['key'] . ': ' . $row['table'], $row);
                        Log::debug($query->time < config('sql-performance-guard.time_threshold') ? 'PASSED: TIME' : 'WARNING: TIME', [$query->time < config('sql-performance-guard.time_threshold') ? 'time < 100.00' : 'time >= 100.00' => $query->time]);
                        Log::debug($row['possible_keys'] !== null ? 'PASSED: POSSIBLE KEYS' : 'WARNING: POSSIBLE KEYS', [$row['possible_keys'] !== null ? 'possible keys exist' : 'possible keys are null' => $row['possible_keys']]);
                        Log::debug($row['key'] !== null ? 'PASSED: KEY' : 'WARNING: KEY', [$row['key'] !== null ? 'key chosen' : 'key is null' => $row['key']]);
                        Log::debug($row['key_len'] !== null ? 'PASSED: KEY LEN' : 'WARNING: KEY LEN', [$row['key_len'] !== null ? 'key len is not null' : 'key len is null' => $row['key_len']]);
                        Log::debug($row['key_len'] !== null && $row['key_len'] < config('sql-performance-guard.key_length_threshold') ? 'PASSED: KEY LEN VALUE' : 'WARNING: KEY LEN VALUE', [$row['key_len'] !== null && $row['key_len'] < config('sql-performance-guard.key_length_threshold') ? 'key len < 256' : 'key len >= 256' => $row['key_len']]);
                        Log::debug($row['rows'] < config('sql-performance-guard.rows_threshold') ? 'PASSED: ROWS' : 'WARNING: ROWS', [$row['rows'] < config('sql-performance-guard.rows_threshold') ? 'rows < 1000' : 'rows >= 1000' => $row['rows']]);
                    }

                    Log::debug('=============== EXPLAIN = END ===============');
                    Log::debug('');
                }
            }
        });
    }
}
