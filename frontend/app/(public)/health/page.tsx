import { AppShell } from '@/components/ui/AppShell';
import { Card } from '@/components/ui/Card';
import { fetchHealth, fetchVersion } from '@/services/health.service';

export const dynamic = 'force-dynamic';

export default async function HealthPage() {
  let health = null;
  let version = null;
  let error: string | null = null;

  try {
    [health, version] = await Promise.all([fetchHealth(), fetchVersion()]);
  } catch (e) {
    error = e instanceof Error ? e.message : 'API unreachable';
  }

  return (
    <AppShell title="Stack Health" workspace="public">
      <div className="grid gap-4 md:grid-cols-2">
        <Card title="Frontend">
          <p className="text-sm text-zinc-600">NeatMeet OS web shell is running.</p>
          <p className="mt-2 font-mono text-xs text-zinc-500">Next.js PWA foundation</p>
        </Card>

        <Card title="Backend API">
          {error ? (
            <p className="text-sm text-red-600">{error}</p>
          ) : (
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">Status</dt>
                <dd className="font-medium">{health?.status}</dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">Service</dt>
                <dd className="font-mono text-xs">{health?.service}</dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">Database</dt>
                <dd>{health?.checks.database.status}</dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">API version</dt>
                <dd>{version?.version}</dd>
              </div>
            </dl>
          )}
        </Card>
      </div>
    </AppShell>
  );
}
