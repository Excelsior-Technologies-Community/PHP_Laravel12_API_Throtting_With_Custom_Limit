<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThrottleViolation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ip_address',
        'method',
        'endpoint',
        'route_name',
        'limiter',
        'retry_after',
        'user_agent',
    ];

    protected $casts = [
        'retry_after' => 'integer',
    ];

    /**
     * Get the user who triggered the violation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}