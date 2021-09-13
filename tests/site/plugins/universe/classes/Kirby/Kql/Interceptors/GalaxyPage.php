<?php

namespace Kirby\Kql\Interceptors;

class GalaxyPage extends \Kirby\Kql\Interceptors\Cms\Page
{
    public function allowedMethods(): array
    {
        return array_merge(parent::allowedMethods(), [
            'simulationTick',
            'hasBoost',
        ]);
    }
}