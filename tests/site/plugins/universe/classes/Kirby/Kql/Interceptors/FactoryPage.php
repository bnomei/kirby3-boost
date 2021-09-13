<?php

namespace Kirby\Kql\Interceptors;

class FactoryPage extends \Kirby\Kql\Interceptors\Cms\Page
{
    public function allowedMethods(): array
    {
        return array_merge(parent::allowedMethods(), [
            'hasBoost'
        ]);
    }
}