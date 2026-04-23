<?php

namespace Modules\Auth\Enums;

enum Role: string
{
    case BusinessOwner = 'business_owner';
    case Manager = 'manager';
    case Admin = 'admin';
    case Employee = 'employee';

}
