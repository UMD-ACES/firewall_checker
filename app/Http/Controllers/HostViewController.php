<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class HostViewController extends Controller
{
    public function index()
    {
        return view('hosts');
    }

    public function view($groupId)
    {
        $groupHost = \DB::table('syshealth')
            ->select(['inserted_on', 'firewall', 'transferInMB', 'transferOutMB', 'storageMB', 'memoryMB', 'cpuLoad'])
            ->where('alive', '=', 1)
            ->where('group_id', '=', $groupId)
            ->get();

        $data = array(array('date', 'firewall', 'transferInMB', 'transferOutMB', 'storageMB', 'memoryMB', 'cpuLoad'));
        $i = 1;

        foreach ($groupHost as $host)
        {
            $data[$i][] = $host->inserted_on;
            $data[$i][] = $host->firewall;
            $data[$i][] = $host->transferInMB;
            $data[$i][] = $host->transferOutMB;
            $data[$i][] = $host->storageMB;
            $data[$i][] = $host->memoryMB;
            $data[$i][] = $host->cpuLoad;
            $i++;
        }

        $out = fopen('php://output', 'w');
        foreach ($data as $item) {
            fputcsv($out, $item);
        }
        fclose($out);


    }
}
