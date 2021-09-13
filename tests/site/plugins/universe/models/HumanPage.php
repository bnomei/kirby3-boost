<?php

namespace Universe;

use Kirby\Cms\Field;
use Kirby\Cms\Page;
use Kirby\Toolkit\Str;

class HumanPage extends \Bnomei\BoostPage
{
    public static function create(array $props): Page
    {
        $props['slug'] = Str::slug(Str::random(16));
        $props['content']['title'] = $props['slug'];

        $page = parent::create($props);
        $page = $page->changeStatus('unlisted');
        return $page;
    }

    public function kidsCount(): int
    {
        return count($this->kids()->split());
    }
}
