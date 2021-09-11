<?php

declare(strict_types=1);

namespace Bnomei;

class BoostPage extends \Kirby\Cms\Page
{
    // will add some plugin specific methods AND overwrite...
    // ppublic static function create(array $props): Page
    // public function writeContent(array $data, string $languageCode = null): bool
    // public function readContent(string $languageCode = null): array
    use PageHasBoost;
}
