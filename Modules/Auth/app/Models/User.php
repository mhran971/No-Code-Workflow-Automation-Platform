<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class User extends Model
{
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];
}
