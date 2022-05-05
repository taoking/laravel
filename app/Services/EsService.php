<?php


namespace App\Services;


use Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Config;

class EsService
{

    public static function init()
    {
        print_r([Config::get('app.es_host')]);
//        $client = ClientBuilder::create()->setHosts(['10.220.30.80:9200'])->build();
//        echo $client->ping();
        $client = ClientBuilder::create()
            ->setHosts(Config::get('app.es_host'))
            ->build();
        echo $client->ping();
// Info API
        $response = $client->info();


        echo $response['version']['number']; // 8.0.0
    }
}
