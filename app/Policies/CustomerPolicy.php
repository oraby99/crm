<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    /**
     * Admin and team leaders can view any customer (within their scope).
     * CustomerScope handles row-level filtering.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * A user can view a customer only if it is within their scope.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $this->customerWithinScope($user, $customer);
    }

    /**
     * Admin and team leaders can create customers.
     * Sales employees can also create customers (assigned to themselves).
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * A user can update a customer only if it is within their scope.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $this->customerWithinScope($user, $customer);
    }

    /**
     * Only admin and team leaders can delete customers.
     */
    public function delete(User $user, Customer $customer): bool
    {
        if (! $this->customerWithinScope($user, $customer)) {
            return false;
        }

        return $user->isAdmin() || $user->isTeamLeader();
    }

    /**
     * Only admin and team leaders can restore soft-deleted customers.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return $user->isAdmin() || $user->isTeamLeader();
    }

    /**
     * Only admin can force-delete customers.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admin and team leaders can reassign customers.
     */
    public function reassign(User $user, Customer $customer): bool
    {
        if (! $this->customerWithinScope($user, $customer)) {
            return false;
        }

        return $user->isAdmin() || $user->isTeamLeader();
    }

    /**
     * Only admin and team leaders can import customers.
     */
    public function import(User $user): bool
    {
        return $user->isAdmin() || $user->isTeamLeader();
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Check if the customer falls within the authenticated user's scope.
     * This mirrors CustomerScope logic for individual record checks.
     */
    private function customerWithinScope(User $user, Customer $customer): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::TeamLeader => $customer->team_leader_id === $user->id,
            UserRole::Sales => $customer->sales_id === $user->id,
        };
    }
}
