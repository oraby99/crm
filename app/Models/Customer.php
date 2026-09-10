<?php

namespace App\Models;

use App\Scopes\CustomerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'whatsapp_phone',
        'platform_id',
        'customer_need_id',
        'details',
        'status_id',
        'sales_id',
        'team_leader_id',
        'created_by',
        'next_follow_up_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_follow_up_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Global Scopes
    // ──────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::addGlobalScope(new CustomerScope);
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    /**
     * Platform/source this customer came from.
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

    /**
     * What product/service the customer needs.
     */
    public function customerNeed(): BelongsTo
    {
        return $this->belongsTo(CustomerNeed::class, 'customer_need_id');
    }

    /**
     * Current status of the customer in the workflow.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(CustomerStatus::class, 'status_id');
    }

    /**
     * The sales employee responsible for this customer.
     */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    /**
     * The team leader overseeing this customer.
     */
    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    /**
     * The user who created this customer record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * All activity / follow-up records for this customer.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CustomerActivity::class)->latest();
    }

    /**
     * The latest activity recorded for this customer.
     */
    public function latestActivity(): HasMany
    {
        return $this->hasMany(CustomerActivity::class)->latestOfMany();
    }

    // ──────────────────────────────────────────────────────────────
    // Business logic helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * A customer is considered "contacted" if at least one activity exists.
     */
    public function isContacted(): bool
    {
        return $this->activities()->exists();
    }

    /**
     * Normalize a phone number to E.164-ish format for WhatsApp links.
     * Strips spaces, dashes, parentheses, and leading zeros.
     */
    public function whatsappLink(): string
    {
        $phone = $this->whatsapp_phone ?? $this->phone;
        $normalized = preg_replace('/[^0-9+]/', '', $phone);

        // If number starts with 0, assume Egyptian number (+20)
        if (str_starts_with($normalized, '0')) {
            $normalized = '+20'.ltrim($normalized, '0');
        }

        // Remove leading +
        $normalized = ltrim($normalized, '+');

        return "https://wa.me/{$normalized}";
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Customers with no activities (not yet contacted).
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeNotContacted($query)
    {
        return $query->whereDoesntHave('activities');
    }

    /**
     * Customers with at least one activity.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeContacted($query)
    {
        return $query->whereHas('activities');
    }

    /**
     * Follow-ups due today.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeFollowUpToday($query)
    {
        return $query->whereDate('next_follow_up_at', today());
    }

    /**
     * Overdue follow-ups (past due and not completed).
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeOverdueFollowUp($query)
    {
        return $query->where('next_follow_up_at', '<', now())
            ->whereDate('next_follow_up_at', '<', today());
    }
}
