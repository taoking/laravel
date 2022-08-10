<?php
function info(&$arr, $left, $right)
{
    if ($left == $right){
        return ;
    }
    $middle = floor(($left+$right)/2);

    info($arr, $left, $middle);
    info($arr,$middle+1, $right);
    merge($arr, $left, $middle, $right);
}
function merge(&$arr, $left, $middle, $right){
    $tmp = [];
    $p1 = $left;
    $p2 = $middle+1;

    while ($p1 <=$middle && $p2<=$right){
        $tmp[] = $arr[$p1] < $arr[$p2]? $arr[$p1++]: $arr[$p2++];
    }
    while ($p1 <=$middle){
        $tmp[] = $arr[$p1++];
    }
    while ($p2<=$right){
        $tmp[] = $arr[$p2++];
    }
    for ($i = 0; $i<=count($tmp)-1;$i++){
        $arr[$left+$i] = $tmp[$i];
    }
}

$arr = [];

for ($i = 0; $i<30; $i++){
    $arr[] = random_int(1,100);
}
print_r($arr);
info($arr,0,count($arr)-1);

print_r($arr);
