<?php


namespace App\Http\Controllers;


use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class OfficeController extends Controller
{

    public function read(){
//        $word = new PhpWord();

//        optional()
        $write = IOFactory::load('222.doc');

        $xmlWrite = IOFactory::createWriter($write,'HTML');
//        echo $xmlWrite->save("php://output");//输出html文件到页面`
        $xmlWrite->save('test.html');
    }
}
