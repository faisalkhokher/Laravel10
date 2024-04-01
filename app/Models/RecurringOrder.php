<?php

namespace App\Models;

use App\Models\RecurringRequest;
use App\Enums\RecurringOrderProgress;
use Illuminate\Database\Eloquent\Model;
use App\Enums\RecurringOrderProgressEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecurringOrder extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'progress' => RecurringOrderProgressEnum::class
    ];

    /**
     * Get the recurring_request that owns the RecurringOrder
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recurringRequest()
    {
        return $this->belongsTo(RecurringRequest::class, 'recurring_request_id', 'id');
    }

}
