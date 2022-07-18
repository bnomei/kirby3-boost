<?php

use Bnomei\BoostIndex;

@include_once __DIR__ . '/vendor/autoload.php';

autoloader(__DIR__)->classes();

if (! function_exists('bolt')) {
    function bolt(string $id, ?\Kirby\Cms\Page $parent = null, bool $cache = true, bool $extend = true)
    {
        return \Bnomei\Bolt::page($id, $parent, $cache, $extend);
    }
}

if (! function_exists('modified')) {
    function modified($model)
    {
        return \Bnomei\BoostCache::modified($model);
    }
}

if (! function_exists('token')) {
    function token(string $seed = null): string
    {
        return (new \Bnomei\TokenGenerator($seed))->generate();
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
            foreach($id as $uuid) {
                $pages[] = boost($uuid);
            }
            return new \Kirby\Cms\Pages($pages);
        }

        if (is_string($id)) {
            $page = \Bnomei\BoostIndex::page($id);
            if (!$page) {
                $page = \Bnomei\Bolt::page($id);
            }
            return $page;
        }

        return null;
    }
}

Kirby::plugin('bnomei/boost', [
    'options' => [
        'cache' => true,
        'expire' => 0,
        'fileModifiedCheck' => false, // expects content file to not be altered outside of kirby
        'read' => true, // read from cache
        'write' => true, // write to cache
        'drafts' => true, // index drafts as well
        'tinyurl' => [
            'url' => function () {
                return kirby()->url('index');
            },
            'folder' => 'x',
        ],
        'patch' => [
            'files' => true, // monkey patch files class
        ],
        'helper' => true, // use boost helper
    ],
    'blueprints' => autoloader(__DIR__)->blueprints(),
    'collections' => autoloader(__DIR__)->collections(),
    'pageMethods' => [ // PAGE
        'bolt' => function (string $id) {
            return \Bnomei\Bolt::page($id, $this);
        },
        'boost' => function () {
            $page = $this;

            // has boost?
            if ($page->hasBoost() !== true) {
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
                $this->deleteContentCache();;
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
            return Bnomei\BoostIndex::singleton()->add($this);
        },
        'boostIndexRemove' => function () {
            return \Bnomei\BoostIndex::singleton()->remove($this);
        },
        'boostCacheDirUri' => function() {
            \Bnomei\BoostCache::singleton()->set(
                hash('xxh3', $this->uuid()) . '-diruri',
                $this->diruri(),
                option('bnomei.boost.expire')
            );
        },
        'forceNewBoostId' => function (bool $overwrite = false, ?string $id = null) {
            $isEmpty = false;
            $u = $this->uuid();
            if ($u instanceof \Kirby\Cms\Field) {
                $isEmpty = $this->uuid()->isEmpty();
            }
            if (($overwrite || $isEmpty) && !is_string($u)) {
                $uuid = $id ?? token();
                // make 100% sure its unique
                while (BoostIndex::singleton()->find($uuid, false)) {
                    $uuid = token();
                }
                kirby()->impersonate('kirby');
                return $this->update([
                    'uuid' => $uuid,
                ]);
            }

            return $this;
        },
        'searchForTemplate' => function (string $template): \Kirby\Cms\Pages
        {
            $pages = [];
            foreach(\Bnomei\BoostIndex::singleton()->toKVs() as $data) {
                $diruri = $data['diruri'];
                if($data['template'] === $template && Str::contains($diruri, $this->diruri())) {
                    $pages[] = bolt($diruri);
                }
            }
            return new \Kirby\Cms\Pages($pages);
        },
        'tinyurl' => function (): string {
            if ($this->hasBoost() === true && $url = \Bnomei\BoostIndex::tinyurl($this->uuid())) {
                $this->boostIndexAdd();
                return $url;
            }
            return site()->errorPage()->url();
        },
        'tinyUrl' => function (): string {
            return $this->tinyurl();
        },
    ],
    'pagesMethods' => [ // PAGES
        'boost' => function () {
            $time = -microtime(true);
            $count = 0;
            \Bnomei\BoostCache::beginTransaction();
            foreach ($this as $page) {
                $count += $page->boost() ? 1 : 0;
            }
            \Bnomei\BoostCache::endTransaction();
            return round(($time + microtime(true)) * 1000);
        },
        'unboost' => function () {
            $time = -microtime(true);
            $count = 0;
            \Bnomei\BoostCache::beginTransaction();
            foreach ($this as $page) {
                $count += $page->unboost() ? 1 : 0;
            }
            \Bnomei\BoostCache::endTransaction();
            return round(($time + microtime(true)) * 1000);
        },
        'boostIndexAdd' => function () {
            $time = -microtime(true);
            foreach ($this as $page) {
                \Bnomei\BoostIndex::singleton()->add($page);
            }
            return round(($time + microtime(true)) * 1000);
        },
        'boostIndexRemove' => function () {
            $time = -microtime(true);
            foreach ($this as $page) {
                \Bnomei\BoostIndex::singleton()->remove($page);
            }
            return round(($time + microtime(true)) * 1000);
        },
        'boostmark' => function (): array {
            $time = -microtime(true);
            $str = '';
            $count = 0;
            \Bnomei\BoostCache::beginTransaction();
            foreach ($this as $page) {
                if ($page->hasBoost() === true) {
                    // uuid and a field to force reading from cache calling the uuid & title from content file
                    $str .= $page->diruri() . $page->modified() . $page->uuid()  . $page->title()->value();
                    $page->boostIndexAdd();
                    $count++;
                }
            }
            \Bnomei\BoostCache::endTransaction();
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
            \Bnomei\Bolt::index(function ($page) use (&$count) {
                $count += $page->boost() ? 1 : 0;
            });
            return $count;
        },
        'boostmark' => function () {
            $drafts = option('bnomei.boost.drafts');
            return site()->index($drafts)->boostmark();
        },
        'searchForTemplate' => function (string $template): \Kirby\Cms\Pages
        {
            $pages = [];
            foreach(\Bnomei\BoostIndex::singleton()->toKVs() as $data) {
                if($data['template'] === $template) {
                    $pages[] = bolt($data['diruri']);
                }
            }
            return new \Kirby\Cms\Pages($pages);
        },
    ],
    'fieldMethods' => [
        'toPageBoosted' => function ($field): ?\Kirby\Cms\Page {
            return boost($field->value);
        },
        'toPagesBoosted' => function ($field): \Kirby\Cms\Pages {
            $pages = [];
            foreach (explode(',', $field->value) as $value) {
                if ($page = boost($value)) {
                    $pages[] = $page;
                }
            }
            return new \Kirby\Cms\Pages($pages);
        },
    ],
    'routes' => function ($kirby) {
        $folder = $kirby->option('bnomei.boost.tinyurl.folder');
        return [
            [
                'pattern' => $folder . '/(:any)',
                'method' => 'GET',
                'action' => function ($uuid) {
                    $page = boost($uuid);
                    if ($page) {
                        return die(\Kirby\Cms\Response::redirect($page->url(), 302));
                    }
                    return die(\Kirby\Cms\Response::redirect(site()->errorPage()->url(), 404));
                },
            ],
        ];
    },
    'hooks' => [
        'page.create:after' => function ($page) {
            if (option('bnomei.boost.helper')) {
                $page = $page->forceNewBoostId(false);
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
                $duplicatePage = $duplicatePage->forceNewBoostId(true);
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
