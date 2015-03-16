<?php

namespace GDM\ISPConfigCli\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateSiteCommand extends \GDM\ISPConfigCli\Command {

    /**
     *
     * @var CreateSiteCommandConifg
     */
    private $config;
    private $specialChars = '@$%^&*()-+?_';

    const COMMAND_NAME = 'createSite';

    protected function configure() {
        $this->
                setName(static::COMMAND_NAME)->
                setDescription('Create a site domain, databse and database user in one command')->
                addArgument("server", \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Name of the server to provis this site on')->
                addArgument("client", \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Name or Id of the client who owns this site')->
                addArgument("domain", \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Domain name to setup')->
                addArgument("dbname", \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Database name to setup')->
                addArgument("dbuser", \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Database user to setup or if exists to assign to database')->
                addArgument("dbpass", \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Database password to assign to user (Only required if DB User does not exist)')->
                addOption("genpass", 'g', \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'If set, a random db password will be generated'
        );
    }

    protected function getConfig() {
        if (!$this->config) {
            $iniConf      = \GDM\Helpers\ISPConfig::getInstance()->getConfig();
            $this->config = new CreateSiteCommandConifg();
            foreach ($this->config as $prop => $value) {
                if (isset($iniConf['CreateSiteCommand'][$prop])) {
                    $this->config->$prop = $iniConf['CreateSiteCommand'][$prop];
                }
            }
            $this->config->server = $this->getInput()->getArgument('server');
            $this->config->client = $this->getInput()->getArgument('client');
            $this->config->domain = $this->getInput()->getArgument('domain');
            $this->config->dbname = $this->getInput()->getArgument('dbname');
            $this->config->dbuser = $this->getInput()->getArgument('dbuser');
            if ($this->getInput()->getOption('genpass')) {
                $this->config->dbpass = $this->generatePassword();
            } else {
                $this->config->dbpass = $this->getInput()->getArgument('dbpass');
            }

            $this->config->serverId = $this->getServerId();
            $this->config->clientId = $this->getClientId();
            $this->config->dbUserId = $this->getDatabaseId();

            $this->testParameters($this->config);
        }
        return $this->config;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $config = $this->getConfig();
        $this->createSite($config);
        $this->createDBUser($config);
        $this->createDb($config);
    }

    protected function getServerId() {
        $serverId = $this->config->server;
        $this->info("Checking server " . $serverId . " ", false);
        if (!is_numeric($serverId)) {
            $this->info("... finding id ", false);
            $serverArr = $this->soapClient()->serverGetServeridByName($this->config->server . $this->getConfig()->suffix);
            if (isset($serverArr[0]['server_id'])) {
                $serverId = $serverArr[0]['server_id'];
            } else {
                $serverArr = $this->soapClient()->serverGetServeridByName($this->config->server);
                if (isset($serverArr[0]['server_id'])) {
                    $serverId = $serverArr[0]['server_id'];
                } else {
                    $this->info("Failed");
                    throw new \InvalidArgumentException("Unable to find the server " . $this->config->server . $this->getConfig()->suffix . " (" . $this->config->server . ")");
                }
            }
            $this->info("... found " . $serverId, false);
        } else {
            $serverArr = $this->soapClient()->serverGet($serverId);
            if (isset($serverArr['server']['hostname'])) {
                $this->info("... found " . $serverArr['server']['hostname'] . " ", false);
            } else {
                throw new \InvalidArgumentException("Unable to find the server " . $this->config->server);
            }
        }
        $this->success("OK");

        return $serverId;
    }

    protected function getClientId() {
        $clientId = $this->config->client;
        $this->info("Checking client " . $clientId . " ", false);
        if (!is_numeric($clientId)) {
            $this->info("... finding id ", false);
            $clientArr = $this->soapClient()->clientGetByUsername($this->config->client);
            if (isset($clientArr['client_id'])) {
                $clientId = $clientArr['client_id'];
            } else {
                $this->info("Failed");
                throw new \InvalidArgumentException("Unable to find the the client " . $this->config->client);
            }
            $this->info("... found " . $clientId . " ", false);
        } else {
            $clientArr = $this->soapClient()->clientGet($clientId);
            if (isset($clientArr['client_id'])) {
                $clientId = $clientArr['client_id'];
                $this->info("... found " . $clientId . " ", false);
            } else {
                $this->error("Failed");
                throw new \InvalidArgumentException("Unable to find the client " . $this->config->client);
            }
        }
        $this->success("OK");
        return $clientId;
    }

    protected function getDatabaseId() {
        $dbId = null;
        if (!$this->config->dbpass) {
            $this->info("Checking databse user " . $this->config->dbuser . " ... finding id ", false);
            $dbUserArr = $this->soapClient()->sitesDatabaseUserGet(array('database_user' => $this->config->dbuser));
            if (isset($dbUserArr[0]["database_user_id"])) {
                $dbId = $dbUserArr[0]["database_user_id"];
                $this->info("... found " . $dbId . " ", false);
            } else {
                $this->error("Failed");
                throw new \InvalidArgumentException("No DB Password defined, and unable to find DB user " . $this->config->dbuser . ". Include dbpass to create database " . $this->config->dbuser);
            }
            $this->success("OK");
        }
        return $dbId;
    }

    protected function buildSitesWebDomainAddArgs($serverConf) {
        $sitesWebDomainAddArgs = array();
        if ($serverConf && isset($serverConf['sitesWebDomainAdd'])) {
            $sitesWebDomainAddConfig = $serverConf['sitesWebDomainAdd'];
            $soapClientRelection     = new \ReflectionClass($this->soapClient());
            foreach ($soapClientRelection->getMethod('sitesWebDomainAdd')->getParameters() as $parameter) {
                $parmName = $parameter->getName();
                if ($parmName == "domain") {
                    $sitesWebDomainAddArgs[] = $this->getInput()->getArgument('domain');
                } else if ($parmName == "serverId") {
                    $sitesWebDomainAddArgs[] = $this->config->serverId;
                } else if ($parmName == "clientId") {
                    $sitesWebDomainAddArgs[] = $this->config->clientId;
                } else if (isset($sitesWebDomainAddConfig[$parmName])) {
                    $sitesWebDomainAddArgs[] = $sitesWebDomainAddConfig[$parmName];
                } else {
                    $sitesWebDomainAddArgs[] = "";
                }
            }
        }
        return $sitesWebDomainAddArgs;
    }

    protected function testParameters(CreateSiteCommandConifg $config) {
        $this->info("Checking domain " . $config->domain . " ", false);
        $domainResult = $this->soapClient()->sitesWebDomainGet(array("domain" => $config->domain));
        if ($domainResult != false) {
            $this->error("Failed");
            throw new \InvalidArgumentException("The domain " . $config->domain . " already exists ");
        }
        $this->success("OK");

        $this->info("Checking database " . $config->dbname . " ", false);
        $dbnameResult = $this->soapClient()->sitesDatabaseGet(array("database_name" => $config->dbname));
        if ($dbnameResult != false) {
            $this->error("Failed");
            throw new \InvalidArgumentException("The database " . $config->dbname . " already exists ");
        }
        $this->success("OK");

        $this->info("Checking database username " . $config->dbuser . " ", false);
        if (strlen($config->dbuser) > 16) {
            throw new \InvalidArgumentException("The database user name must be 16 characters or less");
        }
        $this->success("OK");
        
        if ($this->config->dbpass) {
            $this->info("Checking database password ", false);
            $errors = array();
            if (strlen($this->config->dbpass) < 8) {
                $errors[] = " * be at least 8 characters long";
            }
            if (!preg_match('/[ABCDEFGHIJKLNMOPQRSTUVWXYZ]/', $this->config->dbpass)) {
                $errors[] = " * contain at least one UPPER CASE character";
            }
            if (!preg_match('/[abcdefghijklmnopqrstuvwxyz]/', $this->config->dbpass)) {
                $errors[] = " * contain at least one lower case character";
            }
            if (!preg_match('/[0123456789]/', $this->config->dbpass)) {
                $errors[] = " * contain at least one number";
            }
            if (!preg_match('/[' . preg_quote($this->specialChars) . ']/', $this->config->dbpass)) {
                $errors[] = " * contain at least one of " . $this->specialChars;
            }
            if (count($errors)) {
                throw new \InvalidArgumentException("The database password (" . $this->config->dbpass . ") must" . PHP_EOL . implode(PHP_EOL, $errors));
            }
            if ($this->getInput()->getOption('genpass')) {
                $this->info("( Generated " . $this->config->dbpass . " ) ", false);
            }
            $this->success("OK");
        }
    }

    protected function createSite(CreateSiteCommandConifg $config) {
        $this->info("Creating domain " . $this->getInput()->getArgument('domain') . " ", false);
        $sitesWebDomainAddArgs = $this->buildSitesWebDomainAddArgs(isset($config->servers[$config->serverId]) ? $config->servers[$config->serverId] : null);
        $config->siteId        = call_user_func_array(array($this->soapClient(), "sitesWebDomainAdd"), $sitesWebDomainAddArgs);
        if ($config->siteId == false) {
            $this->error("Failed");
            throw $this->soapClient()->getLastException();
        }
        if (isset($config->servers[$config->serverId]['sitesWebDomainAdd']['appendwebroot'])) {
            $domainResult = $this->soapClient()->sitesWebDomainGet($config->siteId);
            if ($domainResult) {
                $domainResult["apache_directives"] = 'DocumentRoot "' . $domainResult["document_root"] . '/' . $config->servers[$config->serverId]['sitesWebDomainAdd']['appendwebroot'] . '"';
                $res                               = $this->soapClient()->sitesWebDomainUpdate($config->clientId, $domainResult["domain_id"], $domainResult);
                if ($res === false) {
                    $this->error("Failed");
                    throw $this->soapClient()->getLastException();
                }
            }
        }
        $this->success("Completed " . $config->siteId);
    }

    protected function createDBUser(CreateSiteCommandConifg $config) {
        if (!$config->dbUserId) {
            $this->info("Creating database user " . $config->dbuser . " ", false);
            $config->dbUserId = $this->soapClient()->sitesDatabaseUserAdd($config->clientId, $config->serverId, $config->dbuser, $config->dbpass);
            if ($config->dbUserId == false) {
                $this->error("Failed");
                throw $this->soapClient()->getLastException();
            }
            $this->success("Completed " . $config->dbUserId);
        }
    }

    protected function createDb(CreateSiteCommandConifg $config) {
        $this->info("Creating database " . $config->dbname . " ", false);
        $config->dbId = $this->soapClient()->sitesDatabaseAdd($config->clientId, $config->serverId, $config->siteId, $config->dbname, $config->dbUserId);
        if ($config->dbId == false) {
            $this->error("Failed");
            throw $this->soapClient()->getLastException();
        }
        $this->success("Completed " . $config->dbId);
    }

    /**
     * @see https://www.dougv.com/2010/03/23/a-strong-password-generator-written-in-php/
     * @param type $l
     * @param type $c
     * @param type $n
     * @param type $s
     * @return boolean
     */
    function generatePassword($l = 8, $c = 2, $n = 2, $s = 2) {
        // get count of all required minimum special chars
        $count = $c + $n + $s;
        $out   = "";
        // sanitize inputs; should be self-explanatory
        if (!is_int($l) || !is_int($c) || !is_int($n) || !is_int($s)) {
            trigger_error('Argument(s) not an integer', E_USER_WARNING);
            $out = false;
        } elseif ($l < 0 || $l > 20 || $c < 0 || $n < 0 || $s < 0) {
            trigger_error('Argument(s) out of range', E_USER_WARNING);
            $out = false;
        } elseif ($c > $l) {
            trigger_error('Number of password capitals required exceeds password length', E_USER_WARNING);
            $out = false;
        } elseif ($n > $l) {
            trigger_error('Number of password numerals exceeds password length', E_USER_WARNING);
            $out = false;
        } elseif ($s > $l) {
            trigger_error('Number of password capitals exceeds password length', E_USER_WARNING);
            $out = false;
        } elseif ($count > $l) {
            trigger_error('Number of password special characters exceeds specified password length', E_USER_WARNING);
            $out = false;
        }
        if ($out !== false) {

            // all inputs clean, proceed to build password
            // change these strings if you want to include or exclude possible password characters
            $chars = "abcdefghijklmnopqrstuvwxyz";
            $caps  = strtoupper($chars);
            $nums  = "0123456789";
            $syms  = $this->specialChars;

            // build the base password of all lower-case letters
            for ($i = 0; $i < $l; $i++) {
                $out .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
            }

            // create arrays if special character(s) required
            if ($count) {
                // split base password to array; create special chars array
                $tmp1 = str_split($out);
                $tmp2 = array();

                // add required special character(s) to second array
                for ($i = 0; $i < $c; $i++) {
                    array_push($tmp2, substr($caps, mt_rand(0, strlen($caps) - 1), 1));
                }
                for ($i = 0; $i < $n; $i++) {
                    array_push($tmp2, substr($nums, mt_rand(0, strlen($nums) - 1), 1));
                }
                for ($i = 0; $i < $s; $i++) {
                    array_push($tmp2, substr($syms, mt_rand(0, strlen($syms) - 1), 1));
                }

                // hack off a chunk of the base password array that's as big as the special chars array
                $tmp1 = array_slice($tmp1, 0, $l - $count);
                // merge special character(s) array with base password array
                $tmp1 = array_merge($tmp1, $tmp2);
                // mix the characters up
                shuffle($tmp1);
                // convert to string for output
                $out  = implode('', $tmp1);
            }
        }
        return $out;
    }

}
