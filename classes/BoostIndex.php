<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\Page;
use Kirby\Toolkit\A;

final class BoostIndex
{
    public const SEPERATOR = '|!|';

    /** @var array */
    private $index;

    /** @var int */
    private $expire;

    /** @var bool */
    private $isDirty;

    public function __construct()
    {
        $this->expire = option('bnomei.boost.expire', 0);
        $this->isDirty = false;

        $this->index = $this->read();

        $cache = $this->cache();
        if ($cache && method_exists($cache, 'register_shutdown_function')) {
            $cache->register_shutdown_function(function () {
                $this->write();
            });
        }

        if (option('debug') || empty($this->index)) {
            $this->index(true);
            $success = $this->write();
        }
    }

    /** NOTE: register that with cache instead
    public function __destruct()
    {
    $this->write();
    }
     */

    public function __destruct()
    {
        $cache = $this->cache();
        if ($cache && method_exists($cache, 'register_shutdown_function') === false) {
            $this->write();
        }
    }

    private function cache()
    {
        return BoostCache::singleton();
    }

    private function read(): array
    {
        return $this->cache() ? $this->cache()->get('index', []) : [];
    }

    public function write(): bool
    {
        if ($this->cache() && $this->isDirty) {
            $this->isDirty = false;
            return $this->cache()->set('index', $this->index, $this->expire);
        }
        return false;
    }

    public function index(bool $force = false, ?Page $target = null): int
    {
        $count = $this->index ? count($this->index) : 0;
        if ($count > 0 && !$force) {
            return $count;
        }

        $this->index = [];
        $count = 0;
        foreach (kirby()->collection('siteindexfolders') as $page) {
            // save memory when indexing
            $page = bolt($page, null, false, false);
            if (!$page || $page->hasBoost() !== true) {
                $page = null; // free memory, do not use unset()
                continue;
            }
            if ($this->add($page)) {
                $count++;
            }
            // save every n steps
            if ($count % 2000 === 0) {
                $this->write();
            }
            // only search until target is found
            if ($target && $target->id() === $page->id()) {
                $this->write();
                break;
            }
            $page = null; // free memory, do not use unset()
        }
        return $count;
    }

    public function flush(): bool
    {
        $this->index = [];
        $this->isDirty = true;
        /* on destruct
        if ($this->cache()) {
            return $this->cache()->set('index', [], $this->expire);
        }
        */
        return true;
    }

    public function findByBoostId(string $boostid, bool $throwException = true): ?Page
    {
        $boostid = trim($boostid);
        $id = A::get($this->index, $boostid);

        if ($id && $page = bolt(explode(static::SEPERATOR, $id)[0])) {
            return $page;
        } else {
            $crawl = null;
            foreach (kirby()->collection('boostidpages') as $page) {
                if ($this->add($page) && $page->boostIDField()->value() === $boostid) {
                    $crawl = $page;
                    break;
                }
            }
            $this->write();
            if ($crawl) {
                return $crawl;
            } elseif ($throwException) {
                throw new \Exception("No page found for BoostID: " . $boostid);
            }
        }
        return null;
    }

    public function add(Page $page): bool
    {
        if ($page->boostIDField()->isEmpty()) {
            return false;
        }

        $boostid = $page->boostIDField()->value();
        $id = $page->diruri() . static::SEPERATOR . $page->title()->value();
        if (kirby()->multilang()) {
            $id = $page->diruri() . static::SEPERATOR . $page->content(kirby()->defaultLanguage()->code())->title()->value();
        }

        if (!array_key_exists($boostid, $this->index) ||
            $this->index[$boostid] !== $id
        ) {
            $this->isDirty = true;
            $this->index[$boostid] = $id;
        }
        return true;
    }

    public function remove(Page $page): bool
    {
        if ($page->boostIDField()->isEmpty()) {
            return false;
        }

        $boostid = $page->boostIDField()->value();
        if (array_key_exists($boostid, $this->index)) {
            unset($this->index[$boostid]);
            $this->isDirty = true;
        }
        return true;
    }

    public function toArray(): array
    {
        return $this->index;
    }

    public function count(): int
    {
        return count($this->index);
    }

    private static $singleton;
    public static function singleton(): self
    {
        if (!static::$singleton) {
            static::$singleton = new self();
        }

        return static::$singleton;
    }

    public static function tinyurl($id): string
    {
        $url = option('bnomei.boost.tinyurl.url');
        if ($url && !is_string($url) && is_callable($url)) {
            $url = $url();
        }
        if ($url === kirby()->url('index')) {
            $url = rtrim($url, '/') . '/' . option('bnomei.boost.tinyurl.folder');
        }
        return rtrim($url, '/') . '/' . $id;
    }

    public static function modified($id): ?int
    {
        $modified = null;

        if ($id instanceof \Kirby\Cms\Page) {
            $id = $id->id();
            $modified = BoostCache::singleton()->get(crc32($id) . '-modified');
        }
        elseif ($id instanceof \Kirby\Cms\File) {
            $modified = $id->modified();
            $id = $id->id();
        }
        elseif ($id instanceof \Kirby\Cms\Site) {
            $modified = filemtime($id->contentFile());
            $id = '$';
        }

        if ($modified) { // could be false
            return $modified;
        }

        if ($page = \bolt($id)) {
            $page->boost(); // force cache update
            $modified = $page->modified();
            if ($modified) { // could be false
                return $modified;
            }
        }

        return null;
    }

    public static function page(string $id): ?Page
    {
        return static::singleton()->findByBoostId($id, false);
    }
}
