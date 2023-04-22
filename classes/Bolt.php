<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Filesystem\Dir;
use Kirby\Cms\Page;
use Kirby\Toolkit\A;

final class Bolt
{
    /**
     * @var string
     */
    private $root;

    /**
     * @var string
     */
    private $extension;

    /**
     * @var array<string>
     */
    private $modelFiles;
    /**
     * @var Page|null
     */
    private $parent;

    /**
     * @var array<string,Page>
     */
    private static $idToPage;

    public function __construct(?Page $parent = null)
    {
        $kirby = kirby();
        $this->root = $kirby->root('content');
        if ($parent) {
            $this->parent = $parent;
            $this->root = $parent->root();
        }

        $this->extension = $kirby->contentExtension();
        if ($kirby->multilang()) {
            $this->extension = $kirby->defaultLanguage()->code() . '.' . $this->extension;
        }

        $extension = $this->extension;
        $this->modelFiles = array_map(static function ($value) use ($extension) {
            return $value . '.' . $extension;
        }, array_keys(Page::$models));

        if (option('debug')) {
            $this->flush();
        }
    }

    private static function cache()
    {
        return BoostCache::singleton();
    }

    public function flush()
    {
        static::$idToPage = [];

        // this would flush content cache as well or depending on cache driver everything
        // do NOT do this ever!
        // // BoostCache::singleton()->flush();

        return true;
    }

    public function lookup(string $id, bool $cache = true): ?Page
    {
        $lookup = A::get(static::$idToPage, $id);
        if (!$lookup && $cache && static::cache()) {
            if ($diruri = static::cache()->get('bolt/' . hash(BoostCache::hashalgo(), $id))) {
                // bolt will ignore caches with invalid paths and update them automatically
                // it does not need to be flushed ever
                if ($page = $this->findByID($diruri, false)) {
                    $this->pushLookup($id, $page);
                    $lookup = $page;
                } else {
                    static::cache()->remove('bolt/' . hash(BoostCache::hashalgo(), $id));
                }
            }
        }
        return $lookup;
    }

    public function pushLookup(string $id, Page $page): void
    {
        static::$idToPage[$id] = $page;

        // only update if necessary
        $diruri = $page->diruri();
        if ($diruri !== static::cache()->get('bolt/' . hash(BoostCache::hashalgo(), $id))) {
            static::cache()->set(hash(BoostCache::hashalgo(), $id) . '-bolt', $diruri, option('bnomei.boost.expire'));
        }
    }

    public static function toArray(): array
    {
        if (!static::$idToPage) {
            static::$idToPage = [];
        }
        return static::$idToPage;
    }

    public function findByID(string $id, bool $cache = true, bool $extend = true): ?Page
    {

        $page = $this->lookup($id, $cache);
        if ($page) {
            return $page;
        }


        $draft = false;
        $treeid = null;
        $parent = $this->parent;
        $parts = explode('/', $id);

        foreach ($parts as $part) {
            if ($part === '_drafts') {
                $draft = true;
                $this->root .= '/' . '_drafts';
                continue;
            }
            $numSplit = array_reverse(explode(Dir::$numSeparator, $part));
            $partWithoutNum = $numSplit[0];
            $num = count($numSplit) > 1 ? intval($numSplit[1]) : null;
            $treeid = $treeid ? $treeid . '/' . $partWithoutNum : $partWithoutNum;
            $page = $this->lookup($treeid, $cache);
            if ($page) {
                $parent = $page;
                $this->root = $page->root(); // loop
                continue;
            }

            $params = [
                'root' => null,
                'dirname' => null,
                'parent' => $parent,
                'slug' => $partWithoutNum,
                'num' => $num,
                'model' => null,
            ];

            // if dir exists
            if (is_dir($this->root . '/' . $part)) {
                $params['root'] = $this->root . '/' . $part;
                $params['dirname'] = $part;
                foreach ($this->modelFiles as $modelFile) {
                    if (file_exists($params['root'] . '/' . $modelFile)) {
                        $template = str_replace('.' . $this->extension, '', $modelFile);
                        $params['template'] = $template;
                        $params['model'] = $template;
                        break;
                    }
                }
            } else { // search for dir
                $directory = @opendir($this->root);
                if ($directory) {
                    while ($file = readdir($directory)) {
                        if (strpos($file, '.') !== false) {
                            continue;
                        }
                        $_part = Dir::$numSeparator . $part;
                        if (substr($file, -strlen($_part)) === $_part) {
                            $params['root'] = $this->root . '/' . $file;
                            $params['diruri'] = $file;
                            if (preg_match('/^([0-9]+)' . Dir::$numSeparator . '(.*)$/', $file, $match)) {
                                $params['num'] = intval($match[1]);
                                $params['slug'] = $match[2];
                            }
                        }
                        if ($params['root']) {
                            foreach ($this->modelFiles as $modelFile) {
                                if (file_exists($params['root'] . '/' . $modelFile)) {
                                    $template = str_replace('.' . $this->extension, '', $modelFile);
                                    $params['template'] = $template;
                                    $params['model'] = $template;
                                    break;
                                }
                            }
                            break;
                        }
                    }
                    closedir($directory);
                }
            }

            if (! $params['root']) {
                return null; // not found
            }
            if ($draft === true) {
                $params['isDraft'] = $draft;
                // Only direct subpages are marked as drafts
                $draft = false;
            }
            $page = null; //kirby()->extension('pages', $this->root);
            if (! $page) {
                $page = Page::factory($params);
                $this->pushLookup($treeid, $page);

                if ($extend) {
                    kirby()->extend([
                        'pages' => [$this->root => $page,]
                    ]);
                }
            }
            $parent = $page;
            $this->root = $params['root']; // loop
        }
        return $page;
    }

    public static function page(string $id, ?Page $parent = null, bool $cache = true, bool $extend = true): ?Page
    {
        return (new self($parent))->findByID($id, $cache, $extend);
    }

    public static function index($callback)
    {
        $count = 0;
        foreach (site()->siteindexfolders() as $page) {
            // save memory when indexing
            $page = \Bnomei\Bolt::page($page, null, false, false);
            if ($page && !is_string($callback) && is_callable($callback)) {
                $callback($page);
            }
            $count++;
            $page = null; // free memory, do not use unset()
        }
        return $count;
    }
}
