<?php

namespace WDK;

use Timber\Timber;

class WDK
{
    public function __construct($options = [])
    {
        $locations = !empty($options['locations'])? $options['locations']:[];
        add_action('init', static function () use ($locations) {
            System::Start($locations);
        });
    }
}