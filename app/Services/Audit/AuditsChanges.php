<?php

namespace App\Services\Audit;

use App\Services\Audit\AuditLogger;

trait AuditsChanges
{
    /**
     * Fields that must be redacted ('***') if they appear in before/after diffs.
     */
    protected array $auditRedacted = ['password', 'remember_token', 'api_token'];

    /**
     * Fields that must never appear in diffs at all (noise).
     */
    protected array $auditIgnored = ['updated_at', 'created_at'];

    public static function bootAuditsChanges(): void
    {
        static::created(function ($model) {
            app(AuditLogger::class)->log(
                action: $model->auditActionName('created'),
                target: $model,
                properties: ['after' => $model->auditRedactAttributes($model->getAttributes())],
            );
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            foreach ($model->auditIgnored as $ignored) {
                unset($changes[$ignored]);
            }
            if (empty($changes)) {
                return;
            }
            $before = [];
            foreach ($changes as $key => $_) {
                $before[$key] = $model->getOriginal($key);
            }

            app(AuditLogger::class)->log(
                action: $model->auditActionName('updated'),
                target: $model,
                properties: [
                    'before' => $model->auditRedactAttributes($before),
                    'after' => $model->auditRedactAttributes($changes),
                ],
            );
        });

        static::deleted(function ($model) {
            app(AuditLogger::class)->log(
                action: $model->auditActionName('deleted'),
                target: $model,
                properties: ['before' => $model->auditRedactAttributes($model->getAttributes())],
            );
        });
    }

    protected function auditActionName(string $verb): string
    {
        $class = strtolower(class_basename(static::class));
        return "{$class}.{$verb}";
    }

    protected function auditRedactAttributes(array $attrs): array
    {
        foreach ($this->auditRedacted as $key) {
            if (array_key_exists($key, $attrs)) {
                $attrs[$key] = '***';
            }
        }
        foreach ($this->auditIgnored as $key) {
            unset($attrs[$key]);
        }
        return $attrs;
    }
}
