<?php

namespace App\Http\Controllers;

use App\Services\EsService;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Http\Request;

class EsController extends Controller
{

    public function index(EsService $service):void
    {
        $service->init();
        $service->initOrg();
    }
}
