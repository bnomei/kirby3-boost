<?php

return [
    // 'debug' => true,
    'api' => [
        'basicAuth' => true,
        'allowInsecure' => false,
    ],
    'bnomei.boost.cache' => [
        'type'     => 'sqlite',
        'prefix'   => 'boost',
    ],
];
