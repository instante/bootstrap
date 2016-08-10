<?php

namespace Instante\Tests\Bootstrap;

use Instante\Bootstrap\Bootstrapper;
use Nette\Configurator;
use Tracy\Debugger;

require_once __DIR__ . '/../bootstrap.php';

class TestBootstrapper extends Bootstrapper
{
    public static $fakeConsoleMode = FALSE;

    protected function isConsoleMode()
    {
        return self::$fakeConsoleMode;
    }

}

final class PrepareBootstrapper
{
    public static $paths;

    public static $containerClassIdBase;
    public static $containerNumber = 0;

    /** @return Bootstrapper */
    public static function prepareBootstrapper()
    {
        @mkdir(self::$paths['temp'], 0777, TRUE);
        @mkdir(self::$paths['log'], 0777, TRUE);
        return new TestBootstrapper(self::$paths);
    }

    public static function buildContainer()
    {
        $bootstrapper = static::prepareBootstrapper();
        $bootstrapper->onPreparedConfigurator[] = function (Configurator $configurator) {
            $configurator->addParameters(['container' => ['class' => self::getContainerClass()]]);
        };
        $bootstrapper->addRobotLoadedPaths(self::$paths['app']);
        $container = $bootstrapper->build();

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

        return $container;
    }

    private static function getContainerClass()
    {
        return self::$containerClassIdBase . (self::$containerNumber++);
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

PrepareBootstrapper::$containerClassIdBase = sprintf('Container_%s_%s_', time(), rand(0, 10000));
