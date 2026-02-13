<?php

namespace Modules\Auth\Repositories;

use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\User;

class UserRepository
{
    /**
     * Create a new user.
     */
    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);

        return User::create($data);
    }
}
