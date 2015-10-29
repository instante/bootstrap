<?php

namespace Instante\Tests\Bootstrap;

use Instante\Helpers\FileSystem;
use Tester\Assert;
use Tracy\Debugger;

require_once  __DIR__ . '/prepare-bootstrapper.php';

$container = PrepareBootstrapper::buildContainer();

$paths = [];
foreach (PrepareBootstrapper::$paths as $key=>$val) {
    $paths[$key] = FileSystem::simplifyPath($val);
}

Assert::equal($paths, $container->getParameters()['paths']);

Debugger::log('foo');
Assert::match('~foo~', file_get_contents($paths['log'] . '/info.log'));

Assert::same($paths['temp'], $container->getParameters()['tempDir']);
