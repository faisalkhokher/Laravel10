<?php

namespace App\Models;

use App\Models\Plan;
use App\Models\PolicyHolder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Policy extends Model
{
    use HasFactory;
    protected $table = 'policies';
    protected $connection = 'mysql';

    /**
     * Get the plan that owns the Policy
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    /**
     * Get the policyHolder that owns the Policy
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function policyHolder()
    {
        return $this->belongsTo(PolicyHolder::class, 'policy_holder_id', 'id');
    }
}
