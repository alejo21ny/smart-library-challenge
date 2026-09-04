<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Librarian = 'librarian';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Librarian => 'Librarian',
            self::Member => 'Member',
        };
    }

    /** Roles that can manage the catalog and circulation. */
    public function isStaff(): bool
    {
        return $this === self::Admin || $this === self::Librarian;
    }
}
