<?php

namespace App\Scopes;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Automatically scopes the Customer query to the authenticated user's access level.
 *
 * - Admin: no restriction (sees all customers)
 * - TeamLeader: sees only customers belonging to their team
 * - Sales: sees only their own assigned customers
 */
class CustomerScope implements Scope
{
    /**
     * Apply the scope to the given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            // No authenticated user — return nothing.
            $builder->whereRaw('0 = 1');

            return;
        }

        if ($user->role === UserRole::Admin) {
            // Admin sees everything — no scope restriction.
            return;
        }

        if ($user->role === UserRole::TeamLeader) {
            $builder->where('team_leader_id', $user->id);

            return;
        }

        // Sales — see only their own customers.
        $builder->where('sales_id', $user->id);
    }
}
