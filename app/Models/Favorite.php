<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Favorite extends Pivot
{
    use HasUuids;

    protected $table = 'favorites';

    public $incrementing = false;

    protected $keyType = 'string';
}
