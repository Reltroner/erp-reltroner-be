<?php

namespace Database\Seeders;

use App\Models\FeatureEntitlement;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\RolePermission;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Plans
        $freePlan = Plan::updateOrCreate(['plan_key' => 'free'], ['name' => 'Free Tier', 'description' => 'Standard free workspace limits']);
        $growthPlan = Plan::updateOrCreate(['plan_key' => 'growth'], ['name' => 'Growth Tier', 'description' => 'Growth workspace parameters']);
        $enterprisePlan = Plan::updateOrCreate(['plan_key' => 'enterprise'], ['name' => 'Enterprise Tier', 'description' => 'Unlimited modules access']);

        // 2. Seed Plan Limits
        PlanLimit::updateOrCreate(['plan_key' => 'free', 'resource_key' => 'users_limit'], ['max_value' => 5]);
        PlanLimit::updateOrCreate(['plan_key' => 'free', 'resource_key' => 'storage_mb_limit'], ['max_value' => 512]);
        PlanLimit::updateOrCreate(['plan_key' => 'growth', 'resource_key' => 'users_limit'], ['max_value' => 50]);
        PlanLimit::updateOrCreate(['plan_key' => 'growth', 'resource_key' => 'storage_mb_limit'], ['max_value' => 10240]);
        PlanLimit::updateOrCreate(['plan_key' => 'enterprise', 'resource_key' => 'users_limit'], ['max_value' => 9999]);
        PlanLimit::updateOrCreate(['plan_key' => 'enterprise', 'resource_key' => 'storage_mb_limit'], ['max_value' => 1048576]);

        // 3. Seed Permissions List
        $permissions = [
            // Admin Panel permissions
            ['permission_key' => 'admin.dashboard.view', 'description' => 'View operational dashboard', 'module_key' => 'admin'],
            ['permission_key' => 'admin.tenants.view', 'description' => 'View tenant registry', 'module_key' => 'admin'],
            ['permission_key' => 'admin.tenants.update', 'description' => 'Update tenant configuration', 'module_key' => 'admin'],
            ['permission_key' => 'admin.users.view', 'description' => 'Inspect user access controls', 'module_key' => 'admin'],
            ['permission_key' => 'admin.entitlements.view', 'description' => 'View plan entitlements', 'module_key' => 'admin'],
            ['permission_key' => 'admin.entitlements.update', 'description' => 'Modify plan entitlements', 'module_key' => 'admin'],
            ['permission_key' => 'admin.audit.view', 'description' => 'Inspect append-only logs', 'module_key' => 'admin'],
            ['permission_key' => 'admin.usage.view', 'description' => 'Inspect resource usage logs', 'module_key' => 'admin'],

            // ERP Business permissions
            ['permission_key' => 'inventory.products.view', 'description' => 'View inventory warehouse records', 'module_key' => 'inventory'],
            ['permission_key' => 'inventory.products.create', 'description' => 'Create inventory warehouse records', 'module_key' => 'inventory'],
            ['permission_key' => 'sales.transactions.create', 'description' => 'Create sales ledger entries', 'module_key' => 'sales'],
            ['permission_key' => 'finance.journals.post', 'description' => 'Post ledger journal allocations', 'module_key' => 'finance'],
            ['permission_key' => 'reports.financial.view', 'description' => 'View financial reports data', 'module_key' => 'reports'],
            ['permission_key' => 'settings.company.update', 'description' => 'Modify company workspace configurations', 'module_key' => 'settings'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(['permission_key' => $p['permission_key']], $p);
        }

        // 4. Seed Role Permission Mappings
        // super-admin gets all admin.* permissions
        $superAdminRoles = [
            'admin.dashboard.view',
            'admin.tenants.view',
            'admin.tenants.update',
            'admin.users.view',
            'admin.entitlements.view',
            'admin.entitlements.update',
            'admin.audit.view',
            'admin.usage.view',
        ];
        foreach ($superAdminRoles as $perm) {
            RolePermission::updateOrCreate(['role_key' => 'super-admin', 'permission_key' => $perm], ['scope' => 'system']);
        }

        // erp-admin gets read/write admin permissions except modifying plans
        $erpAdminRoles = [
            'admin.dashboard.view',
            'admin.tenants.view',
            'admin.users.view',
            'admin.entitlements.view',
            'admin.audit.view',
        ];
        foreach ($erpAdminRoles as $perm) {
            RolePermission::updateOrCreate(['role_key' => 'erp-admin', 'permission_key' => $perm], ['scope' => 'system']);
        }

        // owner role gets all ERP features
        $ownerRoles = [
            'inventory.products.view',
            'inventory.products.create',
            'sales.transactions.create',
            'finance.journals.post',
            'reports.financial.view',
            'settings.company.update',
        ];
        foreach ($ownerRoles as $perm) {
            RolePermission::updateOrCreate(['role_key' => 'owner', 'permission_key' => $perm], ['scope' => 'tenant']);
        }

        // viewer role gets view only permissions
        $viewRoles = [
            'inventory.products.view',
            'reports.financial.view',
        ];
        foreach ($viewRoles as $perm) {
            RolePermission::updateOrCreate(['role_key' => 'viewer', 'permission_key' => $perm], ['scope' => 'tenant']);
        }

        // 5. Seed Feature Entitlements
        // Free plan has view features enabled
        FeatureEntitlement::updateOrCreate(['plan_key' => 'free', 'feature_key' => 'inventory.products.view'], ['is_enabled' => true]);
        FeatureEntitlement::updateOrCreate(['plan_key' => 'free', 'feature_key' => 'sales.transactions.create'], ['is_enabled' => true]);
        FeatureEntitlement::updateOrCreate(['plan_key' => 'free', 'feature_key' => 'inventory.products.create'], ['is_enabled' => false]);

        // Growth plan has create features enabled
        FeatureEntitlement::updateOrCreate(['plan_key' => 'growth', 'feature_key' => 'inventory.products.view'], ['is_enabled' => true]);
        FeatureEntitlement::updateOrCreate(['plan_key' => 'growth', 'feature_key' => 'inventory.products.create'], ['is_enabled' => true]);
        FeatureEntitlement::updateOrCreate(['plan_key' => 'growth', 'feature_key' => 'sales.transactions.create'], ['is_enabled' => true]);
        FeatureEntitlement::updateOrCreate(['plan_key' => 'growth', 'feature_key' => 'finance.journals.post'], ['is_enabled' => true]);

        // Enterprise plan has everything enabled
        FeatureEntitlement::updateOrCreate(['plan_key' => 'enterprise', 'feature_key' => 'inventory.products.view'], ['is_enabled' => true]);
        FeatureEntitlement::updateOrCreate(['plan_key' => 'enterprise', 'feature_key' => 'inventory.products.create'], ['is_enabled' => true]);
        FeatureEntitlement::updateOrCreate(['plan_key' => 'enterprise', 'feature_key' => 'sales.transactions.create'], ['is_enabled' => true]);
        FeatureEntitlement::updateOrCreate(['plan_key' => 'enterprise', 'feature_key' => 'finance.journals.post'], ['is_enabled' => true]);
        FeatureEntitlement::updateOrCreate(['plan_key' => 'enterprise', 'feature_key' => 'reports.financial.view'], ['is_enabled' => true]);

        // 6. Seed Default Tenant
        Tenant::updateOrCreate(
            ['slug' => 'default'],
            [
                'id' => '00000000-0000-0000-0000-000000000000',
                'name' => 'Default Organization',
                'status' => 'active',
                'plan_key' => 'free',
            ]
        );
    }
}
