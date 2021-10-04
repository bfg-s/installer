<?php

namespace Bfg\Installer;

use Illuminate\Support\Facades\Facade as FacadeIlluminate;

/**
 * Class Facade.
 * @package Bfg\Installer
 */
class Facade extends FacadeIlluminate
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return InstallerFacade::class;
    }
}
