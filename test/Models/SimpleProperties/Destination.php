<?php

namespace Test\Models\SimpleProperties;

/**
 * Class Destination
 *
 * @package Test\Models\SimpleProperties
 */
class Destination
{
    public $name;

    public function __construct($name = '')
    {
        $this->name = $name;
    }
}
