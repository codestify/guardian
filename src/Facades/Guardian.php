<?php

namespace Shah\Guardian\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Shah\Guardian\Guardian
 */
class Guardian extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Shah\Guardian\Guardian::class;
    }
}
