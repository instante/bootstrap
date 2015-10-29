<?php

namespace Instante\Tests\Bootstrap;

use Tester\Assert;

require_once  __DIR__ . '/prepare-bootstrapper.php';

$_SERVER['REMOTE_ADDR'] = 'developer-ip';

Assert::false(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);

$_COOKIE['debugMode'] = 'yes';
Assert::true(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);

$_COOKIE['debugMode'] = 'no';
Assert::false(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);

$_GET['debugMode'] = 'yes';
Assert::true(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);

$_GET['debugMode'] = 'no';
Assert::false(PrepareBootstrapper::buildContainer()->getParameters()['debugMode']);
