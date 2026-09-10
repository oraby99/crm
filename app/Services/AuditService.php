<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Record an auditable action.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function log(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Log a customer creation event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function customerCreated(Model $customer, array $attributes): void
    {
        static::log('customer.created', $customer, null, $attributes);
    }

    /**
     * Log a customer update event.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function customerUpdated(Model $customer, array $oldValues, array $newValues): void
    {
        static::log('customer.updated', $customer, $oldValues, $newValues);
    }

    /**
     * Log a customer assignment or reassignment.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function customerAssigned(Model $customer, array $oldValues, array $newValues): void
    {
        static::log('customer.assigned', $customer, $oldValues, $newValues);
    }

    /**
     * Log a customer status change.
     */
    public static function statusChanged(Model $customer, ?int $oldStatusId, ?int $newStatusId): void
    {
        static::log('customer.status_changed', $customer, ['status_id' => $oldStatusId], ['status_id' => $newStatusId]);
    }

    /**
     * Log customer deletion.
     */
    public static function customerDeleted(Model $customer): void
    {
        static::log('customer.deleted', $customer);
    }

    /**
     * Log customer restoration.
     */
    public static function customerRestored(Model $customer): void
    {
        static::log('customer.restored', $customer);
    }

    /**
     * Log a user creation event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function userCreated(Model $user, array $attributes): void
    {
        static::log('user.created', $user, null, $attributes);
    }

    /**
     * Log a user activation/deactivation event.
     */
    public static function userStatusChanged(Model $user, bool $oldStatus, bool $newStatus): void
    {
        static::log('user.status_changed', $user, ['is_active' => $oldStatus], ['is_active' => $newStatus]);
    }

    /**
     * Log an import completed event.
     */
    public static function importCompleted(Model $import): void
    {
        static::log('import.completed', $import);
    }
}
