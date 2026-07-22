import { AppShell } from '@/components/ui/AppShell';
import { Card } from '@/components/ui/Card';

export default function ProviderWorkspacePage() {
  return (
    <AppShell title="Provider workspace" workspace="provider">
      <Card title="Stylist / freelancer shell">
        <p className="text-sm text-zinc-600">
          Placeholder layout for provider calendar, chair/room assignment, and
          scoped client access — implemented in Staff and Booking modules.
        </p>
      </Card>
    </AppShell>
  );
}
