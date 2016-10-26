<?php

namespace Instante\Tests\Bootstrap;

use Tester\Assert;

require_once  __DIR__ . '/prepare-bootstrapper.php';

$_GET['debugMode'] = 'no';
$container = PrepareBootstrapper::buildContainer();

Assert::true($container->getParameters()['debugDisabledExplicitly']);
