import { AppShell } from '@/components/ui/AppShell';
import { Card } from '@/components/ui/Card';

export default function DeskWorkspacePage() {
  return (
    <AppShell title="Front desk workspace" workspace="desk">
      <Card title="Reception shell">
        <p className="text-sm text-zinc-600">
          Placeholder layout for calendar, POS, waitlist, and client lookup —
          implemented in Booking and POS modules.
        </p>
      </Card>
    </AppShell>
  );
}
