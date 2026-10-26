<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class NodeTopic extends Pivot
{
    use HasUuids;

    protected $table = 'node_topic';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['is_link' => 'boolean'];
    }
}
