<?php

namespace Instante\Tests\Bootstrap;

use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/prepare-bootstrapper.php';

$_SERVER['REMOTE_ADDR'] = 'developer-ip';

try {
    Assert::false(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);

    $_COOKIE['debugMode'] = 'yes';
    Assert::true(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);

    $_COOKIE['debugMode'] = 'no';
    Assert::false(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);

    $_GET['debugMode'] = 'yes';
    Assert::true(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);

    $_GET['debugMode'] = 'no';
    Assert::false(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);
} catch (\Exception $e) {
    /** This is important because Tracy\Debugger may have overtaken exception handler */
    /** @noinspection PhpInternalEntityUsedInspection */
    Environment::handleException($e);
}
