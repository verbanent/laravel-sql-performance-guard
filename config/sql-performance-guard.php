<?php

return [
    'time_threshold' => env('SQL_TIME_THRESHOLD', 100.00),
    'key_length_threshold' => env('SQL_KEY_LENGTH_THRESHOLD', 256),
    'rows_threshold' => env('SQL_ROWS_THRESHOLD', 1000),
];
