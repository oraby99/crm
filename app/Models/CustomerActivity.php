<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerActivity extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'user_id',
        'activity_type',
        'old_status_id',
        'new_status_id',
        'notes',
        'follow_up_date',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => ActivityType::class,
            'follow_up_date' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    /**
     * The customer this activity belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The user who recorded this activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The status before this activity (for status_change type).
     */
    public function oldStatus(): BelongsTo
    {
        return $this->belongsTo(CustomerStatus::class, 'old_status_id');
    }

    /**
     * The status after this activity (for status_change type).
     */
    public function newStatus(): BelongsTo
    {
        return $this->belongsTo(CustomerStatus::class, 'new_status_id');
    }
}
