<?php

use Bnomei\Bolt;
use Bnomei\BoostCache;
use Bnomei\BoostIndex;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Toolkit\Obj;
use Kirby\Toolkit\Str;
use Kirby\Uuid\FileUuid;
use Kirby\Uuid\Uuid;
use Kirby\Uuid\Uuids;

@include_once __DIR__ . '/vendor/autoload.php';

load([
    'bnomei\\bolt' => 'classes/Bolt.php',
    'bnomei\\boostcache' => 'classes/BoostCache.php',
    'bnomei\\boostfile' => 'classes/BoostFile.php',
    'bnomei\\boostindex' => 'classes/BoostIndex.php',
    'bnomei\\boostpage' => 'classes/BoostPage.php',
    'bnomei\\boostuser' => 'classes/BoostUser.php',
    'bnomei\\cachebenchmark' => 'classes/CacheBenchmark.php',
    'bnomei\\modelhasboost' => 'classes/ModelHasBoost.php',
], __DIR__);

if (! function_exists('bolt')) {
    function bolt(string $id, ?Page $parent = null, bool $cache = true, bool $extend = true)
    {
        $id = str_replace('page://', '', $id);
        $cacheKey = 'page/' . Str::substr($id, 0, 2) . '/' . Str::substr($id, 2);
        if ($pageIdFromUuidCache = Uuids::cache()->get($cacheKey)) {
            $id = $pageIdFromUuidCache;
        }

        return Bolt::page($id, $parent, $cache, $extend);
    }
}

if (! function_exists('modified')) {
    function modified($model)
    {
        return BoostCache::modified($model);
    }
}

if (! function_exists('boost')) {
    function boost($id)
    {
        if (!$id || !option('bnomei.boost.helper')) {
            return null;
        }

        if (is_array($id)) {
            $pages = [];
            foreach ($id as $uuid) {
                $pages[] = boost($uuid);
            }
            return new Pages($pages);
        }

        $schema = 'page';

        if ($id instanceof Uuid) {
            $schema = $id->uri->type();
            $id = $id->id();
        }

        if (is_string($id) && Str::contains($id, '://')) {
            list($schema, $id) = explode('://', $id);
        }

        if ($schema === 'file' && is_string($id)) {
            // get id for file, guess parent page, get that then file
            // since that is faster than letting kirby core do it
            // FileUuuid::findByCache() would resolve so we do it manually
            if ($uuid = FileUuid::for($schema . '://' . $id) ) {
                // $value = $uuid->value(); // would resolve parent
                $value = Uuids::cache()->get($uuid->key());
                if (!$value) {
                    return null;
                }
                $parent = boost($value['parent']);
                return $parent?->file($value['filename']);
            }
        }

        if ($schema === 'page' && is_string($id)) {
            $page = BoostIndex::page($id);
            if (!$page) {
                $page = Bolt::page($id);
            }
            return $page;
        }

        return null;
    }
}

