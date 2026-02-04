<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceVital extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'url',
        'lcp_ms',
        'cls_score',
        'inp_ms',
        'created_at',
    ];

    protected $casts = [
        'lcp_ms' => 'float',
        'cls_score' => 'float',
        'inp_ms' => 'float',
        'created_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('performance-guard.storage.connection') ?? parent::getConnectionName();
    }
}
