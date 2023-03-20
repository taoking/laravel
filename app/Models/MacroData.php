<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MacroData extends Model
{
    use HasFactory;

    protected $connection = 'doris';

    protected $table = 'macro_index_data2';
}
