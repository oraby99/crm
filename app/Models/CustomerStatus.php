<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerStatus extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'color',
        'sort_order',
        'is_active',
        'is_final',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_final' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Customers currently in this status.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'status_id');
    }

    /**
     * Activities that set this as the new status.
     */
    public function activitiesAsNewStatus(): HasMany
    {
        return $this->hasMany(CustomerActivity::class, 'new_status_id');
    }

    /**
     * Activities that had this as the old status.
     */
    public function activitiesAsOldStatus(): HasMany
    {
        return $this->hasMany(CustomerActivity::class, 'old_status_id');
    }

    /**
     * Scope to only active statuses, ordered by sort_order.
     *
     * @param  Builder<CustomerStatus>  $query
     * @return Builder<CustomerStatus>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
