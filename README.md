# Kirby3 Boost

![Release](https://flat.badgen.net/packagist/v/bnomei/kirby3-boost?color=ae81ff)
![Downloads](https://flat.badgen.net/packagist/dt/bnomei/kirby3-boost?color=272822)
[![Build Status](https://flat.badgen.net/travis/bnomei/kirby3-boost)](https://travis-ci.com/bnomei/kirby3-boost)
[![Coverage Status](https://flat.badgen.net/coveralls/c/github/bnomei/kirby3-boost)](https://coveralls.io/github/bnomei/kirby3-boost) 
[![Maintainability](https://flat.badgen.net/codeclimate/maintainability/bnomei/kirby3-boost)](https://codeclimate.com/github/bnomei/kirby3-boost) 
[![Twitter](https://flat.badgen.net/badge/twitter/bnomei?color=66d9ef)](https://twitter.com/bnomei)

Boost the speed of Kirby by having content files of pages cached, with automatic unique ID, fast lookup and Tiny-URL.

## Commerical Usage

This plugin is free but if you use it in a commercial project please consider to 
- [make a donation 🍻🍻🍻](https://www.paypal.me/bnomei/15) or
- [buy me ☕☕☕](https://buymeacoff.ee/bnomei) or
- [buy a Kirby license using this affiliate link](https://a.paddle.com/v2/click/1129/35731?link=1170)

## Usecase

If you have a lot of page objects (1000+) with or without relations to each other via unique ids then consider using this plugin. With less page objects you will propably not gain enough to justify the overhead.

## How does this plugin work?

- It caches all content files and keeps the cache up to date when you add or modify content. This cache will be used when constructing page objects making everything that involves page objects faster (even the Panel).
- It provides a benchmark to help you decide which cachedriver to use.
- It can add an unique ID for page objects that can be used create relations that do not break even if the slug or directory of a page object changes.
- It provides a very fast lookup for page objects via id, diruri or the unique id.
- It provides you with a tiny-url for page objects that have an unique id.

## Setup

For each template you want to be cached you need to add the field to your blueprint AND use a model to add the content cache logic.

**site/blueprints/pages/default.yml**
```yml
preset: page

fields:
  # visible field
  boostid:
    type: boostid
  
  # hidden field
  #boostid:
  #  extends: fields/boostid

  one_relation:
    extends: fields/boostidpage

  many_related:
    extends: fields/boostidpages
```

**site/models/default.php**
```php
class DefaultPage extends \Kirby\Cms\Page
{
    use \Bnomei\PageHasBoost;
}

// or

class DefaultPage extends \Bnomei\BoostPage
{
    
}
```

## Usage

### Page from Id
```php
$page = page($somePageId); // slower
$page = boost($somePageId); // faster
```

### Page from DirUri
```php
$page = boost($somePageDirUri); // fastest
```

### Page from BoostID
```php
$page = boost($boostId); // will use fastest internally
```

### Modified timestamp from cache

This will try to get the modified timestamp from cache. If the page object content can be cached but currently was not, it will force a content cache write. It will return the modified timestamp of a page object or if it does not exist it will return `null`.

```php
$pageModifiedTimestampOrNull = modified($somePageId); // faster
```

### Debug === read from content file (not from cache)

If you set Kirbys global debug option to `true` the plugin will not read the content cache but from the content file on disk. But it will write to the content cache so you can get debug messages if anything goes wrong with that process.

### Forcing a content cache update

You can force writing outdated values to the cache manually but doing that should not be necessary.

```php
// write content cache of a single page
$cachedYesOrNoAsBoolean = $page->boost();

// write content cache of all pages in a Pages collection
$durationOfThisCacheIO = $page->children()->boost();

// write content cache of all pages in site index
$durationOfThisCacheIO = site()->boost();
```

## Cache

### Limitations

How much and if you gain anything regarding performance depends on the hardware. All your content files must fit within the memory limitation. If you run into errors consider increasing the server settings or choose a different cache driver.

| Defaults for | Memcached | APCu | Redis | MySQL | SQLite |
|----|----|----|----|----|----|
| max memory size | 64MB | 32MB | 0 (none) | 0 (none) | 0 (none) |
| size of key/value pair | 1MB | 4MB | 512MB | 0 (none) | 0 (none) |

Kirby has [built in support](https://getkirby.com/docs/reference/system/options/cache#cache-driver) for File, Apcu, Memcached and Memory. I have created additional cache drivers for [MySQL](https://github.com/bnomei/kirby3-mysql-cachedriver), [Redis](https://github.com/bnomei/kirby3-redis-cachedriver) and [SQLite](https://github.com/bnomei/kirby3-sqlite-cachedriver).

### Benchmark

The included benchmark can help you make an educated guess which is the faster cache driver. The only way to make sure is measuring in production. 
Be aware that this will create and remove 1000 items cached. The benchmark will try to perform as many get operations within given timeframe (default 1 second per cache). The higher results are better.

```php
// use helpers to generate caches to compare
// rough performance level is based on my tests
$caches = [
    // better
    // \Bnomei\BoostCache::null(),
    // \Bnomei\BoostCache::memory(),
    \Bnomei\BoostCache::apcu(),      //  180
    \Bnomei\BoostCache::sqlite(),    //  55
    // \Bnomei\BoostCache::file(),   //  44
    \Bnomei\BoostCache::memcached(), //  14
    \Bnomei\BoostCache::redis(),     //  11
    // \Bnomei\BoostCache::mysql(),  //  ??
    // worse
];

// run the cachediver benchmark
var_dump(\Bnomei\CacheBenchmark::run($caches, 1, 1000)); // a rough guess
var_dump(\Bnomei\CacheBenchmark::run($caches, 1, site()->index()->count())); // more realistic

// first make sure all boosted pages are up-to-date in cache
// this can be skipped on next benchmark
site()->boost();
// run the boost pages benchmark
var_dump(site()->boostmark());
```

- Memory Cache Driver and Null Cache Driver would perform best but it either caches in memory only for current request or not at all and that is not really useful for this plugin. 
- APCu Cache can be expected to be very fast but one has to make sure all content fits into the memory limitations.
- SQLite Cache Driver will perform very well since everything will be in one file and I optimized the read/write with [pragmas](https://github.com/bnomei/kirby3-sqlite-cachedriver/blob/bc3ccf56cefff7fd6b0908573ce2b4f09365c353/index.php#L20) and [wal journal mode](https://github.com/bnomei/kirby3-sqlite-cachedriver/blob/bc3ccf56cefff7fd6b0908573ce2b4f09365c353/index.php#L34).
- The File Cache Driver will perform worse the more page objects you have. You are probably better of with no cache. This is the only driver with this flaw. Benchmarking this driver will also create a lot of file which in total might cause the script to exceed your php execution time.
- The MySQL Cache Driver is still in development but I expect it to on par with Memcached and Redis.

But do not take my word for it. Download the plugin, set realistic benchmark options and run the benchmark on your production server.

#### Interactive Demo

You can find the benchmark and demos running on server sponsored by **Kirbyzone** here:

- [Benchmark with all Drivers](https://kirby3-boost.bnomei.com)
- [Demo using APCu Cache Driver](https://kirby3-boost-apcu.bnomei.com)
- [Demo using MySQL Cache Driver](https://kirby3-boost-mysql.bnomei.com)
- [Demo using Null Cache Driver](https://kirby3-boost-null.bnomei.com). This setup behaves like having the boost plugin NOT active at all.
- [Demo using Redis Cache Driver](https://kirby3-boost-redis.bnomei.com)
- [Demo using SQLite Cache Driver](https://kirby3-boost-sqlite.bnomei.com)

#### Headless Demo

Queries are sent to the public API endpoint of the <a class="underline" href="https://github.com/getkirby/kql">KQL Plugin</a>. You can either use this interactive playground or a tool like HTTPie, Insomnia, PAW or Postman to connect to the API.

**HTTPie examples**
```shell
http POST https://kirby3-boost.bnomei.com/benchmark --json
http POST https://kirby3-boost-apcu.bnomei.com/api/query -a api@kirby3-boost.bnomei.com:kirby3boost < myquery.json
```

### Config

Once you know which driver you want to use you can set the plugin cache options.

**site/config/config.php**
```php
<?php

return [
    // other options

    // default is file cache driver because it will always work
    'bnomei.boost.cache' => [
        'type'     => 'file',
        'prefix'   => 'boost',
    ],

    // example apcu
    'bnomei.boost.cache' => [
        'type'     => 'apcu',
        'prefix'   => 'boost',
    ],

    // example sqlite
    // https://github.com/bnomei/kirby3-sqlite-cachedriver
    'bnomei.boost.cache' => [
        'type'     => 'sqlite',
        'prefix'   => 'boost',
    ],

    // example redis
    // https://github.com/bnomei/kirby3-redis-cachedriver
    'bnomei.boost.cache' => [
        'type'     => 'redis',
        'prefix'   => 'boost',
        'host'     => function() { return env('REDIS_HOST'); },
        'port'     => function() { return env('REDIS_PORT'); },
        'database' => function() { return env('REDIS_DATABASE'); },
        'password' => function() { return env('REDIS_PASSWORD'); },
    ],

    // example memcached
    'bnomei.boost.cache' => [
        'type'     => 'memcached',
        'prefix'   => 'boost',
        'host'     => '127.0.0.1',
        'port'     => 11211,
    ],
];
```

## Tiny-URL

This plugin allows you to use the BoostID value in a shortend URL. It also registers a route to redirect from the shortend URL to the actual page. Retrieve the shortend URL it with the `tinyurl()` Page-Method. 

```php
echo $page->url(); // https://devkit.bnomei.com/test-43422931f00e27337311/test-2efd96419d8ebe1f3230/test-32f6d90bd02babc5cbc3
echo $page->boostIDField()->value(); // 8j5g64hh
echo $page->tinyurl(); // https://devkit.bnomei.com/x/8j5g64hh
```

## Settings

| bnomei.boost.            | Default        | Description               |            
|---------------------------|----------------|---------------------------|
| fieldname | `['boostid', 'autoid']` | change name of loaded fields |
| expire | `0` | expire in minutes for all caches created |
| index.generator | callback | the uuid genertor |
| tinyurl.url | callback | returning `site()->url()`. Use htaccess on that domain to redirect `RewriteRule (.*) http://www.bnomei.com/x/$1 [R=301]` |
| tinyurl.folder | `x` | Tinyurl format: yourdomain/{folder}/{hash} |

## Migration from AutoID

You can use this plugin instead of AutoID if you did not use autoid in site objects, file objects and structures. This plugin will default to the `boostid` field to get the unique id but it will use the `autoid` field as fallback.

- Setup the models (see above)
- Keep `autoid` field or replace with `boostid` field
- Replace `autoid`/`AUTOID` in blueprint queries with `BOOSTID`
- Replace calls to `autoid()` with `boost()` in php code
- Replace `->fromAutoID()` with `->fromBoostID()` in php code

## History

This plugin is an enhanced combination of 
- [Page Memcached](https://github.com/bnomei/kirby3-page-memcached), 
- [Page SQLite](https://github.com/bnomei/kirby3-page-sqlite), 
- [AutoID](https://github.com/bnomei/kirby3-autoid/) and 
- [Bolt](https://github.com/bnomei/kirby3-bolt). 

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/bnomei/kirby3-boost/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.
