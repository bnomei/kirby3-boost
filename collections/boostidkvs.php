<?php

class StaticBoostIdKVs
{
    public static $cache = null;
    public static function load(): ?array
    {
        if (static::$cache) {
            return static::$cache;
        }

        static::$cache = \Bnomei\BoostIndex::singleton()->toKVs();

        return static::$cache;
    }
}

return function () {
    return new \Kirby\Cms\Collection(StaticBoostIdKVs::load());
};
