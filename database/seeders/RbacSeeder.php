<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RbacSeeder extends Seeder
{
    private const ROLE_PERMISSIONS = [
        'admin' => [
            PermissionName::ViewUsers, PermissionName::UpdateUsers,
            PermissionName::CreateCategories, PermissionName::UpdateCategories, PermissionName::DeleteCategories,
            PermissionName::ViewAnyCourses, PermissionName::CreateCourses, PermissionName::UpdateAnyCourses,
            PermissionName::DeleteAnyCourses, PermissionName::PublishAnyCourses,
            PermissionName::ViewAnyLessons, PermissionName::CreateAnyLessons,
            PermissionName::UpdateAnyLessons, PermissionName::DeleteAnyLessons,
            PermissionName::DownloadOwnCertificates,
        ],
        'teacher' => [
            PermissionName::ViewOwnCourses, PermissionName::CreateCourses, PermissionName::UpdateOwnCourses,
            PermissionName::DeleteOwnCourses, PermissionName::PublishOwnCourses,
            PermissionName::ViewOwnLessons, PermissionName::CreateOwnLessons,
            PermissionName::UpdateOwnLessons, PermissionName::DeleteOwnLessons,
            PermissionName::DownloadOwnCertificates,
        ],
        'student' => [
            PermissionName::PurchaseCourses, PermissionName::StudyCourses, PermissionName::CompleteLessons,
            PermissionName::ViewOwnCertificates, PermissionName::DownloadOwnCertificates,
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (PermissionName::cases() as $permission) {
                Permission::firstOrCreate(['name' => $permission->value]);
            }

            foreach (self::ROLE_PERMISSIONS as $name => $permissions) {
                $role = Role::firstOrCreate(['name' => $name]);
                $names = array_map(fn (PermissionName $permission): string => $permission->value, $permissions);
                $role->permissions()->sync(Permission::whereIn('name', $names)->pluck('id'));
            }
        });
    }
}
