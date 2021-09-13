<?php

namespace Universe;

use Kirby\Cms\Page;

class PlanetPage extends \Bnomei\BoostPage
{
    public static function create(array $props): Page
    {
        $page = parent::create($props);
        $page = $page->changeStatus('unlisted');
        return $page;
    }
}