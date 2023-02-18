<?php

declare(strict_types=1);

return [
    'uri' => 'http://127.0.0.1:2379',
    'version' => 'v3beta',
    'retry_interval' => 5,
    'path_prefix' => '/micro/registry',
    'framework' => 'go-micro',
    'options' => [
        'timeout' => 10,
    ],
];