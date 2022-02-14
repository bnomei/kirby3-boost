<?php

@include_once __DIR__ . '/vendor/autoload.php';

autoloader(__DIR__)->classes();

if (! function_exists('bolt')) {
    function bolt(string $id, ?\Kirby\Cms\Page $parent = null, bool $cache = true, bool $extend = true)
    {
        return \Bnomei\Bolt::page($id, $parent, $cache, $extend);
    }
}

if (! function_exists('modified')) {
    function modified($id)
    {
        return \Bnomei\BoostIndex::modified($id);
    }
}

if (! function_exists('boost')) {
    function boost(string $id): ?\Kirby\Cms\Page
    {
        $page = \Bnomei\BoostIndex::page($id);
        if (!$page) {
            $page = \Bnomei\Bolt::page($id);
        }
        return $page;
    }
    /*
    function siteIndexFilterByBoostID(string $id): ?\Kirby\Cms\Page
    {
        $drafts = option('bnomei.boost.drafts');
        return site()->index($drafts)->filter(function ($page) use ($id) {
            return $page->boostIDField()->value() === $id;
        })->first();
    }
    */
}

Kirby::plugin('bnomei/boost', [
    'options' => [
        'cache' => true,
        'fieldname' => 'boostid', // autoid
        'expire' => 0,
        'fileModifiedCheck' => false, // expects file to not be altered outside of kirby
        'read' => true, // read from cache
        'write' => true, // write to cache
        'drafts' => true, // index drafts as well
        'index' => [
            'generator' => function (?string $seed = null) {
                // override with custom callback if needed
                return (new \Bnomei\TokenGenerator($seed))->generate();
            },
        ],
        'tinyurl' => [
            'url' => function () {
                return kirby()->url('index');
            },
            'folder' => 'x',
        ],
        'updateIndexWithHooks' => true, // disable this when batch creating pages
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
                return false;
            }

            // if not has an id force one
            $page = $page->forceNewBoostId();

            // needs write?
            $lang = kirby()->languageCode();
            $content = $page->readContentCache($lang);

            // add after cache was read and id exists
            $page->boostIndexAdd();

            // if needs write
            if (! $content) {
                // then write
                return $page->writeContentCache($page->content()->toArray(), $lang);
            }
            return true;
        },
        'unboost' => function () {
            // has boost?
            if ($this->hasBoost() !== true) {
                return false;
            }
            $this->boostIndexRemove();
            return $this->deleteContentCache();
        },
        'isBoosted' => function () {
            // has boost?
            if ($this->hasBoost() !== true) {
                return false;
            }
            // $this->boostIndexAdd(); // this would trigger content add
            return $this->isContentBoosted(kirby()->languageCode());
        },
        'boostIDField' => function () {
            $fieldname = option('bnomei.boost.fieldname');
            if ($this->{$fieldname}()->isNotEmpty()) {
                return $this->{$fieldname}();
            }
            // default
            return $this->boostid();
        },
        'BOOSTID' => function () { // casesensitive
            $this->boostIndexAdd();
            return $this->boostIDField()->value();
        },
        'boostIndexAdd' => function () {
            return Bnomei\BoostIndex::singleton()->add($this);
        },
        'boostIndexRemove' => function () {
            return \Bnomei\BoostIndex::singleton()->remove($this);
        },
        'tinyurl' => function (): string {
            if ($this->hasBoost() === true && $url = \Bnomei\BoostIndex::tinyurl($this->boostIDField())) {
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
                    // uuid and a field to force reading from cache
                    $str .= $page->diruri() . $page->modified() . $page->boostIDField()->value();
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
    ],
    'fieldMethods' => [
        'fromBoostID' => function ($field): ?\Kirby\Cms\Page {
            return boost($field->value);
        },
        'fromBoostIDs' => function ($field): ?\Kirby\Cms\Pages {
            $pages = [];
            foreach (explode(',', $field->value) as $value) {
                if ($page = boost($value)) {
                    $pages[] = $page;
                }
            }
            return count($pages) ? new \Kirby\Cms\Pages($pages) : null;
        },
    ],
    'fields' => [
        'boostid' => [
            'props' => [
                'value' => function (string $value = null) {
                    return $value;
                },
            ],
        ],
        'autoid' => [
            'props' => [
                'value' => function (string $value = null) {
                    return $value;
                },
            ],
        ],
    ],
    'routes' => function ($kirby) {
        $folder = $kirby->option('bnomei.boost.tinyurl.folder');
        return [
            [
                'pattern' => $folder . '/(:any)',
                'method' => 'GET',
                'action' => function ($boostid) {
                    $page = boost($boostid);
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
            if ($page->hasBoost() === true) {
                $page = $page->forceNewBoostId(false);
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $page->boostIndexAdd();
                }
            }
        },
        'page.update:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $newPage->boostIndexAdd();
                }
            }
        },
        'page.duplicate:after' => function ($duplicatePage, $originalPage) {
            if ($duplicatePage->hasBoost() === true) {
                $duplicatePage = $duplicatePage->forceNewBoostId(true);
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $duplicatePage->boostIndexAdd();
                }
            }
        },
        'page.changeNum:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $newPage->boostIndexAdd();
                    $newPage->index(option('bnomei.boost.drafts'))->boostIndexAdd();
                }
            }
        },
        'page.changeSlug:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $newPage->boostIndexAdd();
                    $newPage->index(option('bnomei.boost.drafts'))->boostIndexAdd();
                }
            }
        },
        'page.changeStatus:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $newPage->boostIndexAdd();
                    $newPage->index(option('bnomei.boost.drafts'))->boostIndexAdd();
                }
            }
        },
        'page.delete:after' => function ($page, $force) {
            if ($page->hasBoost() === true) {
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $page->boostIndexRemove();
                    $page->index(option('bnomei.boost.drafts'))->boostIndexRemove();
                }
            }
        },
    ],
]);
