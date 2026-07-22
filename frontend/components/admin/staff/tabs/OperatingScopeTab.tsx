'use client';

import { useCallback, useEffect, useState } from 'react';
import { EmptyState, Field, inputClass } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Location, Workspace } from '@/lib/identity-types';
import type { StaffProvider } from '@/lib/staff-types';
import { updateStaffOperatingScope } from '@/services/staff.service';

interface OperatingScopeTabProps {
  provider: StaffProvider;
  locations: Location[];
  workspaces: Workspace[];
  onSaved: () => void;
}

export function OperatingScopeTab({
  provider,
  locations,
  workspaces,
  onSaved,
}: OperatingScopeTabProps) {
  const [selectedLocations, setSelectedLocations] = useState<string[]>([]);
  const [selectedWorkspaces, setSelectedWorkspaces] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setSelectedLocations(provider.operating_location_ids ?? []);
    setSelectedWorkspaces(provider.workspace_ids ?? []);
  }, [provider]);

  function toggleLocation(id: string) {
    setSelectedLocations((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
    );
  }

  function toggleWorkspace(id: string) {
    setSelectedWorkspaces((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
    );
  }

  const save = useCallback(async () => {
    setError(null);
    try {
      await updateStaffOperatingScope(provider.id, {
        location_ids: selectedLocations,
        workspace_ids: selectedWorkspaces,
      });
      onSaved();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }, [provider.id, selectedLocations, selectedWorkspaces, onSaved]);

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card title="Operating locations">
        {error ? <p className="mb-2 text-sm text-red-600">{error}</p> : null}
        {locations.length === 0 ? <EmptyState message="No locations configured." /> : null}
        <ul className="mb-3 space-y-2">
          {locations.map((loc) => (
            <li key={loc.id} className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                id={`loc-${loc.id}`}
                checked={selectedLocations.includes(loc.id)}
                onChange={() => toggleLocation(loc.id)}
              />
              <label htmlFor={`loc-${loc.id}`}>{loc.name}</label>
            </li>
          ))}
        </ul>
      </Card>
      <Card title="Allowed workspaces">
        {workspaces.length === 0 ? <EmptyState message="No workspaces configured." /> : null}
        <ul className="mb-3 space-y-2">
          {workspaces.map((ws) => (
            <li key={ws.id} className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                id={`ws-${ws.id}`}
                checked={selectedWorkspaces.includes(ws.id)}
                onChange={() => toggleWorkspace(ws.id)}
              />
              <label htmlFor={`ws-${ws.id}`}>
                {ws.name} <span className="text-zinc-500">({ws.workspace_type})</span>
              </label>
            </li>
          ))}
        </ul>
        <Button type="button" onClick={save}>
          Save operating scope
        </Button>
      </Card>
    </div>
  );
}
