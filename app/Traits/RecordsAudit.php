<?php

namespace App\Traits;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait RecordsAudit
{
    public static function bootRecordsAudit(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->log(
                static::auditEventName('created'),
                $model,
                null,
                [],
                static::auditValues($model->getAttributes(), $model),
            );
        });

        static::updated(function (Model $model): void {
            app(AuditLogger::class)->log(
                static::auditEventName('updated'),
                $model,
                null,
                static::auditValues($model->getOriginal(), $model),
                static::auditValues($model->getChanges(), $model),
            );
        });

        static::deleted(function (Model $model): void {
            app(AuditLogger::class)->log(
                static::auditEventName('deleted'),
                $model,
                null,
                static::auditValues($model->getOriginal(), $model),
            );
        });
    }

    protected static function auditEventName(string $action): string
    {
        return str(class_basename(static::class))->snake()->append('.'.$action)->toString();
    }

    protected static function auditValues(array $values, Model $model): array
    {
        $values = Arr::except($values, method_exists($model, 'auditRedactedAttributes') ? $model->auditRedactedAttributes() : []);

        foreach (method_exists($model, 'auditMaskedAttributes') ? $model->auditMaskedAttributes() : [] as $attribute) {
            if (array_key_exists($attribute, $values)) {
                $values[$attribute] = '[REDACTED]';
            }
        }

        return $values;
    }
}
