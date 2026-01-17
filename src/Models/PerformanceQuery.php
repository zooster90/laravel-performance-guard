<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceQuery extends Model
{
    public $timestamps = false;

    protected $table = 'performance_queries';

    protected $fillable = [
        'performance_record_id',
        'sql',
        'normalized_sql',
        'duration_ms',
        'is_slow',
        'is_duplicate',
        'file',
        'line',
        'created_at',
    ];

    protected $casts = [
        'duration_ms' => 'float',
        'is_slow' => 'boolean',
        'is_duplicate' => 'boolean',
        'line' => 'integer',
        'created_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('performance-guard.storage.connection') ?? parent::getConnectionName();
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(PerformanceRecord::class, 'performance_record_id');
    }
}
