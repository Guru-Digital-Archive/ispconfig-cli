<?php

namespace GDM\Helpers;

use \Exception;
use \GDM\ISPConfig\SoapClient;
use \Symfony\Component\Yaml\Parser;

class ISPConfig
{

    /**
     * Default config filename
     * @var string
     */
    protected static $configFile = 'ispconfig-cli.yml';

    /**
     * Contains keys to parameters that must be contained in the config file
     * @var array
     */
    protected static $requiredSettings = ['url', 'user', 'password'];

    /**
     * Holds the setting loaded from the config file
     * @var array
     */
    private $settings = [];

    /**
     * Singleton instance of the ISPConfig helper
     * @var ISPConfig
     */
    protected static $instance = null;

    /**
     * Instance of the ISPConfig soap client
     * @var SoapClient
     */
    private $soapClient = null;

    protected function __construct()
    {
    }

    /**
     *
     * @return SoapClient
     */
    public function getSoapClient()
    {
        if (!$this->soapClient) {
            $config           = $this->getConfig();
            $this->soapClient = new SoapClient($config['url'], $config['user'], $config['password']);
        }
        return $this->soapClient;
    }

    /**
     *
     * @return ISPConfig
     */
    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }
        return static::$instance;
    }

    protected function getConfigFile()
    {
        $etcConf    = DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . self::$configFile;
        $dir        = (class_exists('\Phar') && \Phar::running(false)) ? dirname(\Phar::running(false)) : dirname(__FILE__);
        $configFile = $dir . DIRECTORY_SEPARATOR . self::$configFile;

        $checked    = [];
        $checked[]  = $etcConf;
        if (file_exists($etcConf)) {
            $configFile = $etcConf;
        } else {
            $max = 10;
            $i   = 1;
            while (!file_exists($configFile)) {
                $checked[] = $configFile;
                if ($i >= $max) {
                    throw new Exception('Unable to find config file: '.PHP_EOL . implode(PHP_EOL, $checked));
                }
                $dir        = dirname($dir);
                $configFile = $dir . DIRECTORY_SEPARATOR . self::$configFile;
                $i ++;
            }
        }

        return $configFile;
    }

    public function getConfig()
    {
        if (!$this->settings) {
            $yaml           = new Parser();
            $this->settings = $yaml->parse(file_get_contents($this->getConfigFile()));
            $this->checkConfig();
        }
        return $this->settings;
    }

    /**
     *
     * @return \GDM\ISPConfigCli\Command[]
     */
    public function getCommands()
    {
        $classTemplate = '\GDM\ISPConfigCli\{CommandName}Command';
        $commands      = [];
        $reflection    = new \ReflectionClass('\GDM\ISPConfig\SoapClient');
        foreach ($reflection->getMethods() as $method) {
            /* @var $method ReflectionMethod */
            $remoteMethodName = $method->getName();
            if ($remoteMethodName !== '__construct') {
                $toTest  = str_replace('{CommandName}', ucfirst($remoteMethodName), $classTemplate);
                $class   = class_exists($toTest) ? $toTest : '\GDM\ISPConfigCli\Command';
                $command = new $class($remoteMethodName);
                $command->setDescription($remoteMethodName);

                foreach ($method->getParameters() as $parameter) {
                    /* @var $parameter ReflectionParameter */
                    $command->addArgument($parameter->getName(), $parameter->isOptional() ? \Symfony\Component\Console\Input\InputArgument::OPTIONAL : \Symfony\Component\Console\Input\InputArgument::REQUIRED, '...');
                }
                $commands[] = $command;
            }
        }
        return $commands;
    }

    protected function checkConfig()
    {
        foreach (self::$requiredSettings as $requiredSettings) {
            if (!isset($this->settings[$requiredSettings])) {
                throw new Exception('The required settings ' . $requiredSettings . ' was not found in the config file ' . $this->getConfigFile());
            }
        }
        return true;
    }
}
