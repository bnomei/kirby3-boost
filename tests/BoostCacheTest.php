<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\BoostCache;
use PHPUnit\Framework\TestCase;

final class BoostCacheTest extends TestCase
{
    public function testCache()
    {
        $cache = BoostCache::singleton();
        // cli will have file cache
        $this->assertInstanceOf(\Kirby\Cache\FileCache::class, $cache);
    }

    public function testWriteAndRead()
    {
        $cache = BoostCache::singleton();
        $this->assertTrue($cache->set('a', 1));
        $this->assertEquals(1, $cache->get('a'));
    }

    public function testReadFromCache()
    {
        $cache = BoostCache::singleton();
        $this->assertEquals(1, $cache->get('a'));
        $this->assertEquals(null, $cache->get('b'));
    }

    public function testTearDown(): void
    {
        $cache = BoostCache::singleton();
        $this->assertTrue($cache->remove('a'));
    }
}
