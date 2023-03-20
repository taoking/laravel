<?php

namespace App\Http\Controllers;

use App\Models\MacroData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DorisController extends Controller
{
    //

    public function addData(){
//        $res = DB::connection('doris')->select(" select * from `macro_index_data2` where (`rid` = 7) limit 1 ");
        $res = MacroData::where(['rid'=>7])->first()->toArray();
        $res['rid'] = '11111117';
        $res = MacroData::insert($res);
        print_r($res);
    }
}
