<?php

namespace GDM\ISPConfigCli;

use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

class UserNameExitsCommand extends Command {

    /**
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \GDM\ISPConfig\UsernameStatus[] $cmdOutput
     * @return int
     */
    protected function onSuccess(InputInterface $input, OutputInterface $output, $cmdOutput) {
        $this->tableHeaders = array('Username', 'Exists', 'Error');
        $rows               = array();
        $i                  = 0;
        foreach ($cmdOutput as $status) {
            $i++;
            $rows[] = array($status->username, $status->exists ? "true" : "false", $status->error);
            if ($this->repeatHeaders > 0 && $i % $this->repeatHeaders == 0) {
                $rows[] = new \Symfony\Component\Console\Helper\TableSeparator();
                $rows[] = $this->tableHeaders;
                $rows[] = new \Symfony\Component\Console\Helper\TableSeparator();
            }
        }
        $this->getHelper('table')->
                setLayout(\Symfony\Component\Console\Helper\TableHelper::LAYOUT_BORDERLESS)->
                setHeaders($this->tableHeaders)->
                setRows($rows)->
                render($output);

        return 0;
    }

//    protected function onFailure(InputInterface $input, OutputInterface $output, $cmdOutput) {
//        $this->printLastError();
//        return 1;
//    }
}
