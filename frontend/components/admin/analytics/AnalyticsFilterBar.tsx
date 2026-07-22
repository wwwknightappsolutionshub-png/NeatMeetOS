'use client';

import { useEffect, useState } from 'react';
import { Field, inputClass } from '@/components/admin/ui';
import type { Location, TeamMember } from '@/lib/identity-types';
import { fetchLocations, fetchTeamMembers } from '@/services/identity.service';

export interface AnalyticsFilterValues {
  from: string;
  to: string;
  location_id: string;
  provider_id: string;
}

interface AnalyticsFilterBarProps {
  value: AnalyticsFilterValues;
  onChange: (value: AnalyticsFilterValues) => void;
  showLocation?: boolean;
  showProvider?: boolean;
}

function teamMemberLabel(member: TeamMember): string {
  const name = [member.first_name, member.last_name].filter(Boolean).join(' ').trim();
  return name || member.display_name || member.email || member.id;
}

export function AnalyticsFilterBar({
  value,
  onChange,
  showLocation = true,
  showProvider = true,
}: AnalyticsFilterBarProps) {
  const [locations, setLocations] = useState<Location[]>([]);
  const [providers, setProviders] = useState<TeamMember[]>([]);

  useEffect(() => {
    if (showLocation) {
      fetchLocations().then(setLocations).catch(() => setLocations([]));
    }
    if (showProvider) {
      fetchTeamMembers().then(setProviders).catch(() => setProviders([]));
    }
  }, [showLocation, showProvider]);

  function update(patch: Partial<AnalyticsFilterValues>) {
    onChange({ ...value, ...patch });
  }

  return (
    <div className="mb-6 grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
      <Field label="From">
        <input
          type="date"
          className={inputClass}
          value={value.from}
          max={value.to || undefined}
          onChange={(e) => update({ from: e.target.value })}
        />
      </Field>
      <Field label="To">
        <input
          type="date"
          className={inputClass}
          value={value.to}
          min={value.from || undefined}
          onChange={(e) => update({ to: e.target.value })}
        />
      </Field>
      {showLocation ? (
        <Field label="Location">
          <select
            className={inputClass}
            value={value.location_id}
            onChange={(e) => update({ location_id: e.target.value })}
          >
            <option value="">All locations</option>
            {locations.map((loc) => (
              <option key={loc.id} value={loc.id}>
                {loc.name}
              </option>
            ))}
          </select>
        </Field>
      ) : null}
      {showProvider ? (
        <Field label="Provider">
          <select
            className={inputClass}
            value={value.provider_id}
            onChange={(e) => update({ provider_id: e.target.value })}
          >
            <option value="">All providers</option>
            {providers.map((member) => (
              <option key={member.id} value={member.id}>
                {teamMemberLabel(member)}
              </option>
            ))}
          </select>
        </Field>
      ) : null}
    </div>
  );
}
