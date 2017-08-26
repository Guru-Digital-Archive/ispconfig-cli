<?php

namespace GDM\ISPConfigCli;

use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

class ServerGetServeridByNameCommand extends Command
{

    /**
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \GDM\ISPConfig\UsernameStatus[] $cmdOutput
     * @return int
     */
    protected function onSuccess(InputInterface $input, OutputInterface $output, $cmdOutput)
    {
        $res = 0;
        if (count($cmdOutput)) {
            foreach ($cmdOutput as $server) {
                if (isset($server['server_id'])) {
                    $this->info($server['server_id']);
                }
            }
        } else {
            $this->error('Not found');
            $res = 1;
        }
        return $res;
    }

//    protected function onFailure(InputInterface $input, OutputInterface $output, $cmdOutput) {
//        $this->printLastError();
//        return 1;
//    }
}
