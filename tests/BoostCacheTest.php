<?php

require_once __DIR__.'/../vendor/autoload.php';

use Bnomei\BoostCache;

test('cache', function () {
    $cache = BoostCache::singleton();

    // cli will have file cache
    expect($cache)->toBeInstanceOf(\Kirby\Cache\FileCache::class);

    expect(option('debug'))->toBeFalse();
});
test('write and read', function () {
    $cache = BoostCache::singleton();
    expect($cache->set('a', 1))->toBeTrue();
    expect($cache->get('a'))->toEqual(1);
});
test('read from cache', function () {
    $cache = BoostCache::singleton();
    expect($cache->get('a'))->toEqual(1);
    expect($cache->get('b'))->toEqual(null);
});
test('tear down', function () {
    $cache = BoostCache::singleton();
    expect($cache->remove('a'))->toBeTrue();
});
test('core drivers', function () {
    $cache = BoostCache::singleton();
    expect(BoostCache::nulld())->not->toBeNull();
    expect(BoostCache::file())->not->toBeNull();
    expect(BoostCache::apcu())->not->toBeNull();
    expect(BoostCache::memory())->not->toBeNull();
    // $this->assertNotNull(BoostCache::memcached());
});
test('non core drivers', function () {
    $cache = BoostCache::singleton();
    expect(BoostCache::sqlite())->toBeNull();
    expect(BoostCache::mysql())->toBeNull();
    expect(BoostCache::redis())->toBeNull();
});
