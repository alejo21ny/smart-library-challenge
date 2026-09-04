<?php

namespace App\Exceptions;

use DomainException;

/** Thrown when a borrow is attempted on a book that already has an active loan. */
class BookUnavailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This book is already borrowed.');
    }
}
