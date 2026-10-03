<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();
        $data['committee_roles'] = $user->roles->pluck('name')->intersect(User::COMMITTEE_ROLES)->values()->all();
        unset($data['password']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $selected = array_values(array_intersect($this->data['committee_roles'] ?? [], User::COMMITTEE_ROLES));

        // A super admin cannot remove their own super_admin role (avoids locking everyone out).
        if ($user->is(auth()->user()) && ! in_array('super_admin', $selected, true)) {
            $selected[] = 'super_admin';
            Notification::make()->title('You cannot remove your own super admin role')->warning()->send();
        }

        $before = $user->roles->pluck('name')->intersect(User::COMMITTEE_ROLES)->values()->all();
        $other = $user->roles->pluck('name')->diff(User::COMMITTEE_ROLES)->values()->all(); // keep non-committee roles
        $user->syncRoles(array_merge($other, $selected));

        sort($before);
        sort($selected);
        if ($before !== $selected) {
            AuditLog::record('admin.user.roles_changed', $user, ['from' => $before, 'to' => $selected]);
        }
    }
}
