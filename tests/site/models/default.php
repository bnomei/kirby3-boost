<?php

class DefaultPage extends \Kirby\Cms\Page
{
    use \Bnomei\ModelHasBoost;

    public function someUuidRelationField(): Kirby\Cms\Field
    {
        return new \Kirby\Cms\Field($this, 'someField', $this->uuid()->id());
    }
}
