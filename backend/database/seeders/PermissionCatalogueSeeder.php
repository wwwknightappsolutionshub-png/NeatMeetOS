<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionCatalogueSeeder extends Seeder
{
    /**
     * @return array<int, array{id: string, name: string, slug: string, module: string}>
     */
    public static function catalogue(): array
    {
        return [
            ['id' => 'identity.view', 'name' => 'View identity settings', 'slug' => 'identity.view', 'module' => 'identity'],
            ['id' => 'identity.manage', 'name' => 'Manage organization settings', 'slug' => 'identity.manage', 'module' => 'identity'],
            ['id' => 'identity.access.manage', 'name' => 'Manage roles and access', 'slug' => 'identity.access.manage', 'module' => 'identity'],
            ['id' => 'identity.audit.view', 'name' => 'View audit logs', 'slug' => 'identity.audit.view', 'module' => 'identity'],
            ['id' => 'booking.view', 'name' => 'View bookings', 'slug' => 'booking.view', 'module' => 'booking'],
            ['id' => 'booking.manage', 'name' => 'Manage bookings', 'slug' => 'booking.manage', 'module' => 'booking'],
            ['id' => 'crm.view', 'name' => 'View CRM', 'slug' => 'crm.view', 'module' => 'crm'],
            ['id' => 'crm.manage', 'name' => 'Manage CRM', 'slug' => 'crm.manage', 'module' => 'crm'],
            ['id' => 'staff.view', 'name' => 'View staff operations', 'slug' => 'staff.view', 'module' => 'staff'],
            ['id' => 'staff.manage', 'name' => 'Manage staff operations', 'slug' => 'staff.manage', 'module' => 'staff'],
            ['id' => 'commerce.pos.view', 'name' => 'View POS', 'slug' => 'commerce.pos.view', 'module' => 'commerce'],
            ['id' => 'commerce.pos.manage', 'name' => 'Manage POS', 'slug' => 'commerce.pos.manage', 'module' => 'commerce'],
            ['id' => 'pos.view', 'name' => 'View POS checkouts', 'slug' => 'pos.view', 'module' => 'pos'],
            ['id' => 'pos.manage', 'name' => 'Manage POS checkouts', 'slug' => 'pos.manage', 'module' => 'pos'],
            ['id' => 'pos.checkout.complete', 'name' => 'Complete POS checkouts', 'slug' => 'pos.checkout.complete', 'module' => 'pos'],
            ['id' => 'pos.refund', 'name' => 'Refund POS checkouts', 'slug' => 'pos.refund', 'module' => 'pos'],
            ['id' => 'pos.checkout.reopen', 'name' => 'Reopen completed checkouts', 'slug' => 'pos.checkout.reopen', 'module' => 'pos'],
            ['id' => 'pos.receipt.manage', 'name' => 'Manage POS receipts', 'slug' => 'pos.receipt.manage', 'module' => 'pos'],
            ['id' => 'commerce.payments.view', 'name' => 'View payments (commerce)', 'slug' => 'commerce.payments.view', 'module' => 'commerce'],
            ['id' => 'commerce.payments.manage', 'name' => 'Manage payments (commerce)', 'slug' => 'commerce.payments.manage', 'module' => 'commerce'],
            ['id' => 'payments.view', 'name' => 'View payments', 'slug' => 'payments.view', 'module' => 'payments'],
            ['id' => 'payments.manage', 'name' => 'Manage payments', 'slug' => 'payments.manage', 'module' => 'payments'],
            ['id' => 'payments.refund', 'name' => 'Refund payments', 'slug' => 'payments.refund', 'module' => 'payments'],
            ['id' => 'payments.reporting.view', 'name' => 'View payment reporting', 'slug' => 'payments.reporting.view', 'module' => 'payments'],
            ['id' => 'commerce.inventory.view', 'name' => 'View inventory (commerce)', 'slug' => 'commerce.inventory.view', 'module' => 'commerce'],
            ['id' => 'commerce.inventory.manage', 'name' => 'Manage inventory (commerce)', 'slug' => 'commerce.inventory.manage', 'module' => 'commerce'],
            ['id' => 'inventory.view', 'name' => 'View inventory', 'slug' => 'inventory.view', 'module' => 'inventory'],
            ['id' => 'inventory.manage', 'name' => 'Manage inventory', 'slug' => 'inventory.manage', 'module' => 'inventory'],
            ['id' => 'inventory.adjust', 'name' => 'Adjust inventory stock', 'slug' => 'inventory.adjust', 'module' => 'inventory'],
            ['id' => 'inventory.reporting.view', 'name' => 'View inventory reporting', 'slug' => 'inventory.reporting.view', 'module' => 'inventory'],
            ['id' => 'commerce.memberships.view', 'name' => 'View memberships', 'slug' => 'commerce.memberships.view', 'module' => 'commerce'],
            ['id' => 'commerce.memberships.manage', 'name' => 'Manage memberships', 'slug' => 'commerce.memberships.manage', 'module' => 'commerce'],
            ['id' => 'memberships.view', 'name' => 'View memberships', 'slug' => 'memberships.view', 'module' => 'memberships'],
            ['id' => 'memberships.manage', 'name' => 'Manage memberships', 'slug' => 'memberships.manage', 'module' => 'memberships'],
            ['id' => 'memberships.reporting.view', 'name' => 'View membership reporting', 'slug' => 'memberships.reporting.view', 'module' => 'memberships'],
            ['id' => 'marketing.view', 'name' => 'View marketing', 'slug' => 'marketing.view', 'module' => 'marketing'],
            ['id' => 'marketing.manage', 'name' => 'Manage marketing', 'slug' => 'marketing.manage', 'module' => 'marketing'],
            ['id' => 'marketing.dispatch', 'name' => 'Dispatch marketing runs', 'slug' => 'marketing.dispatch', 'module' => 'marketing'],
            ['id' => 'marketing.reporting.view', 'name' => 'View marketing reporting', 'slug' => 'marketing.reporting.view', 'module' => 'marketing'],
            ['id' => 'notifications.view', 'name' => 'View notifications', 'slug' => 'notifications.view', 'module' => 'notifications'],
            ['id' => 'notifications.manage', 'name' => 'Manage notifications', 'slug' => 'notifications.manage', 'module' => 'notifications'],
            ['id' => 'notifications.reporting.view', 'name' => 'View notification reporting', 'slug' => 'notifications.reporting.view', 'module' => 'notifications'],
            ['id' => 'analytics.view', 'name' => 'View analytics', 'slug' => 'analytics.view', 'module' => 'analytics'],
            ['id' => 'analytics.reporting.view', 'name' => 'View analytics reporting', 'slug' => 'analytics.reporting.view', 'module' => 'analytics'],
            ['id' => 'analytics.exports.manage', 'name' => 'Manage analytics saved reports and exports', 'slug' => 'analytics.exports.manage', 'module' => 'analytics'],
            ['id' => 'integrations.view', 'name' => 'View provider integrations', 'slug' => 'integrations.view', 'module' => 'integrations'],
            ['id' => 'integrations.manage', 'name' => 'Manage provider integrations', 'slug' => 'integrations.manage', 'module' => 'integrations'],
            ['id' => 'integrations.reporting.view', 'name' => 'View provider integration reporting', 'slug' => 'integrations.reporting.view', 'module' => 'integrations'],
            ['id' => 'ecommerce.view', 'name' => 'View ecommerce', 'slug' => 'ecommerce.view', 'module' => 'ecommerce'],
            ['id' => 'ecommerce.manage', 'name' => 'Manage ecommerce', 'slug' => 'ecommerce.manage', 'module' => 'ecommerce'],
            ['id' => 'gallery.view', 'name' => 'View works gallery', 'slug' => 'gallery.view', 'module' => 'gallery'],
            ['id' => 'gallery.manage', 'name' => 'Manage works gallery', 'slug' => 'gallery.manage', 'module' => 'gallery'],
            ['id' => 'lookbook.view', 'name' => 'View lookbook', 'slug' => 'lookbook.view', 'module' => 'lookbook'],
            ['id' => 'lookbook.manage', 'name' => 'Manage lookbook', 'slug' => 'lookbook.manage', 'module' => 'lookbook'],
            ['id' => 'next_visit.view', 'name' => 'View next visit plans', 'slug' => 'next_visit.view', 'module' => 'next_visit'],
            ['id' => 'next_visit.manage', 'name' => 'Manage next visit plans', 'slug' => 'next_visit.manage', 'module' => 'next_visit'],
        ];
    }

    public function run(): void
    {
        foreach (self::catalogue() as $permission) {
            Permission::query()->updateOrCreate(['id' => $permission['id']], $permission);
        }
    }
}
