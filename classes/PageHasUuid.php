<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\Page;
use Kirby\Toolkit\A;

trait PageHasUuid
{
    public static function create(array $props): Page
    {
        if (!A::get($props['content'], 'uuid')) {
            $uuid = token();
            // make 100% sure its unique
            while (BoostIndex::singleton()->find($uuid, false)) {
                $uuid = token();
            }
            $props['content']['uuid'] = $uuid;
        }

        return parent::create($props);
    }
}
