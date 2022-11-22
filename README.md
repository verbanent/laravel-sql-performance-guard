# Laravel SQL Performance Guard

A service provider to monitor SQL statements, check the performance (EXPLAIN command), view real-time queries, and collect SQL logs to optimise them.

## Installation

Please install the package via Composer:

```shell
composer require verbanent/laravel-sql-performance-guard
```

You can publish the config file if you need to change its default values:

```shell
php artisan vendor:publish --provider="Verbanent\SqlPerformanceGuard\SqlPerformanceGuardServiceProvider" --tag="config"
```

Default settings:

```php
<?php

return [
    'time_threshold' => env('SQL_TIME_THRESHOLD', 100.00),
    'key_length_threshold' => env('SQL_KEY_LENGTH_THRESHOLD', 256),
    'rows_threshold' => env('SQL_ROWS_THRESHOLD', 1000),
];
```

## Usage

Currently, the library works only in the debug mode. Please run it in your development
environment to test SQL queries and make required amends to improve the SQL performance.
If you see better results, apply these changes to your Production environment.

## Example

Install a package and check your logs:

```shell
tail -f storage/logs/laravel.log
```

Change your application mode to `DEBUG` by updating your `.env` file:

```shell
APP_ENV=local
APP_DEBUG=true
LOG_CHANNEL=single
LOG_LEVEL=debug
```

It's just an example. If you configured your logs you might not need to do any changes here. 

Open a page or run a CLI command to trigger saving debug logs. You should something like this:

```shell
=============== EXPLAIN BEGIN ===============
SQL {"sql":"select * from `users` where `email` = 'example@example.com' limit 1"}

Table 1 {"id":1,"select_type":"SIMPLE","table":"users","partitions":null,"type":"ALL","possible_keys":null,"key":null,"key_len":null,"ref":null,"rows":83289,"filtered":10.0,"Extra":"Using where"}
PASSED: TIME {"time < 100.00":66.73}
WARNING: POSSIBLE KEYS {"possible keys are null":null}
WARNING: KEY {"key is null":null}
WARNING: KEY LEN {"key len is null":null}
WARNING: KEY LEN VALUE {"key len >= 256":null}
WARNING: ROWS {"rows >= 1000":83289}
=============== EXPLAIN = END ===============
```

We see the problem is that the query doesn't use any indexes (keys) to filter results. It means it must go through more than 80.000 rows to get the results.

Based on this knowledge let's optimise our table `users` by adding an index:

```sql
create index email_idx on users (email)
```

It's better know:

```shell
=============== EXPLAIN BEGIN ===============
SQL {"sql":"select * from `users` where `email` = 'example@example.com' limit 1"}

Table 1 {"id":1,"select_type":"SIMPLE","table":"users","partitions":null,"type":"ref","possible_keys":"email_idx","key":"email_idx","key_len":"515","ref":"const","rows":1,"filtered":100.0,"Extra":null}
PASSED: TIME {"time < 100.00":17.88}
PASSED: POSSIBLE KEYS {"possible keys exist":"email_idx"}
PASSED: KEY {"key chosen":"email_idx"}
PASSED: KEY LEN {"key len is not null":"515"}
WARNING: KEY LEN VALUE {"key len >= 256":"515"}
PASSED: ROWS {"rows < 1000":1}
=============== EXPLAIN = END ===============
```

But still not perfect. There's the last warning related to the key length. It means that the column contains longer strings and our index might be huge if we don't limit it. Let's try to fix it based on that information:

```sql
drop index email_idx on users;
create index email_idx on users (email(32));
```

The results show all tests passed:

```shell
=============== EXPLAIN BEGIN ===============
SQL {"sql":"select * from `users` where `email` = 'example@example.com' limit 1"}

Table 1 {"id":1,"select_type":"SIMPLE","table":"users","partitions":null,"type":"ref","possible_keys":"email_idx","key":"email_idx","key_len":"131","ref":"const","rows":1,"filtered":100.0,"Extra":"Using where"}
PASSED: TIME {"time < 100.00":14.31}
PASSED: POSSIBLE KEYS {"possible keys exist":"email_idx"}
PASSED: KEY {"key chosen":"email_idx"}
PASSED: KEY LEN {"key len is not null":"131"}
PASSED: KEY LEN VALUE {"key len < 256":"131"}
PASSED: ROWS {"rows < 1000":1}
=============== EXPLAIN = END ===============
```

This is a simple tool to help you diagnose the issue with your queries based on the `EXPLAIN` command. It should be used during your developing process. We don't recommend running it in your Production environment.