<?php

namespace Instante\Tests\Bootstrap;

use Tester\Assert;

require_once __DIR__ . '/prepare-bootstrapper.php';

$loaded = PrepareBootstrapper::buildContainer()->getParameters()['loaded'];

Assert::true(isset($loaded['default']));
Assert::true(isset($loaded['stage']));
Assert::true(isset($loaded['local']));
Assert::false(isset($loaded['debug']));
Assert::false(isset($loaded['development']));

$_GET['debugMode'] = 'yes';
$_SERVER['REMOTE_ADDR'] = 'developer-ip';

$loaded = PrepareBootstrapper::buildContainer()->getParameters()['loaded'];
Assert::true(isset($loaded['default']));
Assert::true(isset($loaded['stage']));
Assert::true(isset($loaded['local']));
Assert::true(isset($loaded['debug']));
Assert::false(isset($loaded['development']));
