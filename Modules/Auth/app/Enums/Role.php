<?php

namespace Modules\Auth\Enums;

enum Role: string
{
    case BusinessOwner = 'business_owner';
    case Admin = 'admin';
}
