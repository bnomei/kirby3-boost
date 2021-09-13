<?php

namespace Universe;

use Kirby\Cms\Page;

class GalaxyPage extends \Bnomei\BoostPage
{
    public static function create(array $props): Page
    {
        $page = parent::create($props);
        $page = $page->changeStatus('unlisted');
        return $page;
    }

    public function simulationTick(): int
    {
        if (!kirby()->user() || kirby()->user()->role()->name() === 'api') {
            kirby()->impersonate('kirby');
        }
        $this->increment('tick');

        return $this->tick()->toInt() + 1; // update happens later
    }
}
