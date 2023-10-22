<?php

require_once __DIR__.'/../vendor/autoload.php';

use Bnomei\BoostCache;
use Bnomei\CacheBenchmark;
use Kirby\Cache\FileCache;

test('create will add boost id', function () {
    $caches = [
        // BoostCache::apcu(),
        BoostCache::file(),
    ];

    $results = CacheBenchmark::run($caches, 1, 10);
    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);
    expect(array_keys($results['results'])[0])->toEqual(FileCache::class);
});
