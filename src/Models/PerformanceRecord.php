<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'method',
        'uri',
        'controller',
        'action',
        'query_count',
        'slow_query_count',
        'duration_ms',
        'memory_mb',
        'grade',
        'has_n_plus_one',
        'has_slow_queries',
        'status_code',
        'user_id',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'uuid' => 'string',
        'query_count' => 'integer',
        'slow_query_count' => 'integer',
        'duration_ms' => 'float',
        'memory_mb' => 'float',
        'has_n_plus_one' => 'boolean',
        'has_slow_queries' => 'boolean',
        'status_code' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('performance-guard.storage.connection') ?? parent::getConnectionName();
    }

    public function queries(): HasMany
    {
        return $this->hasMany(PerformanceQuery::class);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subDay());
    }

    public function scopeWithNPlusOne(Builder $query): Builder
    {
        return $query->where('has_n_plus_one', true);
    }

    public function scopeSlow(Builder $query): Builder
    {
        return $query->where('has_slow_queries', true);
    }

    public function scopeGrade(Builder $query, string $grade): Builder
    {
        return $query->where('grade', strtoupper($grade));
    }

    public function scopeFailing(Builder $query): Builder
    {
        return $query->whereIn('grade', ['D', 'F']);
    }
}
