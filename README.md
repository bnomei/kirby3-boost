# Kirby3 Boost

![Release](https://flat.badgen.net/packagist/v/bnomei/kirby3-boost?color=ae81ff)
![Downloads](https://flat.badgen.net/packagist/dt/bnomei/kirby3-boost?color=272822)
[![Build Status](https://flat.badgen.net/travis/bnomei/kirby3-boost)](https://travis-ci.com/bnomei/kirby3-boost)
[![Coverage Status](https://flat.badgen.net/coveralls/c/github/bnomei/kirby3-boost)](https://coveralls.io/github/bnomei/kirby3-boost) 
[![Maintainability](https://flat.badgen.net/codeclimate/maintainability/bnomei/kirby3-boost)](https://codeclimate.com/github/bnomei/kirby3-boost) 
[![Twitter](https://flat.badgen.net/badge/twitter/bnomei?color=66d9ef)](https://twitter.com/bnomei)

Boost the speed of Kirby by having content files of pages (mem-)cached, with automatic unique ID, fast lookup and Tiny-URL.

## Usecase

If you have a lot of page objects (1000+) with or without relations to each other via unique ids then consider using this plugin. With less page objects you will propably not gain enough to justify the overhead.

## How does this plugin work?

- It caches all content files and keeps the cache up to date when you add or modify content. This cache will be used when constructing page objects making everything that involves page objects faster (even the Panel).
- It can add an unique ID for page objects that can be used create relations that do not break even if the slug or directory of a page object changes.
- It provides a very fast lookup for page objects via id, diruri or the unique id.
- It provides you with a tiny-url for page objects that have an unique id.

> This plugin is an enhanced combination of [Page Memcached](https://github.com/bnomei/kirby3-page-memcached), [AutoID](https://github.com/bnomei/kirby3-autoid/) and [Bolt](https://github.com/bnomei/kirby3-bolt). 

## Setup

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
```php
$page = modified($somePageId);
```

## Memcached

This plugin uses the PHP Memcached extension for optimal performance. Not Memcache, but Memcached. It can also use Kirbys default file cache driver.

### Memcached Setup

**site/config/config.php**
```php
<?php

return [
    // other options
    // use memached
    // defaults...
    'bnomei.boost.cacheType' => 'memcached', // file
    'bnomei.boost.memcached' => [
        'host'    => '127.0.0.1',
        'port'    => 11211,
        'prefix'  => 'boost',
        'expire'  => 0,
        'enforce' => true,
    ],
];
```

### Benchmark

The included benchmark can help you make an educated guess if on your server the memcached cache driver is faster than the file cache driver. This will create and remove 2000 items cached.

```php
// see numbers
echo \Bnomei\CacheBenchmark::file(2) . PHP_EOL; // seconds
echo \Bnomei\CacheBenchmark::memcached(2) . PHP_EOL; // seconds
// or just
echo \Bnomei\CacheBenchmark::fastest(2) . PHP_EOL;
```

### Limitations

How much and if you gain anything regarding performance depends on the hardware. But on most production servers reading data from RAM (via TCP/IP) should be faster than reading a lot of files (even from SSD disks). 

All your content files must fit within the memory limitation of Memcached Server. If you run into errors consider increasing the server settings.

| Defaults for | Memcached |
|----|----|
| max memory size | 64MB 
| size of key/value pair | 1MB |

## Tiny-URL

This plugin allows you to use the BoostID value in a shortend URL. It also registers a route to redirect from the shortend URL to the actual page. Retrieve the shortend URL it with the `tinyurl()` Page-Method. 

```php
echo $page->url(); // https://devkit.bnomei.com/test-43422931f00e27337311/test-2efd96419d8ebe1f3230/test-32f6d90bd02babc5cbc3
echo $page->autoid()->value(); // 8j5g64hh
echo $page->tinyurl(); // https://devkit.bnomei.com/x/8j5g64hh
```

### Settings

| bnomei.boost.            | Default        | Description               |            
|---------------------------|----------------|---------------------------|
| tinyurl.url | callback | returning `site()->url()`. Use htaccess on that domain to redirect `RewriteRule (.*) http://www.bnomei.com/x/$1 [R=301]` |
| tinyurl.folder | `x` | Tinyurl format: yourdomain/{folder}/{hash} |

## Migration from AutoID

You can use this plugin instead of AutoID if you did not use autoid in site objects, file objects and structures. This plugin will default to the `boostid` field to get the unique id but it will use the `autoid` field as fallback.

- Setup the models (see above)
- Keep `autoid` field or replace with `boostid` field
- Replace `autoid`/`AUTOID` in blueprint queries with `BOOSTID`
- Replace calls to `autoid()` with `boost()` in php code
- Replace `->fromAutoID()` with `->fromBoostID()` in php code

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/bnomei/kirby3-boost/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.
