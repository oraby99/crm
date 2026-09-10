<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerActivityPolicy
{
    use HandlesAuthorization;

    /**
     * Any active user who can view the customer can view its activities.
     */
    public function viewAny(User $user, Customer $customer): bool
    {
        return $user->can('view', $customer);
    }

    /**
     * Any active user who can view the customer can view individual activities.
     */
    public function view(User $user, CustomerActivity $activity): bool
    {
        return $user->can('view', $activity->customer);
    }

    /**
     * Any active user who can update the customer can create activities.
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Only the creator or admin can update their own activities.
     */
    public function update(User $user, CustomerActivity $activity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $activity->user_id === $user->id;
    }

    /**
     * Only admin and team leaders can delete activities.
     */
    public function delete(User $user, CustomerActivity $activity): bool
    {
        return $user->isAdmin() || $user->isTeamLeader();
    }
}
