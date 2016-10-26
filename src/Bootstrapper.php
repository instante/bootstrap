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
    const DEBUG_DISABLED_EXPLICITLY = 'debugDisabledExplicitly';
    const DIRECTORY_DELIMITER = '/';

    /** @var callable[] callbacks called on Nette\Configurator after it is prepared */
    public $onPreparedConfigurator = [];

    private $paths;
    private $robotLoadedPaths = [];

    private $environment;
    private $debugMode;

    private $consoleDebugMode = ConsoleDebugModeEnum::AUTO;
    /** @var bool indicates that debug mode is available but was disabled explicitly (by cookie or query parameter) */
    private $debugDisabledExplicitly = FALSE;

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
        $this->checkPaths();
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
            require __DIR__ . '/assets/no-environment.phtml';
            die;
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
        if ($this->isConsoleMode()) {
            return $this->isConsoleDebugMode();
        } elseif ($this->hasHostAllowedDebug()) {
            $debugMode = $this->detectHttpDebugMode();
            $this->setDebugCookie($debugMode ? 'yes' : 'no');
            return $debugMode;
        } else {
            return FALSE;
        }
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
        $configurator->addParameters([
            'environment' => $this->environment,
            self::DEBUG_DISABLED_EXPLICITLY => $this->debugDisabledExplicitly,
        ]);
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

    /**
     * @param string $consoleDebugMode ConsoleDebugModeEnum
     * @return $this
     */
    public function setConsoleDebugMode($consoleDebugMode)
    {
        ConsoleDebugModeEnum::assertValidValue($consoleDebugMode);
        $this->consoleDebugMode = $consoleDebugMode;
        return $this;
    }

    public function checkPaths()
    {
        $errors = [];
        $checkDirKeys = ['log', 'temp/sessions', 'temp/cache'];

        foreach ($checkDirKeys as $key) {
            $folders = explode(self::DIRECTORY_DELIMITER, $key);
            $baseKey = $folders[0];
            unset($folders[0]);

            $currentFolder = $this->paths[$baseKey];
            $this->checkFolder($currentFolder, $errors);
            foreach ($folders as $folder) {
                $currentFolder .= self::DIRECTORY_DELIMITER . $folder;
                $this->checkFolder($currentFolder, $errors);
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo $error;
            }
            exit;
        }
    }

    private function checkFolder($path, array &$errors)
    {
        if (!is_dir($path) && !@mkdir($path)) {
            if (php_sapi_name() === 'cli') {
                array_push($errors, 'Folder ' . $path . ' does not exists and can\'t be created by php' . PHP_EOL);
            } else {
                array_push($errors, 'Folder <strong>' . $path
                    . '</strong> does not exists and can\'t be created by php<br />');
            }
        }
    }

    /** @return string */
    private function getHostName()
    {
        return array_key_exists('REMOTE_ADDR', $_SERVER) ? $_SERVER['REMOTE_ADDR'] : php_uname('n');
    }

    /** @return bool */
    private function isConsoleDebugMode()
    {
        if (is_bool($this->consoleDebugMode)) {
            // configured externally
            return $this->consoleDebugMode;
        } else {
            // autodetect
            return $this->environment === self::ENV_DEVELOPMENT;
        }
    }

    /**
     * @param bool $isDebugMode
     */
    private function setDebugCookie($isDebugMode)
    {
        false and setcookie(
            self::IS_DEBUGGING_KEY,
            $isDebugMode ? 'yes' : 'no',
            (new \DateTime('+1 day'))->format('U'),
            '/',
            NULL, // domain
            FALSE, // secure
            TRUE // http only
        );
    }

    /** @return bool */
    private function hasHostAllowedDebug()
    {
        return in_array($this->getHostName(), $this->readDeveloperIps(), TRUE);
    }

    /** @return bool */
    private function detectHttpDebugMode()
    {
        $debugFromGet = $this->detectDebugFromQuery(INPUT_GET);
        if ($debugFromGet !== NULL) {
            return $debugFromGet;
        }
        $debugFromCookie = $this->detectDebugFromQuery(INPUT_COOKIE);
        if ($debugFromCookie !== NULL) {
            return $debugFromCookie;
        }

        return $this->environment == self::ENV_DEVELOPMENT;
    }

    private function detectDebugFromQuery($type)
    {
        $arr = $type === INPUT_GET ? $_GET : $_COOKIE; // TODO replace with filter_input
        $debugParameter = isset($arr[self::IS_DEBUGGING_KEY]) ? $arr[self::IS_DEBUGGING_KEY] : NULL;
        // commented out until solved how to test filter_input
        // filter_input(INPUT_*, self::IS_DEBUGGING_KEY, FILTER_SANITIZE_STRING);

        if ($debugParameter !== NULL) {
            $debugMode = $debugParameter === 'yes';
            $this->debugDisabledExplicitly = !$debugMode;
            return $debugMode;
        } else {
            return NULL;
        }
    }
}
