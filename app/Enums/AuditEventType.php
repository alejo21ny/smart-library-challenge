<?php

namespace App\Enums;

enum AuditEventType: string
{
    case BookCreated = 'BOOK_CREATED';
    case BookUpdated = 'BOOK_UPDATED';
    case BookDeleted = 'BOOK_DELETED';
    case BookBorrowed = 'BOOK_BORROWED';
    case BookReturned = 'BOOK_RETURNED';
    case UserRoleChanged = 'USER_ROLE_CHANGED';
}
