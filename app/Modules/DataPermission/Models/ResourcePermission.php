<?php

namespace App\Modules\DataPermission\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResourcePermission extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'resource_type',
        'resource_id',
        'subject_type',
        'subject_id',
        'permission_type',
    ];
}
