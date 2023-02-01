<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cache\Cache;
use Kirby\Cache\FileCache;
use Kirby\Cache\ApcuCache;
use Kirby\Cache\MemoryCache;
use Kirby\Cache\MemCached;
use Kirby\Cache\NullCache;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;

final class BoostCache
{
    private static $singleton;
    public static function singleton(): Cache
    {
        if (! self::$singleton) {
            self::$singleton = kirby()->cache('bnomei.boost');
        }
        /* DO NOT DO THIS EVER
        if (option('debug')) {
            self::$singleton->flush();
        }
        */
        self::patchFilesClass();

        return self::$singleton;
    }

    public static function beginTransaction()
    {
        if (is_callable([self::singleton(), 'beginTransaction'])) {
            self::singleton()->beginTransaction();
        }
    }

    public static function endTransaction()
    {
        if (is_callable([self::singleton(), 'endTransaction'])) {
            self::singleton()->endTransaction();
        }
    }

    public static function hashalgo() {
        $algos = explode(',', option('bnomei.boost.hashalgo'));
        if (version_compare(PHP_VERSION, '8.1.0') >= 0) {
            return $algos[0];
        }
        return $algos[1];
    }

    public static function modified($model): ?int
    {
        if ($model instanceof \Kirby\Cms\Page ||
            $model instanceof \Kirby\Cms\File ||
            $model instanceof \Kirby\Cms\User) {
            $modified = static::singleton()->get($model->contentBoostedKey() . '-modified');
            if ($modified) { // could be false
                return $modified;
            } else {
                return $model->modified();
            }
        } elseif ($model instanceof \Kirby\Cms\Site) {
            return filemtime($model->contentFile());
        } elseif (is_string($model)) {
            $key = hash(BoostCache::hashalgo(), $model);
            $languageCode = kirby()->languages()->count() ? kirby()->language()->code() : null;
            if ($languageCode) {
                $key = $key . '-' .  $languageCode;
            }
            $modified = static::singleton()->get($key . '-modified');
            if ($modified) { // could be false
                return $modified;
            }
            if ($page = bolt($model)) {
                return $page->modified();
            }
            return null;
        }

        return null;
    }

    public static function patchFilesClass()
    {
        if (option('bnomei.boost.patch.files')) {
            $filesClass = kirby()->roots()->kirby() . '/src/Cms/Files.php';
            if (F::exists($filesClass) && F::isWritable($filesClass)) {
                $code = F::read($filesClass);
                if (Str::contains($code, '\Bnomei\BoostFile::factory') === false) {
                    $code = str_replace('File::factory(', '\Bnomei\BoostFile::factory(', $code);
                    F::write($filesClass, $code);
                }
            }
        }
    }

    public static function nulld(array $options = []): NullCache
    {
        return new NullCache(array_merge([
        ], $options));
    }

    public static function file(array $options = []): FileCache
    {
        return new FileCache(array_merge([
            'root' => kirby()->roots()->cache(),
        ], $options));
    }

    public static function apcu(array $options = []): ApcuCache
    {
        return new ApcuCache(array_merge([
        ], $options));
    }

    public static function memcached(array $options = []): ?MemCached
    {
        if (class_exists('Memcached')) { // PHP core class
            return new MemCached(array_merge([
                'host' => '127.0.0.1',
                'port' => 11211,
            ]));
        }
        return null;
    }

    public static function memory(array $options = []): MemoryCache
    {
        return new MemoryCache(array_merge([
        ], $options));
    }

    public static function sqlite(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\SQLiteCache')) {
            $feather = \Bnomei\SQLiteCache::singleton(array_merge([
            ], $options));
            return $feather;
        }
        return null;
    }

    public static function mysql(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\MySQLCache')) {
            return \Bnomei\MySQLCache::singleton(array_merge([
            ], $options));
        }
        return null;
    }

    public static function php(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\PHPCache')) {
            $elephant = \Bnomei\PHPCache::singleton(array_merge([
            ], $options));
            return $elephant;
        }
        return null;
    }

    public static function mongodb(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\MongoDBCache')) {
            $ape = \Bnomei\MongoDBCache::singleton(array_merge([
            ], $options));
            return $ape;
        }
        return null;
    }

    public static function redis(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\Redis')) {
            return new \Bnomei\Redis(array_merge([
                'host'   => '127.0.0.1',
                'port'   => 6379,
            ], $options));
        }
        return null;
    }
}
