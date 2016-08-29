<?php

use Instante\Bootstrap\Bootstrapper;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

$root = __DIR__ . '/../sandbox/dev/';

function rrmdir($dir) {
    $result = true;
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir"){
                    rrmdir($dir."/".$object);
                }else{
                    unlink($dir."/".$object);
                }
            }
        }
        reset($objects);
        if (!rmdir($dir)) {
            $result = false;
        };
    }

    return $result;
}

$paths = [
    'app' => "./app",
    'config' => "./config",
    'root' => "./root",
    'log' => "\\badlog",
    'temp' => $root . "good_temp",
];

register_shutdown_function(function () use ($paths) {
    Assert::true(is_dir($paths['temp']));
    Assert::true(is_dir($paths['temp'] . '/sessions'));
    Assert::false(is_dir($paths['log']));
    Assert::true(rrmdir($paths['temp']));
    exit(0);
});

$bootstrapper = new Bootstrapper($paths);
$bootstrapper->checkPaths();

Assert::true(false, 'exit() should be called!');
