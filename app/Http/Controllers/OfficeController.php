<?php


namespace App\Http\Controllers;


use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class OfficeController extends Controller
{

    public function read():void
    {
//        $word = new PhpWord();
//echo '1';exit;
//        optional()
//        $test =IOFactory::createReader();
//        if ($test->canRead('222.doc')){
//            echo '1';
//            $info = $test->load('222.doc');
//            var_dump($info);
////            $xmlWrite = IOFactory::createWriter($info,'HTML');
////            echo $xmlWrite->save("php://output");//输出html文件到页面`
//        }else{
//            echo '0';
//        }
//
//        $conten = file_get_contents('32423.doc');
//        echo $conten;exit;
//        exit;
        header('Content-type:application/msword');
        $content =  file_get_contents('32423.doc');
        $xmlWrite = IOFactory::createWriter($content,'HTML');
        echo $xmlWrite->save("php://output");
        exit;
        $write = IOFactory::load('32423.docx');
//        $write = IOFactory::load('222.doc');
        $xmlWrite = IOFactory::createWriter($write,'HTML');

//        PHPExcel_IOFactory::class
        echo $xmlWrite->save("php://output");//输出html文件到页面`
//        $xmlWrite->save('test.html');
    }
}
