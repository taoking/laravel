<?php

namespace App\Http\Controllers;

use App\Models\MacroData;
use App\Models\MacroDataNew;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DorisController extends Controller
{
    //

    public function addData(){
//        $res = DB::connection('doris')->select(" select * from `macro_index_data2` where (`rid` = 7) limit 1 ");
        $id = 1;
        $data = MacroData::where('rid', '>', $id)->orderBy('rid')->limit(5)->get();
        $data = $data->toArray();
        MacroDataNew::insert($data);
        $id  = end($data)['rid'];
        print_r($id);exit;
        $res['rid'] = '11111118';
        $res = MacroData::insert($res);
        print_r($res);
    }
}
