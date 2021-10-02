<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\BoostCache;
use Bnomei\CacheBenchmark;
use Kirby\Cache\FileCache;
use PHPUnit\Framework\TestCase;

final class CacheBenchmarkTest extends TestCase
{
    public function testCreateWillAddBoostId()
    {
        $caches = [
            // BoostCache::apcu(),
            BoostCache::file(),
        ];

        $results = CacheBenchmark::run($caches, 1, 10);
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals(FileCache::class, array_keys($results['results'])[0]);
    }
}
