<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    //


    function use(): void{
        $a =     function(){
            return $this->dfsd;
        };

        $a->bind(name::class);
    }
}
class name{
  public   $dfsd = 'xxxx';
}
