<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uploaded_by',
        'team_leader_id',
        'file_name',
        'file_path',
        'total_rows',
        'successful_rows',
        'failed_rows',
        'status',
        'assigned_sales_id',
        'error_log',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'total_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    /**
     * The user who uploaded this import.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The team leader who initiated this import.
     */
    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    /**
     * Individual rows in this import batch.
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /**
     * Failed rows in this import batch.
     */
    public function failedRows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->where('status', 'failed');
    }
}
