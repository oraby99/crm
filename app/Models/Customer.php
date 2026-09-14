<?php

namespace App\Models;

use App\Enums\CustomerType;
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
        'type',
        'platform_id',
        'customer_need_id',
        'details',
        'status_id',
        'sales_id',
        'team_leader_id',
        'created_by',
        'next_follow_up_at',
        'created_at',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
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
        return $this->hasMany(CustomerActivity::class);
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
     * Get a formatted summary string for a specific follow-up by index (1-based index).
     */
    public function getFollowUpSummary(int $index): ?string
    {
        $activities = $this->relationLoaded('activities')
            ? $this->activities->sortBy('created_at')->values()
            : $this->activities()->reorder('id', 'asc')->with('user')->get();

        $activity = $activities->get($index - 1);

        if (! $activity) {
            return null;
        }

        $type = $activity->activity_type ? $activity->activity_type->label() : 'نشاط';
        $date = $activity->created_at ? $activity->created_at->format('d/m/Y H:i') : '';
        $user = $activity->user ? $activity->user->name : '';
        $notes = $activity->notes ?? 'بدون ملاحظات';

        return "{$date} [{$type} - {$user}]: {$notes}";
    }

    /**
     * Get summary of the latest follow-up.
     */
    public function getLatestFollowUpSummary(): ?string
    {
        $activity = $this->relationLoaded('activities')
            ? $this->activities->sortByDesc('created_at')->first()
            : $this->activities()->reorder('id', 'desc')->with('user')->first();

        if (! $activity) {
            return null;
        }

        $type = $activity->activity_type ? $activity->activity_type->label() : 'نشاط';
        $date = $activity->created_at ? $activity->created_at->format('d/m/Y H:i') : '';
        $user = $activity->user ? $activity->user->name : '';
        $notes = $activity->notes ?? 'بدون ملاحظات';

        return "{$date} [{$type} - {$user}]: {$notes}";
    }

    /**
     * Get ONLY the note text for a specific follow-up (1-based index).
     */
    public function getFollowUpNotes(int $index): ?string
    {
        $activities = $this->relationLoaded('activities')
            ? $this->activities->sortBy('created_at')->values()
            : $this->activities()->reorder('id', 'asc')->get();

        $activity = $activities->get($index - 1);

        if (! $activity) {
            return null;
        }

        return $activity->notes ?: 'بدون ملاحظات';
    }

    /**
     * Get ONLY the note text of the latest follow-up.
     */
    public function getLatestFollowUpNotes(): ?string
    {
        $activity = $this->relationLoaded('activities')
            ? $this->activities->sortByDesc('created_at')->first()
            : $this->activities()->reorder('id', 'desc')->first();

        if (! $activity) {
            return null;
        }

        return $activity->notes ?: 'بدون ملاحظات';
    }

    /**
     * Get all follow-ups formatted as a clean numbered list.
     */
    public function getAllFollowUpsSummary(): ?string
    {
        $activities = $this->relationLoaded('activities')
            ? $this->activities->sortBy('created_at')->values()
            : $this->activities()->reorder('id', 'asc')->with('user')->get();

        if ($activities->isEmpty()) {
            return null;
        }

        $summaries = [];
        foreach ($activities as $i => $activity) {
            $num = $i + 1;
            $type = $activity->activity_type ? $activity->activity_type->label() : 'نشاط';
            $date = $activity->created_at ? $activity->created_at->format('d/m/Y H:i') : '';
            $user = $activity->user ? $activity->user->name : '';
            $notes = $activity->notes ?? 'بدون ملاحظات';
            $summaries[] = "#{$num} - {$date} [{$type} - {$user}]: {$notes}";
        }

        return implode("\n", $summaries);
    }

    /**
     * Get a dynamic associative array of all follow-ups (1-based index => formatted string).
     *
     * @return array<int, string>
     */
    public function getFormattedFollowUpsArray(): array
    {
        $activities = $this->relationLoaded('activities')
            ? $this->activities->sortBy('created_at')->values()
            : $this->activities()->reorder('id', 'asc')->with('user')->get();

        $result = [];
        foreach ($activities as $index => $activity) {
            $num = $index + 1;
            $type = $activity->activity_type ? $activity->activity_type->label() : 'نشاط';
            $date = $activity->created_at ? $activity->created_at->format('d/m/Y H:i') : '';
            $user = $activity->user ? $activity->user->name : '';
            $notes = $activity->notes ?? 'بدون ملاحظات';

            $result[$num] = "{$date} [{$type} - {$user}]: {$notes}";
        }

        return $result;
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
