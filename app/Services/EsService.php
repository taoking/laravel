<?php


namespace App\Services;


use App\Models\OrgBase;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Config;

class EsService
{
    public $client;

    public function __construct()
    {
        $this->init();
    }

    public  function init(): void
    {
//        $info = explode(",","基础设施投资,高技术投资");
//        print_r($info);exit;
//        print_r([Config::get('app.es_host')]);
//        $client = ClientBuilder::create()->setHosts(['10.220.30.80:9200'])->build();
//        echo $client->ping();
        $this->client = ClientBuilder::create()
            ->setHosts(Config::get('app.es_host'))
            ->build();
        echo  $this->client->ping();
// Info API
        $response =  $this->client->info();


        echo $response['version']['number']; // 8.0.0
    }

    public function initOrg(): void
    {
//        $info = OrgBase::first();
echo date('Y-m-d H:i:s');
        $begin = 0;
        while (true){
            $list = OrgBase::where('rid','>', $begin)->limit(1000)->orderBy('rid')->get()->toArray();
//            print_r($list);

            if (empty($list)) {
                echo 'success end';
                echo date('Y-m-d H:i:s');
                break;
            }
            $length = count($list) - 1;
            echo $list[$length]['rid'];
//            exit;

            $param = [];
            foreach ($list as $value){
                $param['body'][] = [
                    'index' => [
                        '_index' => 'org_info',
                        '_id'    => $value['rid']
                    ]
                ];
                $param['body'][] = $value;
            }

            $this->client->bulk($param);
            $length = count($list) - 1;
            $begin  = $list[$length]['rid'];
        }
//        $param = [
//            'index' => 'org_info',
//            'body'  => $info->toArray(),
//        ];
//        try {
//            $response= $this->client->index($param);
//        } catch (Exception $e) {
//            echo $e->getMessage();
//        }

//        print_r($response);
    }
}
