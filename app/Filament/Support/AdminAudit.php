<?php

namespace App\Filament\Support;

use App\Models\AuditLog;
use App\Models\InAppNotification;
use App\Models\KnowledgeChunk;
use App\Models\Message;
use App\Models\SmsLog;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Writes an AuditLog entry for every Eloquent create, update and delete that happens while a signed-in
 * user is working in the committee panel (page requests and Livewire updates alike). This covers resource
 * pages, modal actions, relation managers and custom pages without touching the models.
 * Only field names are logged, never values.
 */
class AdminAudit
{
    /** Models that are either audit records themselves, derived data, or logged explicitly elsewhere. */
    public const IGNORED = [AuditLog::class, KnowledgeChunk::class, InAppNotification::class, SmsLog::class, Message::class];

    public const SECRET_FIELDS = ['password', 'app_authentication_secret', 'app_authentication_recovery_codes', 'national_id_hash', 'fidelity_reference', 'remember_token'];

    private static bool $writing = false;

    public static function register(): void
    {
        foreach (['created', 'updated', 'deleted'] as $verb) {
            Event::listen("eloquent.{$verb}: *", function (string $event, array $payload) use ($verb) {
                $model = $payload[0] ?? null;
                if ($model instanceof Model) {
                    self::handle($verb, $model);
                }
            });
        }
    }

    private static function handle(string $verb, Model $model): void
    {
        if (self::$writing || in_array($model::class, self::IGNORED, true) || str_starts_with($model::class, 'Spatie\\')) {
            return;
        }
        if (Filament::getCurrentPanel()?->getId() !== 'admin' || ! auth()->check()) {
            return;
        }

        $fields = [];
        if ($verb === 'updated') {
            $fields = array_values(array_diff(array_keys($model->getChanges()), [...self::SECRET_FIELDS, 'updated_at']));
            if (! $fields) {
                return; // only secrets or timestamps changed (e.g. MFA setup, remember token)
            }
        }

        self::$writing = true;
        try {
            AuditLog::record(
                'admin.'.str(class_basename($model))->snake().'.'.$verb,
                $model,
                $fields ? ['fields' => $fields] : [],
            );
        } finally {
            self::$writing = false;
        }
    }
}
