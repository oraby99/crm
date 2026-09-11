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
        'color',
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
     * Scope to only active statuses.
     *
     * @param  Builder<CustomerStatus>  $query
     * @return Builder<CustomerStatus>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('id');
    }
}
