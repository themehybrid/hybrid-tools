<?php

namespace Hybrid\Tools\Facades;

use Hybrid\Core\Facades\Facade;

/**
 * @see \Hybrid\Tools\Config\Repository
 *
 * @method static array all()
 * @method static bool has($key)
 * @method static mixed get($key, $default = null)
 * @method static void prepend($key, $value)
 * @method static void push($key, $value)
 * @method static void set($key, $value = null)
 */
class Config extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'config';
    }

}
