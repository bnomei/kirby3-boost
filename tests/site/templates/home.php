<?php

echo 'file: ' . \Bnomei\CacheBenchmark::file(2) . PHP_EOL; // seconds
echo 'memc: ' . \Bnomei\CacheBenchmark::memcached(2) . PHP_EOL; // seconds
// or just
// echo \Bnomei\CacheBenchmark::fastest() . PHP_EOL;