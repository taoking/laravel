<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PhpEightService
{
    //1  match
    public function matchInfo()
    {
        $res = match (random_int(1,5)){
            1 => 'num 1',
            2 => 'num 2',
            3 => 'num 3',
            default => 'num >= 4',
        };
        echo $res ;
    }

    // 2 get_debug_type
    public function getType()
    {
        echo get_debug_type(str_ends_with('dsafsdf','sdf3'));
    }

    // 3 str_contains
    public function ifContains()
    {
        echo str_contains('abcde','abc');
    }

    // 4 str_start_with
    function ifStart(){
        echo str_starts_with('dfdsadfs','dfd');
        echo str_ends_with('dsafsdf','sdf3');
    }

    // 5  get_resource_id

    // 6  nullsafe  $country = $session?->user?->getAddress()?->country;






    //
    function normal(){
        optional();
        tap();
        Arr::get();
        Arr::dot();
        Str::slug();
    }
}
