<?php

namespace Instante\Tests\Bootstrap;

use Tester\Assert;

require_once  __DIR__ . '/prepare-bootstrapper.php';

Assert::same('stage', PrepareBootstrapper::buildContainer()->getParameters()['environment']);
