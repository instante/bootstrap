<?php

namespace Instante\Tests\Bootstrap;

use Tester\Assert;

require_once  __DIR__ . '/prepare-bootstrapper.php';

PrepareBootstrapper::buildContainer();

Assert::true(class_exists(TestClass::class));
Assert::same('bar', TestClass::FOO);
