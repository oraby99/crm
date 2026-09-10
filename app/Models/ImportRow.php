<?php

namespace App\Models;

use App\Enums\ImportRowStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'row_number',
        'raw_data',
        'status',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'status' => ImportRowStatus::class,
            'row_number' => 'integer',
        ];
    }

    /**
     * The parent import batch.
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
