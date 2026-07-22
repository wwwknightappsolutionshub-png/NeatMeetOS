'use client';

import Link from 'next/link';
import { useParams, useSearchParams } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import { AdminStaffShell } from '@/components/admin/staff/AdminStaffShell';
import { AbsencesTab } from '@/components/admin/staff/tabs/AbsencesTab';
import { AvailabilityTab } from '@/components/admin/staff/tabs/AvailabilityTab';
import { OperatingScopeTab } from '@/components/admin/staff/tabs/OperatingScopeTab';
import { ProfileTab } from '@/components/admin/staff/tabs/ProfileTab';
import { ErrorAlert, LoadingState, StatusBadge } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import type { Location, Workspace } from '@/lib/identity-types';
import type { StaffProvider } from '@/lib/staff-types';
import { fetchLocations, fetchWorkspaces } from '@/services/identity.service';
import { fetchStaffProvider } from '@/services/staff.service';

type Tab = 'profile' | 'availability' | 'absences' | 'scope';

const STAFF_TABS: Tab[] = ['profile', 'availability', 'absences', 'scope'];

export default function StaffDetailPage() {
  const params = useParams();
  const searchParams = useSearchParams();
  const teamMemberId = params.teamMemberId as string;

  const initialTab = (searchParams.get('tab') as Tab | null) ?? 'profile';
  const [provider, setProvider] = useState<StaffProvider | null>(null);
  const [locations, setLocations] = useState<Location[]>([]);
  const [workspaces, setWorkspaces] = useState<Workspace[]>([]);
  const [tab, setTab] = useState<Tab>(
    STAFF_TABS.includes(initialTab) ? initialTab : 'profile',
  );
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([fetchStaffProvider(teamMemberId), fetchLocations(), fetchWorkspaces()])
      .then(([p, locs, wss]) => {
        setProvider(p);
        setLocations(locs);
        setWorkspaces(wss);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [teamMemberId]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    const next = searchParams.get('tab') as Tab | null;
    if (next && STAFF_TABS.includes(next)) {
      setTab(next);
    }
  }, [searchParams]);
  if (loading && !provider) {
    return (
      <AdminStaffShell title="Provider">
        <LoadingState />
      </AdminStaffShell>
    );
  }

  return (
    <AdminStaffShell title={provider?.display_name ?? 'Provider'}>
      <p className="mb-4 text-sm">
        <Link href="/admin/staff" className="text-zinc-600 hover:underline">
          ← Back to staff
        </Link>
      </p>
      {error ? <ErrorAlert message={error} /> : null}

      {provider ? (
        <div className="mb-4 flex flex-wrap items-center gap-2 text-sm text-zinc-600">
          <StatusBadge active={provider.is_active} />
          <span>{provider.employment_type.replace(/_/g, ' ')}</span>
          {provider.is_bookable ? <span>· Bookable</span> : <span>· Not bookable</span>}
        </div>
      ) : null}

      <div className="mb-4 flex flex-wrap gap-2">
        {(['profile', 'availability', 'absences', 'scope'] as Tab[]).map((t) => (
          <Button
            key={t}
            type="button"
            variant={tab === t ? 'primary' : 'secondary'}
            onClick={() => setTab(t)}
          >
            {t === 'scope' ? 'Operating scope' : t.charAt(0).toUpperCase() + t.slice(1)}
          </Button>
        ))}
      </div>

      {provider && tab === 'profile' ? (
        <ProfileTab provider={provider} onSaved={load} />
      ) : null}

      {provider && tab === 'availability' ? (
        <AvailabilityTab
          teamMemberId={teamMemberId}
          locations={locations}
          workspaces={workspaces}
        />
      ) : null}

      {tab === 'absences' ? <AbsencesTab teamMemberId={teamMemberId} /> : null}

      {provider && tab === 'scope' ? (
        <OperatingScopeTab
          provider={provider}
          locations={locations}
          workspaces={workspaces}
          onSaved={load}
        />
      ) : null}
    </AdminStaffShell>
  );
}
