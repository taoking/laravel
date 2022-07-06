<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Monolog\Logger;
use Monolog\Handler\StdoutHandler;

class TestController extends Controller
{
    //


    function use(){
        $a =     function(){
            return $this->dfsd;
        };

        $a->bind(name::class);
    }

    public function kafka(){
        date_default_timezone_set('PRC');

// Create the logger
        $logger = new Logger('my_logger');
// Now add some handlers
        $logger->pushHandler(new StdoutHandler());

        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(10000);
        $config->setMetadataBrokerList('localhost:9092');
        $config->setBrokerVersion('1.0.0');
        $config->setRequiredAck(1);
        $config->setIsAsyn(false);
        $config->setProduceInterval(500);
        $producer = new \Kafka\Producer(
            function() {
                return [
                    [
                        'topic' => 'test',
                        'value' => 'test....message.',
                        'key' => 'testkey',
                    ],
                ];
            }
        );
        $producer->setLogger($logger);
        $producer->success(function($result) {
            var_dump($result);
        });
        $producer->error(function($errorCode) {
            var_dump($errorCode);
        });
        $producer->send(true);
    }

}

class name{
  public   $dfsd = 'xxxx';
}
