<?php

namespace Instante\Tests\Bootstrap;

use Instante\Bootstrap\Bootstrapper;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/prepare-bootstrapper.php';

$_SERVER['REMOTE_ADDR'] = 'developer-ip';

try {
    $parameters = PrepareBootstrapper::buildContainer()->getParameters();
    Assert::false($parameters[Bootstrapper::IS_DEBUGGING_KEY]);
    Assert::false($parameters[Bootstrapper::DEBUG_DISABLED_EXPLICITLY]);

    $_COOKIE['debugMode'] = 'yes';
    $parameters = PrepareBootstrapper::buildContainer()->getParameters();
    Assert::true($parameters[Bootstrapper::IS_DEBUGGING_KEY]);
    Assert::false($parameters[Bootstrapper::DEBUG_DISABLED_EXPLICITLY]);

    $_COOKIE['debugMode'] = 'no';
    $parameters = PrepareBootstrapper::buildContainer()->getParameters();
    Assert::false($parameters[Bootstrapper::IS_DEBUGGING_KEY]);
    Assert::true($parameters[Bootstrapper::DEBUG_DISABLED_EXPLICITLY]);

    $_GET['debugMode'] = 'yes';
    $parameters = PrepareBootstrapper::buildContainer()->getParameters();
    Assert::true($parameters[Bootstrapper::IS_DEBUGGING_KEY]);
    Assert::false($parameters[Bootstrapper::DEBUG_DISABLED_EXPLICITLY]);

    $_GET['debugMode'] = 'no';
    $parameters = PrepareBootstrapper::buildContainer()->getParameters();
    Assert::false($parameters[Bootstrapper::IS_DEBUGGING_KEY]);
    Assert::true($parameters[Bootstrapper::DEBUG_DISABLED_EXPLICITLY]);
} catch (\Exception $e) {
    /** This is important because Tracy\Debugger may have overtaken exception handler */
    /** @noinspection PhpInternalEntityUsedInspection */
    Environment::handleException($e);
}
