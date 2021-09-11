<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\Page;
use Kirby\Toolkit\A;

trait PageHasBoost
{
    public static function create(array $props): Page
    {
        if (!A::get($props['content'], 'boostid')) {
            $boostid = option('bnomei.boost.index.generator')();
            // make 100% sure its unique
            while (BoostIndex::singleton()->findByBoostId($boostid)) {
                $boostid = option('bnomei.boost.index.generator')();
            }
            $props['content']['boostid'] = $boostid;
        }

        return parent::create($props);
    }

    public function hasBoost(): bool
    {
        return true;
    }

    public function forceNewBoostId(?string $id = null)
    {
        if ($this->boostIDField()->isEmpty()) {
            $boostid = $id ?? option('bnomei.boost.index.generator')();
            // make 100% sure its unique
            while (BoostIndex::singleton()->findByBoostId($boostid)) {
                $boostid = option('bnomei.boost.index.generator')();
            }
            return $this->update([
                'boostid' => $boostid,
            ]);
        }

        return $this;
    }

    public function isContentMemcached(string $languageCode = null): bool
    {
        return $this->readContentCache($languageCode) !== null;
    }

    public function memcachedKey(string $languageCode = null): string
    {
        $key = md5($this->id());
        if (!$languageCode) {
            $languageCode = kirby()->languages()->count() ? kirby()->language()->code() : null;
            if ($languageCode) {
                $key = $key . '-' .  $languageCode;
            }
        }

        return $key;
    }

    public function readContent(string $languageCode = null): array
    {
        // read from memcached if exists
        $data = option('debug') ? null : $this->readContentCache($languageCode);

        // read from file and update memcached
        if (! $data) {
            $data = parent::readContent($languageCode);
            $this->writeContentCache($data, $languageCode);
        }

        return $data;
    }

    /**
     * @internal
     */
    public function readContentCache(string $languageCode = null): ?array
    {
        $cache = BoostCache::singleton();
        if (! $cache) {
            return null;
        }

        $modified = $this->modified();
        $modifiedCache = $cache->get(
            $this->memcachedKey($languageCode).'-modified',
            null
        );
        if ($modifiedCache && intval($modifiedCache) < intval($modified)) {
            return null;
        }

        return $cache->get(
            $this->memcachedKey($languageCode),
            null
        );
    }

    public function writeContent(array $data, string $languageCode = null): bool
    {
        // write to file and memcached
        return parent::writeContent($data, $languageCode) &&
            $this->writeContentCache($data, $languageCode);
    }

    /**
     * @internal
     */
    public function writeContentCache(array $data, string $languageCode = null): bool
    {
        $cache = BoostCache::singleton();
        if (! $cache) {
            return true;
        }

        $cache->set(
            $this->memcachedKey($languageCode).'-modified',
            $this->modified(),
            option('bnomei.boost.memcached.expire')
        );

        return $cache->set(
            $this->memcachedKey($languageCode),
            $data,
            option('bnomei.boost.memcached.expire')
        );
    }

    public function delete(bool $force = false): bool
    {
        $cache = BoostCache::singleton();
        if (! $cache) {
            return parent::delete($force);
        }

        foreach (kirby()->languages() as $language) {
            $cache->remove(
                $this->memcachedKey($language->code())
            );
            $cache->remove(
                $this->memcachedKey($language->code()).'-modified'
            );
        }
        $cache->remove(
            $this->memcachedKey()
        );
        $cache->remove(
            $this->memcachedKey().'-modified'
        );

        return parent::delete($force);
    }
}
