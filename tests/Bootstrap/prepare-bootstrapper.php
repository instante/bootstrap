<?php

namespace Instante\Tests\Bootstrap;

use Instante\Bootstrap\Bootstrapper;
use Tracy\Debugger;

require_once __DIR__ . '/../bootstrap.php';

final class PrepareBootstrapper
{
    public static $paths;

    /** @return Bootstrapper */
    public static function prepareBootstrapper()
    {
        @mkdir(self::$paths['temp'], 0777, TRUE);
        @mkdir(self::$paths['log'], 0777, TRUE);
        return new Bootstrapper(self::$paths);
    }

    public static function buildContainer()
    {
        set_error_handler(NULL);
        set_exception_handler(NULL);
        $container = self::prepareBootstrapper()
            ->addRobotLoadedPaths(self::$paths['app'])
            ->build();

        // reset error handlers and prevents tracy shutdown handler after tracy is enabled
        $refl = new \ReflectionClass(Debugger::class);
        if ($refl->hasProperty('reserved')) { //Tracy >= 2.4
            $prop = $refl->getProperty('reserved');
            $prop->setAccessible(TRUE);
            $prop->setValue(NULL);
        } else {
            //back compatibility with tracy <2.4
            $prop = $refl->getProperty('done');
            $prop->setAccessible(TRUE);
            $prop->setValue(TRUE);
        }

        restore_error_handler();
        restore_exception_handler();

        return $container;
    }
}

$root = __DIR__ . '/../sandbox';
PrepareBootstrapper::$paths = [
    'app' => "$root/app",
    'config' => "$root/config",
    'log' => TEMP_DIR . "/log",
    'root' => $root,
    'temp' => TEMP_DIR . "/temp",
];

