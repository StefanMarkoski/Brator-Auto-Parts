<?php

declare(strict_types=1);

namespace App\Models\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
}
