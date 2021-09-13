<?php

use Kirby\Cms\Page;
use Kirby\Toolkit\Str;

class HumanPage extends \Bnomei\BoostPage
{
    public static function create(array $props): Page
    {
        $props['slug'] = Str::slug(Str::random(32));
        $props['content']['title'] = $props['slug'];

        $page = parent::create($props);
        $page = $page->changeStatus('unlisted');
        return $page;
    }
}
