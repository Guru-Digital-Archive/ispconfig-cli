<?php

namespace GDM\ISPConfigCli;

use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

class SitesDatabaseUserGetCommand extends Command {

    /**
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \GDM\ISPConfig\UsernameStatus[] $cmdOutput
     * @return int
     */
    protected function onSuccess(InputInterface $input, OutputInterface $output, $cmdOutput) {
        $res = 0;

        if (count($cmdOutput)) {
            $columns            = array(
                "database_user_id"     => 'DB user Id',
                "sys_userid"           => 'Sys user id',
                "sys_groupid"          => 'Sys group id',
                "sys_perm_user"        => 'Sys perm user',
                "sys_perm_group"       => 'Sys perm group',
                "sys_perm_other"       => 'Sys perm other',
                "server_id"            => 'Server Id',
                "database_user"        => 'User',
                "database_user_prefix" => 'User prefix'
            );
            $this->tableHeaders = array_values($columns);
            $rows               = array();
            $i                  = 0;
            foreach ($cmdOutput as $server) {
                $i++;
                $row = array();
                foreach (array_keys($columns) as $column) {
                    $row[] = isset($server[$column]) ? $server[$column] : "";
                }
                $rows[] = $row;
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
        } else {
            $this->error("Not found");
            $res = 1;
        }
        return $res;
    }

//    protected function onFailure(InputInterface $input, OutputInterface $output, $cmdOutput) {
//        $this->printLastError();
//        return 1;
//    }
}
