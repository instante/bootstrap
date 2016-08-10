<?php


namespace Instante\Bootstrap;


use Instante\Helpers\FileSystem;
use InvalidArgumentException;
use Nette\Configurator;
use Nette\DI\Container;
use Tracy\Debugger;

class Bootstrapper
{
    const ENV_DEVELOPMENT = 'development';
    const ENV_STAGE = 'stage';
    const ENV_PRODUCTION = 'production';
    const IS_DEBUGGING_KEY = 'debugMode';

    /** @var callable[] callbacks called on Nette\Configurator after it is prepared */
    public $onPreparedConfigurator = [];

    private $paths;
    private $robotLoadedPaths = [];

    private $environment;
    private $debugMode;

    /**
     * @param array $paths
     * must contain these keys containing absolute paths: app, config, log, root, temp
     */
    public function __construct(array $paths)
    {
        if (count($d = array_diff(['app', 'config', 'log', 'root', 'temp'], array_keys($paths))) > 0) {
            throw new InvalidArgumentException('missing paths: ' . implode(',', $d));
        }
        if (class_exists(FileSystem::class)) { // check instante utils are installed
            foreach ($paths as &$path) {
                $path = FileSystem::simplifyPath($path);
            }
        }
        $this->paths = $paths;
    }

    /**
     * Add paths to be scanned by Nette robot loader.
     *
     * @param string|array $paths
     * @return $this
     */
    public function addRobotLoadedPaths($paths)
    {
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        $this->robotLoadedPaths = array_merge($this->robotLoadedPaths, $paths);
        return $this;
    }

    /** @return Container */
    public function build()
    {
        $this->environment = $this->detectEnvironment();
        $this->debugMode = $this->detectDebugMode();
        $configurator = $this->prepareConfigurator();
        $this->prepareRobotLoader($configurator);
        $this->addConfigFiles($configurator);
        foreach ($this->onPreparedConfigurator as $callback) {
            $callback($configurator);
        }
        return $configurator->createContainer();
    }

    /**
     * @return string enumeration of ENV_*
     */
    private function detectEnvironment()
    {
        if (file_exists($f = $this->paths['config'] . "/environment")) {
            return trim(file_get_contents($f));
        } else {
            die("The application is not configured to run in this environment - no environment file found.");
        }
    }

    /**
     * Detects if debug mode should be enabled.
     *
     * To enable debug mode from url query, use ?debugMode=1; to disable, use ?debugMode=0.
     * Configuration is then stored to cookie debugMode=yes|no
     *
     * @return bool true if debug mode was set
     */
    private function detectDebugMode()
    {
        $developerIps = $this->readDeveloperIps();
        $debugMode = FALSE;
        $debugModeAllowed = in_array(array_key_exists('REMOTE_ADDR', $_SERVER) ? $_SERVER['REMOTE_ADDR'] :
            php_uname('n'), $developerIps, TRUE);
        if ($debugModeAllowed) {
            //just a complicated but proof method to get cookie parameter
            $useFilter = (!in_array(ini_get('filter.default'), ['', 'unsafe_raw']) || ini_get('filter.default_flags'));
            $cookies = $useFilter ? filter_input_array(INPUT_COOKIE, FILTER_UNSAFE_RAW) :
                (empty($_COOKIE) ? [] : $_COOKIE);
            // (C) Nette Framework

            $isCookieSet = array_key_exists(self::IS_DEBUGGING_KEY, $cookies);
            $debugMode = ($isCookieSet && $cookies[self::IS_DEBUGGING_KEY] === 'yes')
                || (!$isCookieSet && $this->environment == self::ENV_DEVELOPMENT);
            if (array_key_exists(self::IS_DEBUGGING_KEY, $_GET)) {
                $debugMode = in_array($_GET[self::IS_DEBUGGING_KEY], ['yes', 'true', 1], TRUE);
            }

            $cookieExpiration = new \DateTime('+1 day');
            setcookie(self::IS_DEBUGGING_KEY, $debugMode ? 'yes' :
                'no', $cookieExpiration->format('U'), '/', NULL, FALSE, TRUE);
        }

        return $debugMode;
    }

    private function readDeveloperIps()
    {
        $ipsFile = $this->paths['config'] . '/developerIps';
        if (file_exists($ipsFile)) {
            return array_map('trim', file($ipsFile));
        } else {
            return ['127.0.0.1', '::1'];
        }
    }

    /** @return Configurator */
    private function prepareConfigurator()
    {
        $configurator = new Configurator;
        $configurator->addParameters([
            'appDir' => $this->paths['app'], //compatibility with Nette apps using %appDir%
            'paths' => $this->paths,
        ]);

        // Enable Nette Debugger for error visualisation & logging

        $configurator->setDebugMode($this->debugMode);
        $configurator->addParameters(['environment' => $this->environment]);
        if (class_exists(Debugger::class)) {
            $configurator->enableDebugger($this->paths['log']);
        }

        // Specify folder for cache
        $configurator->setTempDirectory($this->paths['temp']);

        return $configurator;
    }

    private function prepareRobotLoader(Configurator $configurator)
    {
        $robotLoader = $configurator->createRobotLoader();
        foreach ($this->robotLoadedPaths as $path) {
            $robotLoader->addDirectory($path);
        }
        $robotLoader->register();
    }

    private function addConfigFiles(Configurator $configurator)
    {
        // general config
        $configurator->addConfig($this->paths['config'] . '/default.neon');

        // debug mode dependent config
        if ($this->debugMode && file_exists($debugConfig = $this->paths['config'] . '/debug.neon')) {
            $configurator->addConfig($debugConfig);
        }

        // environment dependent config
        if (file_exists($envConfig = $this->paths['config'] . '/env.' . $this->environment . '.neon')) {
            $configurator->addConfig($envConfig);
        }

        // local machine config
        if (file_exists($localConfig = $this->paths['config'] . '/local.neon')) {
            $configurator->addConfig($localConfig);
        }
    }

    protected function isConsoleMode() // marked as protected to enable testing
    {
        return php_sapi_name() === 'cli';
    }
}
