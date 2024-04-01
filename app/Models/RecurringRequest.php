<?php

namespace App\Models;

use App\Models\Policy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecurringRequest extends Model
{
    use HasFactory;

    protected $table = 'recurring_requests';
    protected $connection = 'mysql';
    protected $guarded = [];

    /**
     * Get the policy that owns the RecurringRequest
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function policy()
    {
        return $this->belongsTo(Policy::class, 'policy_id','id');
    }
}
