<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\Page;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

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
        foreach (site()->siteindexfolders() as $page) {
            // save memory when indexing
            $page = \Bnomei\Bolt::page($page, null, false, false);
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

    public function find(string $uuid, bool $throwException = true): ?Page
    {
        $uuid = str_replace('page://','', trim($uuid));
        $diruri = $this->diruri($uuid);

        if ($diruri && $page = \Bnomei\Bolt::page($diruri)) {
            return $page;
        } else {
            $crawl = null;

            // try UUID cache via bolt first
            if ($page = \Bnomei\Bolt::page($uuid)) {
                $this->add($page);
                $crawl = $page;
            }

            if (!$crawl) {
                // then try crawling index
                foreach (site()->index(option('bnomei.boost.drafts')) as $page) {
                    if ($this->add($page) && $page->uuid()->id() === $uuid) {
                        $crawl = $page;
                        break;
                    }
                }
            }

            $this->write();
            if ($crawl) {
                return $crawl;
            } elseif ($throwException) {
                throw new \Exception("No page found for uuid: " . $uuid);
            }
        }
        return null;
    }

    public function data(string $uuid): ?array
    {
        if ($data = A::get($this->index, $uuid)) {
            if (Str::contains($data, static::SEPERATOR)) {
                list($diruri, $title, $template) = explode(static::SEPERATOR, $data);
                $data = [
                    'diruri' => $diruri,
                    'template' => $template,
                    'title' => $title,
                    'uuid' => $uuid,
                ];
            }
        }

        return $data ?? null;
    }

    public function diruri(string $uuid)
    {
        $data = $this->data($uuid);
        return A::get($data, 'diruri');
    }

    public function add(Page $page): bool
    {
        $uuid = $page->uuid()->id();

        if (empty($uuid)) {
            return false;
        }

        $id = $page->diruri() . static::SEPERATOR . $page->title()->value() . static::SEPERATOR . $page->template()->name();
        if (kirby()->multilang()) {
            $id = $page->diruri() . static::SEPERATOR . $page->content(kirby()->defaultLanguage()->code())->title()->value() . static::SEPERATOR . $page->template()->name();
        }

        if (!array_key_exists($uuid, $this->index) ||
            $this->index[$uuid] !== $id
        ) {
            $this->isDirty = true;
            $this->index[$uuid] = $id;
        }
        return true;
    }

    public function remove(Page $page): bool
    {
        $uuid = $page->uuid()->id();
        if (empty($uuid)) {
            return false;
        }

        if (array_key_exists($uuid, $this->index)) {
            unset($this->index[$uuid]);
            $this->isDirty = true;
        }
        return true;
    }

    public function toArray(): array
    {
        return $this->index;
    }

    public function toKVs(): array
    {
        $kv = [];
        foreach ($this->toArray() as $uuid => $data) {
            list($diruri, $title, $template) = explode(\Bnomei\BoostIndex::SEPERATOR, $data);
            $kv[] = [
                'id' => $uuid, // needed for kirby\cms\collections class to work
                'diruri' => $diruri,
                'template' => $template,
                'text' => $title,
                'value' => $uuid,
            ];
        }
        usort($kv, function ($a, $b) {
            if ($a['diruri'] == $b['diruri']) {
                return 0;
            }
            return ($a['diruri'] < $b['diruri']) ? -1 : 1;
        });
        $kv = array_map(function ($item) {
            return new \Kirby\Toolkit\Obj($item);
        }, $kv);
        return $kv;
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

    public static function page(string $id): ?Page
    {
        return static::singleton()->find($id, false);
    }
}
