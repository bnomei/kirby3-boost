<?php

// use helpers to generate caches to compare
$caches = [
    \Bnomei\BoostCache::file(),
    \Bnomei\BoostCache::apcu(),
    \Bnomei\BoostCache::memcached(),
    \Bnomei\BoostCache::memory(),
    \Bnomei\BoostCache::sqlite(),
    //\Bnomei\BoostCache::redis(),
];
// run the benchmark
var_dump(\Bnomei\CacheBenchmark::run($caches, 2, 500, 64));
