<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Roles & permissions live on the `web` guard. Both the `web` (session)
     * and `api` (Passport) guards share the `users` provider, so permission
     * checks resolve correctly in both back-office and API contexts.
     */
    private const GUARD = 'web';

    /** @var list<string> */
    private const RESOURCES = [
        'users',
        'roles',
        'categories',
        'quotes',
        'app_configs',
        'app_versions',
        'notifications',
        'articles',
        'tags',
        'vehicles',
        'appraisals',
        'service_bookings',
        'toyota_service_config',
        'otoxpert_service_config',
    ];

    /** @var list<string> */
    private const ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::RESOURCES as $resource) {
            foreach (self::ABILITIES as $ability) {
                Permission::findOrCreate("{$resource}.{$ability}", self::GUARD);
            }
        }

        $superAdmin = Role::findOrCreate('super-admin', self::GUARD);
        $admin = Role::findOrCreate('admin', self::GUARD);
        $staff = Role::findOrCreate('staff', self::GUARD);

        // super-admin also bypasses checks via Gate::before; permissions are
        // assigned explicitly so the back-office UI reflects full access.
        $superAdmin->syncPermissions(Permission::all());

        $admin->syncPermissions([
            'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.viewAny', 'roles.view',
            'categories.viewAny', 'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
            'app_configs.viewAny', 'app_configs.view', 'app_configs.create', 'app_configs.update', 'app_configs.delete',
            'app_versions.viewAny', 'app_versions.view', 'app_versions.create', 'app_versions.update', 'app_versions.delete',
            'notifications.viewAny', 'notifications.view', 'notifications.create', 'notifications.update', 'notifications.delete',
            'articles.viewAny', 'articles.view', 'articles.create', 'articles.update', 'articles.delete',
            'tags.viewAny', 'tags.view', 'tags.create', 'tags.update', 'tags.delete',
            'vehicles.viewAny', 'vehicles.view', 'vehicles.update',
            'appraisals.viewAny', 'appraisals.view', 'appraisals.update',
            'service_bookings.viewAny', 'service_bookings.view', 'service_bookings.create', 'service_bookings.update', 'service_bookings.delete',
            'toyota_service_config.viewAny', 'toyota_service_config.view', 'toyota_service_config.create', 'toyota_service_config.update', 'toyota_service_config.delete',
            'otoxpert_service_config.viewAny', 'otoxpert_service_config.view', 'otoxpert_service_config.create', 'otoxpert_service_config.update', 'otoxpert_service_config.delete',
        ]);

        $staff->syncPermissions([
            'categories.viewAny', 'categories.view', 'categories.create', 'categories.update',
            'quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update',
            'articles.viewAny', 'articles.view', 'articles.create', 'articles.update',
            'tags.viewAny', 'tags.view', 'tags.create', 'tags.update',
            'vehicles.viewAny', 'vehicles.view',
            'appraisals.viewAny', 'appraisals.view', 'appraisals.update',
            'service_bookings.viewAny', 'service_bookings.view', 'service_bookings.update',
            'toyota_service_config.viewAny', 'toyota_service_config.view',
            'otoxpert_service_config.viewAny', 'otoxpert_service_config.view',
        ]);
    }
}
