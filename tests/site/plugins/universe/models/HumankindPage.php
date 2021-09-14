<?php

namespace Universe;

use Kirby\Cms\Page;
use Kirby\Toolkit\A;

class HumankindPage extends Page
{
    public function addAllHumans()
    {
    	$h = [];
    	foreach(kirby()->collection('humans') as $human){
    		$h[] = $human->boostid()->value;
    	}
    	sort($h);
    	$this->update([
    		'humans' => implode(',', $h),
    	]);
    }
}