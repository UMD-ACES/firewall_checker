<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class checkHosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hosts:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collects data about each host';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $hosts = \DB::table('groups')
            ->select(['id', 'groups.name', 'groups.ip'])
            ->get();

        $hosts = $this->checkAlive($hosts);

        $hosts = $this->firewallChecker($hosts);

        $hosts = $this->storageAvailable($hosts);

        $hosts->map(function($host, $key)
        {
            \DB::table('syshealth')
                ->insert([
                    'group_id'      => $host->id,
                    'alive'         => $host->alive,
                    'firewall'      => $host->firewall,
                    'storageKB'     => $host->storage,
                    'inserted_on'   => date("Y-m-d H:i:s")]);
        });
    }

    /**
     * Determines if the host is alive
     *
     * @param Collection $hosts
     * @return Collection $hosts
     */
    private function checkAlive($hosts)
    {
        $hosts->map(function($item, $key) {
            exec('ssh -i /var/www/id_rsa root@'.$item->ip.' "date"', $output, $return);

            $alive = true;

            if($return != 0)
            {
                //mail(getenv('MAIL_TO'), 'HACS102',
                //    'No response from '.$item->name.'
                //    Output: '.implode('', $output).'
                //    Return code: '.$return);
                $alive = false;
            }

            $item->alive = $alive;

            return $item;
        });

        return $hosts;
    }

    /**
     * Determines if the firewall provided to students is running
     *
     * @param Collection $hosts
     * @return Collection $hosts
     */
    private function firewallChecker(Collection $hosts)
    {

        $hosts->map(function($item, $key) {

            if(!$item->alive)
            {
                $item->firewall = false;
                return $item;
            }

            exec('ssh -i /var/www/id_rsa root@'.$item->ip.' "iptables -L -n -v"', $output, $return);

            $firewall = true;

            $inputDrop          = false;
            $forwardDrop        = false;
            $synFloodTable      = false;

            for($i = 0; $i < count($output); $i++)
            {
                if(strpos($output[$i], 'INPUT') !== FALSE && strpos($output[$i], 'DROP') !== FALSE)
                {
                    $inputDrop = true;
                }
                if(strpos($output[$i], 'FORWARD') !== FALSE && strpos($output[$i], 'DROP') !== FALSE)
                {
                    $forwardDrop = true;
                }
                if(strpos($output[$i], 'syn_flood') !== FALSE)
                {
                    $synFloodTable = true;
                }
            }

            if(! ($inputDrop && $forwardDrop && $synFloodTable) )
            {
                //mail(getenv('MAIL_TO'), 'HACS102',
                //    'FIREWALL CONFIG from '.$item->name.'
                //    Output: '.implode("\n", $output).'
                 //   Return code: '.$return);
                $firewall = false;
            }

            $item->firewall = $firewall;

            return $item;
        });

        return $hosts;
    }


    private function storageAvailable(Collection $hosts)
    {
        $hosts->map(function($item, $key) {

            if(!$item->alive)
            {
                $item->storage = 0;
                return $item;
            }

            exec('ssh -i /var/www/id_rsa root@'.$item->ip.' "df -P"', $output, $return);

            $storage = 0;

            for($i = 0; $i < count($output); $i++)
            {
                if(strpos($output[$i], '/dev/sda1') !== FALSE)
                {
                    $line = preg_replace('!\s+!', ' ', $output[$i]);
                    $linePieces = explode(' ', $line);
                    $storage = $linePieces[3];
                }
            }

            if(($storage < 1024*1024)) // less than 1GB
            {
                //mail(getenv('MAIL_TO'), 'HACS102',
                //    'Low Disk Usage from '.$item->name.'
                //    Output: '.implode("\n", $output).'
                //    Return code: '.$return);
            }

            $item->storage = $storage;

            return $item;
        });

        return $hosts;
    }
}
