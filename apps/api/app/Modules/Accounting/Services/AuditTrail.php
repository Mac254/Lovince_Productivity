<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AuditLog;
use App\Modules\Users\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Records who changed which accounting record and how, inside the same database transaction
 * as the change itself.
 */
final class AuditTrail
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(User $user, string $action, Model $subject, array $changes = []): void
    {
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => self::type($subject),
            'subject_id' => $subject->getKey(),
            'changes' => $changes === [] ? null : $changes,
        ]);
    }

    /**
     * The changed fields of a model being saved, as [before, after].
     *
     * @param  list<string>  $ignore
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function diff(Model $model, array $ignore = ['updated_at']): array
    {
        $changes = [];
        foreach (array_keys($model->getDirty()) as $field) {
            if (in_array($field, $ignore, true)) {
                continue;
            }
            $changes[$field] = [self::plain($model->getOriginal($field)), self::plain($model->getAttribute($field))];
        }

        return $changes;
    }

    private static function type(Model $subject): string
    {
        return match (class_basename($subject)) {
            'FinancialAccount' => 'account',
            default => strtolower(class_basename($subject)),
        };
    }

    private static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => $value,
        };
    }
}
