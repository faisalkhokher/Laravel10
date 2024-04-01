<?php

namespace App\Models;

use App\Models\RecurringOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];

    /**
     * Get the policy that owns the Transaction
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function policy()
    {
        return $this->belongsTo(Policy::class, 'policy_id', 'id');
    }

    /**
     * Get the entity that owns the Transaction
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id', 'id');
    }

    /**
     * Get the recurringOrder that owns the Transaction
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recurringOrder()
    {
        return $this->belongsTo(RecurringOrder::class, 'recurring_order_id', 'id');
    }
}
