<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Admin sees all users. Team leader sees their own sales employees.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTeamLeader();
    }

    /**
     * Admin can view any user. Team leader can only view their sales.
     */
    public function view(User $user, User $model): bool
    {
        return $this->canManageUser($user, $model);
    }

    /**
     * Admin can create any user. Team leader can only create sales employees.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isTeamLeader();
    }

    /**
     * Admin can edit any user. Team leader can only edit their own sales.
     */
    public function update(User $user, User $model): bool
    {
        // Prevent editing yourself — use profile page for that.
        if ($user->id === $model->id) {
            return $user->isAdmin();
        }

        return $this->canManageUser($user, $model);
    }

    /**
     * Only admin can delete users. Team leaders can delete their own sales employees.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeamLeader()) {
            return $model->isSales() && $model->team_leader_id === $user->id;
        }

        return false;
    }

    /**
     * Only admin can restore deleted users.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admin can force-delete users.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Determine if the acting user can manage the given user record.
     */
    private function canManageUser(User $user, User $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeamLeader()) {
            // Team leader can only manage their own sales employees.
            return $model->isSales() && $model->team_leader_id === $user->id;
        }

        return false;
    }
}
