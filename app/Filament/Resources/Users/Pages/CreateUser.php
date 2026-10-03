<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['phone_verified_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $roles = array_values(array_intersect($this->data['committee_roles'] ?? [], User::COMMITTEE_ROLES));
        $user->syncRoles($roles);
        AuditLog::record('admin.user.roles_changed', $user, ['roles' => $roles]);
    }
}
