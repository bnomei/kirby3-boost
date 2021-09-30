<?php

@include_once __DIR__ . '/vendor/autoload.php';

autoloader(__DIR__)->classes();

if (! function_exists('bolt')) {
    function bolt(string $id)
    {
        return \Bnomei\Bolt::page($id);
    }
}

if (! function_exists('modified')) {
    function modified(string $id)
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
        return site()->index()->filter(function ($page) use ($id) {
            return $page->boostIDField()->value() === $id;
        })->first();
    }
    */
}

Kirby::plugin('bnomei/boost', [
    'options' => [
        'cache' => true,
        'fieldname' => [
            'boostid',
            // provide drop-in support for autoid (page objects only)
            'autoid',
        ],
        'expire' => 0,
        'fileModifiedCheck' => false, // expects file to not be altered outside of kirby
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
            // has boost?
            if ($this->hasBoost() === false) {
                return false;
            }
            $this->boostIndexAdd();
            // needs write?
            $lang = kirby()->languageCode();
            $content = $this->readContentCache($lang);
            if (! $content) {
                // then write
                return $this->writeContentCache($this->content()->toArray(), $lang);
            }
            return true;
        },
        'unboost' => function () {
            // has boost?
            if ($this->hasBoost() === false) {
                return false;
            }
            $this->boostIndexRemove();
            return $this->deleteContentCache();
        },
        'isBoosted' => function () {
            // has boost?
            if ($this->hasBoost() === false) {
                return false;
            }
            // $this->boostIndexAdd(); // this would trigger content add
            return $this->isContentBoosted(kirby()->languageCode());
        },
        'boostIDField' => function () {
            $fields = option('bnomei.boost.fieldname', []);
            if (is_string($fields)) {
                $fields = [$fields];
            }
            foreach ($fields as $field) {
                if ($this->{$field}()->isNotEmpty()) {
                    return $this->{$field}();
                }
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
            if ($this->hasBoost() && $url = \Bnomei\BoostIndex::tinyurl($this->boostIDField())) {
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
                if ($page->hasBoost()) {
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
            return site()->index()->boost();
        },
        'boostmark' => function () {
            return site()->index()->boostmark();
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
                $page = $page->forceNewBoostId();
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
                $duplicatePage = $duplicatePage->forceNewBoostId();
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $duplicatePage->boostIndexAdd();
                }
            }
        },
        'page.changeNum:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $newPage->boostIndexAdd();
                    $newPage->children()->boostIndexAdd();
                }
            }
        },
        'page.changeSlug:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $newPage->boostIndexAdd();
                    $newPage->children()->boostIndexAdd();
                }
            }
        },
        'page.changeStatus:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $newPage->boostIndexAdd();
                    $newPage->children()->boostIndexAdd();
                }
            }
        },
        'page.delete:after' => function ($page, $force) {
            if ($page->hasBoost() === true) {
                if (option('bnomei.boost.updateIndexWithHooks')) {
                    $page->boostIndexRemove();
                    $page->children()->boostIndexRemove();
                }
            }
        },
    ],
]);
