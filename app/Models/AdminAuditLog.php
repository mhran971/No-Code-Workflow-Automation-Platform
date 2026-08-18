<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;

class AdminAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_email',
        'action',
        'target_type',
        'target_id',
        'description',
        'ip_address',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an audit log entry.
     */
    public static function record(string $action, string $description, ?Model $target = null, array $properties = []): self
    {
        $actor = auth()->user() ?? auth('api')->user();

        return self::create([
            'user_id' => $actor?->id,
            'user_email' => $actor?->email,
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target?->getKey(),
            'description' => $description,
            'ip_address' => request()?->ip(),
            'properties' => $properties,
        ]);
    }
}
