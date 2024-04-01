<?php

namespace App\Models;

use App\Models\Entity;
use App\Models\Underwriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory;
    protected $table = 'plans';
    protected $connection = 'mysql';

    /**
     * Get the entity that owns the Plan
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id', 'id');
    }

    /**
     * Get the underwriter that owns the Plan
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function underwriter(): BelongsTo
    {
        return $this->belongsTo(Underwriter::class, 'underwriter_id', 'id');
    }
}
