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

    public function isContentBoosted(string $languageCode = null): bool
    {
        return $this->readContentCache($languageCode) !== null;
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

    public function contentBoostedKey(string $languageCode = null): string
    {
        $key = strval(crc32($this->id()));
        if (!$languageCode) {
            $languageCode = kirby()->languages()->count() ? kirby()->language()->code() : null;
            if ($languageCode) {
                $key = $key . '-' .  $languageCode;
            }
        }

        return $key;
    }

    public function isContentCacheExpiredByModified(string $languageCode = null): bool
    {
        $cache = BoostCache::singleton();
        if (! $cache) {
            return true;
        }

        $modified = $this->modified();
        $modifiedCache = $cache->get(
            $this->contentBoostedKey($languageCode).'-modified',
            null
        );
        if (!$modifiedCache) {
            return true;
        }
        if ($modifiedCache && intval($modifiedCache) < intval($modified)) {
            return true;
        }

        return false;
    }

    public function readContentCache(string $languageCode = null): ?array
    {
        if ($this->isContentCacheExpiredByModified($languageCode)) {
            return null;
        }

        return BoostCache::singleton()->get(
            $this->contentBoostedKey($languageCode) . '-content',
            null
        );
    }

    public function readContent(string $languageCode = null): array
    {
        // read from boostedCache if exists
        $data = option('debug') ? null : $this->readContentCache($languageCode);

        // read from file and update boostedCache
        if (! $data) {
            $data = parent::readContent($languageCode);
            if ($data) {
                $this->writeContentCache($data, $languageCode);
            }
        }

        return $data;
    }

    public function writeContentCache(?array $data = null, string $languageCode = null): bool
    {
        $cache = BoostCache::singleton();
        if (! $cache) {
            return true;
        }

        $cache->set(
            $this->contentBoostedKey($languageCode) . '-modified',
            $this->modified(),
            option('bnomei.boost.expire')
        );

        return $cache->set(
            $this->contentBoostedKey($languageCode) . '-content',
            $data,
            option('bnomei.boost.expire')
        );
    }

    public function writeContent(array $data, string $languageCode = null): bool
    {
        // write to file and memcached
        return parent::writeContent($data, $languageCode) &&
            $this->writeContentCache($data, $languageCode);
    }

    public function delete(bool $force = false): bool
    {
        $cache = BoostCache::singleton();
        if (! $cache) {
            return parent::delete($force);
        }

        foreach (kirby()->languages() as $language) {
            $cache->remove(
                $this->contentBoostedKey($language->code()) . '-content'
            );
            $cache->remove(
                $this->contentBoostedKey($language->code()).'-modified'
            );
        }
        $cache->remove(
            $this->contentBoostedKey() . '-content'
        );
        $cache->remove(
            $this->contentBoostedKey().'-modified'
        );

        return parent::delete($force);
    }
}
