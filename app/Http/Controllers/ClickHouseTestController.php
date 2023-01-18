<?php

namespace App\Http\Controllers;

use Barryvdh\Debugbar\Facades\Debugbar;
use ClickHouseDB\Client;
use Illuminate\Http\Request;

class ClickHouseTestController extends Controller
{
    //
    public function init(): void{
        $config = [
            'host' => '192.168.1.1',
            'port' => '8123',
            'username' => 'default',
            'password' => ''
        ];
        $db = new Client($config);
        $db->database('default');
//        $db->setTimeout(2);      // 1500 ms
        $db->setTimeout(10);       // 10 seconds
        $db->setConnectTimeOut(5); // 5 seconds
    }

    public function test(): void
    {
        Debugbar::error('Error!');
        echo 'test';
    }
}
