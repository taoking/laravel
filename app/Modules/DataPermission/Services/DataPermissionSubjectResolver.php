<?php

namespace App\Modules\DataPermission\Services;

use App\Models\User;

class DataPermissionSubjectResolver
{
    /**
     * @return list<array{subject_type: string, subject_id: int}>
     */
    public function subjects(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $subjects = [
            ['subject_type' => 'user', 'subject_id' => (int) $user->id],
        ];

        foreach ($user->roles()->pluck('roles.id') as $roleId) {
            $subjects[] = ['subject_type' => 'role', 'subject_id' => (int) $roleId];
        }

        if ($user->department_id !== null) {
            $subjects[] = ['subject_type' => 'department', 'subject_id' => (int) $user->department_id];
        }

        if ($user->organization_id !== null) {
            $subjects[] = ['subject_type' => 'organization', 'subject_id' => (int) $user->organization_id];
        }

        return $subjects;
    }
}
