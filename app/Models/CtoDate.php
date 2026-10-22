<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $cto_request_id @property \Illuminate\Support\Carbon $cto_date @property string $portion */
class CtoDate extends Model
{
    public $timestamps = false;

    protected $fillable = ['cto_request_id', 'cto_date', 'portion', 'minutes'];

    protected function casts(): array
    {
        return ['cto_date' => 'date:Y-m-d', 'minutes' => 'integer'];
    }

    /** @return BelongsTo<CtoRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(CtoRequest::class, 'cto_request_id');
    }
}
