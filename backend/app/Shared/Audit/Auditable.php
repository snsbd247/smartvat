<?php

namespace App\Shared\Audit;

/**
 * Records create/update/delete events for a model into the audit trail.
 * Only changed attributes are stored on update; sensitive hidden attributes are
 * excluded from the recorded values.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLogger::log('created', $model, null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function ($model) {
            AuditLogger::log(
                'updated',
                $model,
                $model->auditableValues($model->getOriginal()),
                $model->auditableValues($model->getChanges()),
            );
        });

        static::deleted(function ($model) {
            AuditLogger::log('deleted', $model, $model->auditableValues($model->getOriginal()), null);
        });
    }

    protected function auditableValues(array $attributes): array
    {
        return collect($attributes)
            ->except($this->getHidden())
            ->except(['password', 'remember_token'])
            ->all();
    }
}
