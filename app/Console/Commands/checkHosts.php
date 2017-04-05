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

        $hosts = $this->trafficTransfer($hosts);

        $hosts = $this->storageAvailable($hosts);

        $hosts = $this->memoryAvailable($hosts);

        $hosts = $this->upTime($hosts);

        $hosts = $this->honeypotAlive($hosts);

        $hosts->map(function($host, $key)
        {
            \DB::table('syshealth')
                ->insert([
                    'group_id'      => $host->id,
                    'alive'         => $host->alive,
                    'alive_error'   => $host->alive_error,
                    'firewall'      => $host->firewall,
                    'transferInMB'  => $host->transferIn,
                    'transferOutMB' => $host->transferOut,
                    'storageMB'     => $host->storage,
                    'memoryMB'      => $host->memory,
                    'cpuLoad'       => $host->cpu,
                    'upTime'        => $host->upTime,
                    'honeypot_1'    => $host->honeypot_1,
                    'honeypot_2'    => $host->honeypot_2,
                    'honeypot_3'    => $host->honeypot_3,
                    'honeypot_4'    => $host->honeypot_4,
                    'inserted_on'   => date("Y-m-d H:i:s")]);
        });

        return 0;
    }

    /**
     * Determines if the host is alive
     *
     * @param Collection $hosts
     * @return Collection $hosts
     */
    private function checkAlive(Collection $hosts)
    {
        $hosts->map(function($item, $key) {
            $alive = true;
            $i = 0;
            $return = 1;
            $output = array();

            while($i < 4 && $return != 0)
            {
                exec('timeout 10s ssh -i /var/www/id_rsa root@' . $item->ip . ' "date" 2>&1', $output, $return);
                $i++;
            }

            //Was unable to connect to system
            if($return != 0)
            {
                $item->alive_error = implode(PHP_EOL, $output);
                $this->checkInfraction($item->id, 'alive', 1, $output, $return);
                $alive = false;
            }
            else
            {
                $item->alive_error = '';
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
                $this->checkInfraction($item->id, 'firewall', 1, $output, $return);
                $firewall = false;
            }

            $item->firewall = $firewall;

            return $item;
        });

        return $hosts;
    }

    /**
     * @param Collection $hosts
     * @return Collection $hosts
     */
    private function trafficTransfer(Collection $hosts)
    {

        $hosts->map(function($item, $key) {

            if(!$item->alive)
            {
                $item->transferIn = 0;
                $item->transferOut = 0;
                return $item;
            }

            exec('ssh -i /var/www/id_rsa root@'.$item->ip.' "ifconfig eth1 | grep \'RX bytes\'"', $output, $return);

            $line = preg_replace('!\s+!', ' ', $output[0]);
            $linePieces = explode(' ', $line);
            $transferIn = str_replace('bytes:', '', $linePieces[2]);
            $transferIn = floor($transferIn / (1024 * 1024));
            $transferOut = str_replace('bytes:', '', $linePieces[6]);
            $transferOut = floor($transferOut / (1024 * 1024));

            $item->transferIn = $transferIn;
            $item->transferOut = $transferOut;

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

            exec('ssh -i /var/www/id_rsa root@'.$item->ip.' "df -P -m"', $output, $return);

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

            if(($storage < 128)) // less than 128MB
            {
                $previousStorageReport = \DB::table('syshealth')
                    ->select('storageMB')
                    ->where('alive', '=', 1)
                    ->where('group_id', '=', $item->id)
                    ->latest('inserted_on')
                    ->first();

                if($previousStorageReport->storageMB > 128)
                {
                    mail(getenv('MAIL_TO'), 'HACS102',
                    'Low Disk Space from '.$item->name.'
                    Output: '.implode("\n", $output).'
                    Return code: '.$return);
                }

                $this->checkInfraction($item->id, 'storageMB', 128, $output, $return);
            }

            $item->storage = $storage;

            return $item;
        });

        return $hosts;
    }

    /**
     * @param Collection $hosts
     * @return Collection $hosts
     */
    private function memoryAvailable(Collection $hosts)
    {
        $hosts->map(function($item, $key) {

            if(!$item->alive)
            {
                $item->memory = 0;
                return $item;
            }

            exec('ssh -i /var/www/id_rsa root@'.$item->ip.' "free -m"', $output, $return);

            $memory = 0;

            for($i = 0; $i < count($output); $i++)
            {
                if(strpos($output[$i], 'Mem:') !== FALSE)
                {
                    $line = preg_replace('!\s+!', ' ', $output[$i]);
                    $linePieces = explode(' ', $line);
                    $memory = $linePieces[3] + $linePieces[5] + $linePieces[6];
                }
            }

            if($memory < 512) // less than 512MB
            {
                $previousMemoryReport = \DB::table('syshealth')
                    ->select('memoryMB')
                    ->where('alive', '=', 1)
                    ->where('group_id', '=', $item->id)
                    ->latest('inserted_on')
                    ->first();

                if($previousMemoryReport->memoryMB > 512)
                {
                    mail(getenv('MAIL_TO'), 'HACS102',
                        'Low RAM Available from '.$item->name.'
                    Output: '.implode("\n", $output).'
                    Return code: '.$return);
                }

                $this->checkInfraction($item->id, 'memoryMB', 51200, $output, $return);
            }

            $item->memory = $memory;

            return $item;
        });

        return $hosts;
    }

    /**
     * @param Collection $hosts
     * @return Collection $hosts
     */
    private function upTime(Collection $hosts)
    {
        $hosts->map(function($item, $key) {

            if(!$item->alive)
            {
                $item->cpu = 0;
                $item->upTime = 'Down/No route';
                return $item;
            }

            exec('ssh -i /var/www/id_rsa root@'.$item->ip.' "uptime | grep -ohe \'load average[s:][: ].*\'"', $load, $return);
            exec('ssh -i /var/www/id_rsa root@'.$item->ip.' "uptime -p"', $upTime, $return);

            $line = preg_replace('!\s+!', ' ', $load[0]);
            $linePieces = explode(' ', $line);
            $cpu = str_replace(',', '', $linePieces[3]);

            $item->cpu = $cpu;
            $item->upTime = $upTime[0];

            return $item;
        });

        return $hosts;
    }

    /**
     * @param Collection $hosts
     * @return Collection $hosts
     */
    private function honeypotAlive(Collection $hosts)
    {
        $hosts->map(function($item, $key) {

            $honeypots = \DB::table('honeypots')
                ->select(['ip'])
                ->where('group_id', '=', $item->id)
                ->get();

            echo $item->id.PHP_EOL;

            for($honeypotNo = 0; $honeypotNo < $honeypots->count(); $honeypotNo++)
            {
                $name = 'honeypot_'.($honeypotNo + 1);
		        $item->$name = 0;

                exec('timeout 3s ping -c 2 '.$honeypots->get($honeypotNo)->ip, $output, $return);


                for($i = 0; $i < count($output); $i++)
                {
                    if(strpos($output[$i], '2 received') !== FALSE)
                    {
                        $item->$name = 1;
                    }
                }

                if($item->$name == 0 || $item->id == 14)
                {
                    $this->checkInfraction($item->id, $name, 1, $output, $return);
                }
            }

            return $item;
        });

        return $hosts;
    }


    private function checkInfraction($group_id, $type, $threshold, $output, $return)
    {
        $previousReport = \DB::table('syshealth')
            ->select('inserted_on', $type)
            ->where('group_id', '=', $group_id)
            ->where('alive', '=', '1')
            ->latest('inserted_on')
            ->first();

        $infType = \DB::table('infractions_type')
            ->select(['id', 'grace_seconds'])
            ->where('name' , '=', $type)
            ->first();

        $lastInfraction = \DB::table('infractions')
            ->select('created_at', 'period')
            ->where('type', '=', $infType->id)
            ->where('group_id', '=', $group_id)
            ->latest('created_at')
            ->first();

        $dbDate = strtotime($previousReport->inserted_on);
        $now = time();


        //greater since threshold accounts for numbers 0...threshold (threshold...infinity are fine values)
        if($previousReport->$type > $threshold)
        {
            return;
        }

        //Determine if it is an infraction
        if(($now - $dbDate) >= $infType->grace_seconds && (!isset($lastInfraction) ||
            ($now - ($now - $dbDate)) != (strtotime($lastInfraction->created_at) - $lastInfraction->period)))
        {
            echo 'Infraction';
            \DB::table('infractions')
                ->insert(['group_id' => $group_id,
                    'inserted_by' => 'automatic',
                    'type'        => $infType->id,
                    'period'      => ($now - $dbDate),
                    'created_at'  => date("Y-m-d H:i:s")]);

            mail(getenv('MAIL_TO'), 'HACS102',
                'Infraction '.$type.' from '.$group_id.'
                Output: '.implode("\n", $output).'
                Return code: '.$return);
        }
    }
}