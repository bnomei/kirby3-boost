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
    ],
    'blueprints' => autoloader(__DIR__)->blueprints(),
    'collections' => autoloader(__DIR__)->collections(),
    'pageMethods' => [ // PAGE
        'bolt' => function (string $id) {
            return \Bnomei\Bolt::page($id, $this);
        },
        'boost' => function () {
            // has boost?
            // needs write?
            // then write
            $lang = kirby()->languageCode();
            return $this->hasBoost() === true &&
                $this->isContentCacheExpiredByModified($lang) &&
                $this->writeContentCache($lang);
        },
        'isBoosted' => function () {
            return $this->hasBoost() === true &&
                $this->isContentBoosted(kirby()->languageCode())
            ;
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
            \Bnomei\BoostIndex::singleton()->add($this);
            return $this->boostIDField()->value();
        },
        'tinyurl' => function (): string {
            if ($url = \Bnomei\BoostIndex::tinyurl($this->boostIDField())) {
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
            foreach ($this as $page) {
                $count += $page->boost() ? 1 : 0;
            }
            return round(($time + microtime(true)) * 1000);
        },
        'boostmark' => function (): array {
            $time = -microtime(true);
            $str = '';
            $count = 0;
            foreach ($this as $page) {
                if ($page->hasBoost()) {
                    // uuid and a field to force reading from cache
                    $str .= $page->diruri() . $page->modified() . $page->boostIDField()->value();
                    $count++;
                }
            }
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
                        return \go($page->url(), 302);
                    }
                    return \go(site()->errorPage()->url(), 404);
                },
            ],
        ];
    },
    'hooks' => [
        'page.create:after' => function ($page) {
            if ($page->hasBoost() === true) {
                $page = $page->forceNewBoostId();
                \Bnomei\BoostIndex::singleton()->add($page);
                \Bnomei\BoostIndex::singleton()->write();
            }
        },
        'page.update:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                \Bnomei\BoostIndex::singleton()->add($newPage);
                \Bnomei\BoostIndex::singleton()->write();
            }
        },
        'page.duplicate:after' => function ($duplicatePage, $originalPage) {
            if ($duplicatePage->hasBoost() === true) {
                $duplicatePage = $duplicatePage->forceNewBoostId();
                \Bnomei\BoostIndex::singleton()->add($duplicatePage);
                \Bnomei\BoostIndex::singleton()->write();
            }
        },
        'page.changeNum:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                \Bnomei\BoostIndex::singleton()->index(true);
                \Bnomei\BoostIndex::singleton()->write();
            }
        },
        'page.changeSlug:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                \Bnomei\BoostIndex::singleton()->index(true);
                \Bnomei\BoostIndex::singleton()->write();
            }
        },
        'page.changeStatus:after' => function ($newPage, $oldPage) {
            if ($newPage->hasBoost() === true) {
                \Bnomei\BoostIndex::singleton()->index(true);
                \Bnomei\BoostIndex::singleton()->write();
            }
        },
        'page.delete:after' => function ($page, $force) {
            if ($page->hasBoost() === true) {
                \Bnomei\BoostIndex::singleton()->index(true);
                \Bnomei\BoostIndex::singleton()->write();
            }
        },
    ],
]);