Kirby::plugin('bnomei/boost', [
    'options' => [
        'hashalgo' => 'xxh3,crc32', // best starting php 8.1 (use crc32 on php 8.0)
        'cache' => true,
        'expire' => 0,
        'fileModifiedCheck' => false, // expects content file to not be altered outside of kirby
        'read' => true, // read from cache
        'write' => true, // write to cache
        'drafts' => true, // index drafts as well
        'patch' => [
            'files' => true, // monkey patch files class
        ],
        'helper' => true, // use boost helper
    ],
    'blueprints' => [
        'fields/boostidkv' => __DIR__ . '/blueprints/fields/boostidkv.yml',
        'fields/boostidkvs' => __DIR__ . '/blueprints/fields/boostidkvs.yml',
    ],
    'collections' => [
        'boostidkvs' => require __DIR__ . '/collections/boostidkvs.php',
    ],
    'pageMethods' => [ // PAGE
        'bolt' => function (string $id) {
            return Bolt::page($id, $this);
        },
        'boost' => function () {
            $page = $this;

            // has boost?
            if ($page->hasBoost() === true) {
                // needs write?
                $lang = kirby()->languageCode();

                // if needs write
                if (!($page->readContentCache($lang))) {
                    // then write
                    $page->writeContentCache($page->content()->toArray(), $lang);
                }
            }

            // add after cache was read and id exists
            $page->boostIndexAdd();

            return true;
        },
        'unboost' => function () {
            // has boost?
            if ($this->hasBoost() === true) {
                $this->deleteContentCache();
            }

            $this->boostIndexRemove();
        },
        'isBoosted' => function () {
            // has boost?
            if ($this->hasBoost() !== true) {
                return false;
            }
            // $this->boostIndexAdd(); // this would trigger content add
            return $this->isContentBoosted(kirby()->languageCode());
        },
        'boostIndexAdd' => function () {
            return BoostIndex::singleton()->add($this);
        },
        'boostIndexRemove' => function () {
            return BoostIndex::singleton()->remove($this);
        },
        'boostCacheDirUri' => function () {
            BoostCache::singleton()->set(
                $this->uuid()->id() . '/diruri',
                $this->diruri(),
                option('bnomei.boost.expire')
            );
        },
        'searchForTemplate' => function (string $template): Pages {
            $pages = [];
            foreach (BoostIndex::singleton()->toKVs() as $obj) {
                /* @var Obj $obj */
                $diruri = $obj->diruri;
                if ($obj->template === $template && Str::contains($diruri, $this->diruri())) {
                    $pages[] = bolt($diruri);
                }
            }
            return new Pages($pages);
        },
    ],
    'pagesMethods' => [ // PAGES
        'boost' => function () {
            $time = -microtime(true);
            $count = 0;
            BoostCache::beginTransaction();
            foreach ($this as $page) {
                $count += $page->boost() ? 1 : 0;
            }
            BoostCache::endTransaction();
            return round(($time + microtime(true)) * 1000);
        },
        'unboost' => function () {
            $time = -microtime(true);
            $count = 0;
            BoostCache::beginTransaction();
            foreach ($this as $page) {
                $count += $page->unboost() ? 1 : 0;
            }
            BoostCache::endTransaction();
            return round(($time + microtime(true)) * 1000);
        },
        'boostIndexAdd' => function () {
            $time = -microtime(true);
            foreach ($this as $page) {
                BoostIndex::singleton()->add($page);
            }
            return round(($time + microtime(true)) * 1000);
        },
        'boostIndexRemove' => function () {
            $time = -microtime(true);
            foreach ($this as $page) {
                BoostIndex::singleton()->remove($page);
            }
            return round(($time + microtime(true)) * 1000);
        },
        'boostmark' => function (): array {
            $time = -microtime(true);
            $str = '';
            $count = 0;
            BoostCache::beginTransaction();
            foreach ($this as $page) {
                if ($page->hasBoost() === true) {
                    // uuid and a field to force reading from cache calling the uuid & title from content file
                    $str .= $page->diruri() . $page->modified() . $page->uuid()->id()  . $page->title()->value();
                    $page->boostIndexAdd();
                    $count++;
                }
            }
            BoostCache::endTransaction();
            return [
                'duration' => round(($time + microtime(true)) * 1000),
                'count' => $count,
                'checksum' => md5($str),
            ];
        },
    ],
    'siteMethods' => [
        'boost' => function () {
            $count = 0;
            Bolt::index(function ($page) use (&$count) {
                $count += $page->boost() ? 1 : 0;
            });
            return $count;
        },
        'boostmark' => function () {
            $drafts = option('bnomei.boost.drafts');
            return site()->index($drafts)->boostmark();
        },
        'searchForTemplate' => function (string $template): Pages {
            $pages = [];
            foreach (BoostIndex::singleton()->toKVs() as $obj) {
                /* @var Obj $obj */
                if ($obj->template === $template) {
                    $pages[] = bolt($obj->diruri);
                }
            }
            return new Pages($pages);
        },
        'siteindexfolders' => function () {
            $paths = kirby()->cache('bnomei.boost')->get('siteindexfolders', []);

            if (count($paths) === 0) {
                $drafts = option('bnomei.boost.drafts');
                $root = kirby()->roots()->content();

                $iter = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST,
                    RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
                );

                $paths = [];
                foreach ($iter as $path => $dir) {
                    if ($dir->isDir()) {
                        if (!$drafts && strstr($path, '_drafts') === false) {
                            continue;
                        }
                        $paths[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
                    }
                }
                kirby()->cache('bnomei.boost')->set('siteindexfolders', $paths, 1);
            }

            return $paths;
        },
    ],
    'fieldMethods' => [
        'toPageBoosted' => function ($field): ?Page {
            return boost($field->value);
        },
        'toPagesBoosted' => function ($field): Pages {
            $pages = [];
            foreach (explode(',', $field->value) as $value) {
                if ($page = boost($value)) {
                    $pages[] = $page;
                }
            }
            return new Pages($pages);
        },
    ],
    'hooks' => [
        'page.create:after' => function ($page) {
            if (option('bnomei.boost.helper')) {
                $page->boostIndexAdd();
            }
        },
        'page.update:after' => function ($newPage, $oldPage) {
            if (option('bnomei.boost.helper')) {
                $newPage->boostIndexAdd();
            }
        },
        'page.duplicate:after' => function ($duplicatePage, $originalPage) {
            if (option('bnomei.boost.helper')) {
                $duplicatePage->boostIndexAdd();
            }
        },
        'page.changeNum:after' => function ($newPage, $oldPage) {
            if (option('bnomei.boost.helper')) {
                $newPage->boostIndexAdd();
                $newPage->index(option('bnomei.boost.drafts'))->boostIndexAdd();
            }
        },
        'page.changeSlug:after' => function ($newPage, $oldPage) {
            if (option('bnomei.boost.helper')) {
                $newPage->boostIndexAdd();
                $newPage->index(option('bnomei.boost.drafts'))->boostIndexAdd();
            }
        },
        'page.changeStatus:after' => function ($newPage, $oldPage) {
            if (option('bnomei.boost.helper')) {
                $newPage->boostIndexAdd();
                $newPage->index(option('bnomei.boost.drafts'))->boostIndexAdd();
            }
        },
        'page.changeTemplate:after' => function ($newPage, $oldPage) {
            if (option('bnomei.boost.helper')) {
                $newPage->boostIndexAdd();
            }
        },
        'page.delete:after' => function ($page, $force) {
            if (option('bnomei.boost.helper')) {
                $page->boostIndexRemove();
                $page->index(option('bnomei.boost.drafts'))->boostIndexRemove();
            }
        },
    ],
]);
