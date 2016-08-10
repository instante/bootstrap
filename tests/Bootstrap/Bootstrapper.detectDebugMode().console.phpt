<?php

namespace Instante\Tests\Bootstrap;

use Instante\Bootstrap\Bootstrapper;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/prepare-bootstrapper.php';

TestBootstrapper::$fakeConsoleMode = TRUE;
try {
    Assert::false(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);
    $bootstrapper = PrepareBootstrapper::prepareBootstrapper();
    $bootstrapper->setConsoleDebugMode(FALSE);
    Assert::false(PrepareBootstrapper::buildContainer($bootstrapper)->getParameters()['debugMode']);

    $bootstrapper = PrepareBootstrapper::prepareBootstrapper();
    $bootstrapper->setConsoleDebugMode(TRUE);
    Assert::true(PrepareBootstrapper::buildContainer($bootstrapper)->getParameters()['debugMode']);

    set_bootstrap_test_sandbox('dev');
    Assert::true(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);

    $bootstrapper = PrepareBootstrapper::prepareBootstrapper();
    $bootstrapper->setConsoleDebugMode(FALSE);
    Assert::false(PrepareBootstrapper::buildContainer($bootstrapper)->getParameters()['debugMode']);

} catch (\Exception $e) {
    /** @noinspection PhpInternalEntityUsedInspection */
    Environment::handleException($e);
}
