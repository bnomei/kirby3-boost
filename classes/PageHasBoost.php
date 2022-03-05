<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\Page;
use Kirby\Toolkit\A;

trait PageHasBoost
{
    /** @var bool */
    private $boostWillBeDeleted;

    public static function create(array $props): Page
    {
        $fieldname = option('bnomei.boost.fieldname');
        if (!A::get($props['content'], $fieldname)) {
            $boostid = option('bnomei.boost.index.generator')();
            // make 100% sure its unique
            while (BoostIndex::singleton()->findByBoostId($boostid, false)) {
                $boostid = option('bnomei.boost.index.generator')();
            }
            $props['content'][$fieldname] = $boostid;
        }

        return parent::create($props);
    }

    public function checkModifiedTimestampForContentBoost(): bool
    {
        return option('bnomei.boost.fileModifiedCheck');
    }

    public function setBoostWillBeDeleted(bool $value): void
    {
        $this->boostWillBeDeleted = $value;
    }

    public function hasBoost(): bool
    {
        return true;
    }

    public function isContentBoosted(string $languageCode = null): bool
    {
        return $this->readContentCache($languageCode) !== null;
    }

    public function forceNewBoostId(bool $overwrite = false, ?string $id = null)
    {
        if ($overwrite || $this->boostIDField()->isEmpty()) {
            $boostid = $id ?? option('bnomei.boost.index.generator')();
            // make 100% sure its unique
            while (BoostIndex::singleton()->findByBoostId($boostid, false)) {
                $boostid = option('bnomei.boost.index.generator')();
            }
            $fieldname = option('bnomei.boost.fieldname');
            kirby()->impersonate('kirby');
            return $this->update([
                $fieldname => $boostid,
            ]);
        }

        return $this;
    }

    public function contentBoostedKey(string $languageCode = null): string
    {
        $key = strval(crc32($this->id()));
        if (! $languageCode) {
            $languageCode = kirby()->languages()->count() ? kirby()->language()->code() : null;
        }
        if ($languageCode) {
            $key = $key . '-' .  $languageCode;
        }

        return $key;
    }

    public function isContentCacheExpiredByModified(string $languageCode = null): bool
    {
        $cache = BoostCache::singleton();
        if (! $cache) {
            return true;
        }

        $modifiedCache = $cache->get(
            $this->contentBoostedKey($languageCode).'-modified',
            null
        );
        if (!$modifiedCache) {
            return true;
        }

        $modified = $this->modified();
        // in rare case the file does not exist or is not readable
        if ($modified === false) {
            return true;
        }
        // otherwise compare
        if ($modifiedCache && intval($modifiedCache) < intval($modified)) {
            return true;
        }

        return false;
    }

    public function readContentCache(string $languageCode = null): ?array
    {
        if ($this->checkModifiedTimestampForContentBoost()) {
            if ($this->isContentCacheExpiredByModified($languageCode)) {
                return null;
            }
        }

        return BoostCache::singleton()->get(
            $this->contentBoostedKey($languageCode) . '-content',
            null
        );
    }

    public function readContent(string $languageCode = null): array
    {
        // read from boostedCache if exists
        $data = option('bnomei.boost.read') === false || option('debug') ? null : $this->readContentCache($languageCode);

        // read from file and update boostedCache
        if (! $data) {
            $data = parent::readContent($languageCode);
            if ($data && $this->boostWillBeDeleted !== true) {
                $this->writeContentCache($data, $languageCode);
            }
        }

        return $data;
    }

    public function writeContentCache(?array $data = null, string $languageCode = null): bool
    {
        $cache = BoostCache::singleton();
        if (! $cache || option('bnomei.boost.write') === false) {
            return true;
        }

        $modified = $this->modified();

        // in rare case file does not exists or is not readable
        if ($modified === false) {
            return false; // try again another time
        }

        $cache->set(
            $this->contentBoostedKey($languageCode) . '-modified',
            $modified,
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
        // write to file and cache
        return parent::writeContent($data, $languageCode) &&
            $this->writeContentCache($data, $languageCode);
    }

    public function deleteContentCache(): bool
    {
        $cache = BoostCache::singleton();
        if (! $cache) {
            return true;
        }

        $this->setBoostWillBeDeleted(true);

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

        return true;
    }

    public function delete(bool $force = false): bool
    {
        $cache = BoostCache::singleton();
        if (! $cache) {
            return parent::delete($force);
        }

        $success = parent::delete($force);
        $this->deleteContentCache();

        return $success;
    }
}
