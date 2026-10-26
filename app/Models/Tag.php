<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['topic_id', 'value'])]
class Tag extends Model
{
    use HasUuids;

    public $timestamps = false;

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
