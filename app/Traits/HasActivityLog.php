<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

trait HasActivityLog
{
    /**
     * Get all activity logs for this model.
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'loggable');
    }

    /**
     * Log an activity for this model.
     */
    public function logActivity(string $action, array $properties = []): ActivityLog
    {
        return $this->activityLogs()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'properties' => $properties,
        ]);
    }

    /**
     * Boot the trait to automatically log certain activities.
     */
    protected static function bootHasActivityLog(): void
    {
        static::created(function ($model) {
            $model->logActivity('created', ['attributes' => $model->getAttributes()]);
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            if (!empty($changes)) {
                $model->logActivity('updated', [
                    'old' => array_intersect_key($model->getOriginal(), $changes),
                    'new' => $changes,
                ]);
            }
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }
}
