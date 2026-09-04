<?php

namespace App\Events;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserRoleChanged
{
    use Dispatchable;

    public function __construct(
        public User $user,
        public UserRole $oldRole,
        public UserRole $newRole,
        public ?User $actor,
    ) {}
}
