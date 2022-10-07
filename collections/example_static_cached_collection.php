<?php

// this is an example how to create a static cached collection using my Lapse Plugin
// https://forum.getkirby.com/t/remember-this-caching-in-kirby/23705/4?u=bnomei
// https://github.com/bnomei/kirby3-lapse

class PagesThatCanBeReferencedWithoutIndex
{
    public static $cache = null;
    public static function load(): ?\Kirby\Cms\Pages
    {
        // if cached then return that
        if (static::$cache) {
            return static::$cache;
        }

        // use lapse to cache the diruri
        // this will avoid index()
        $cachedDirUris = \Bnomei\Lapse::io(
            static::class, // a key for the cache
            function () {
                $collection = site()->index(true)->filterBy('intendedTemplate', 'in', [
                    'person',
                    'organisation',
                    'document',
                    'place'
                ]);
                return array_values($collection->map(function ($page) {
                    return $page->diruri();
                }));
            },
            10 // expire in 10 minutes
        );

        // use bolt from autoid/boost to get pages quickly
        $pages = array_map(function ($diruri) {
            return \Bnomei\Bolt::page($diruri);
        }, $cachedDirUris);
        // remove those that bolt did not find
        $pages = array_filter($pages, function ($page) {
            return is_null($page) ? false : true;
        });

        $collectionFromDirUris = new \Kirby\Cms\Pages($pages);

        static::$cache = $collectionFromDirUris;
        return static::$cache;
    }
}

return function () {
    return PagesThatCanBeReferencedWithoutIndex::load();
};
