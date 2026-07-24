<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An FCM registration token for a single device.
 * A user may have many tokens (one per logged-in device).
 */
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'device_name',
        'platform',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
