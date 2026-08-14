'use client';

import { useId, useState } from 'react';

const inputClass =
  'mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 outline-none transition focus:border-[#2f5a45] focus:ring-2 focus:ring-[#2f5a45]/20';

export function normalizeClockValue(raw: string): string {
  const digits = raw.replace(/[^\d]/g, '');
  if (digits.length >= 4) {
    const hh = Math.min(23, Number(digits.slice(0, 2)));
    const mm = Math.min(59, Number(digits.slice(2, 4)));
    return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
  }
  const match = raw.trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
  if (!match) return raw.trim();
  const hh = Math.min(23, Number(match[1]));
  const mm = Math.min(59, Number(match[2]));
  return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
}

export function ClockTimeField({
  label,
  value,
  onChange,
  required,
  help,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  required?: boolean;
  help?: string | null;
}) {
  const textId = useId();
  const pickerId = useId();
  const [typed, setTyped] = useState(value);
  const display = value || typed;
  const pickerValue = /^\d{2}:\d{2}/.test(display) ? display.slice(0, 5) : '';

  function commit(next: string) {
    const normalized = normalizeClockValue(next);
    setTyped(normalized);
    onChange(normalized);
  }

  return (
    <div className="block text-sm">
      <span className="font-medium text-stone-700">{label}</span>
      <div className="mt-1 grid grid-cols-1 gap-2 min-[380px]:grid-cols-[1fr_auto]">
        <input
          id={textId}
          type="text"
          inputMode="numeric"
          autoComplete="off"
          placeholder="09:00"
          maxLength={5}
          value={display}
          required={required}
          onChange={(e) => {
            const next = e.target.value;
            setTyped(next);
            if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(next.trim()) || next.replace(/\D/g, '').length >= 4) {
              onChange(normalizeClockValue(next));
            } else {
              onChange(next);
            }
          }}
          onBlur={() => commit(typed || value)}
          className={inputClass.replace('mt-1 ', '')}
          aria-describedby={help ? `${textId}-help` : undefined}
        />
        <input
          id={pickerId}
          type="time"
          step={60}
          value={pickerValue}
          aria-label={`${label} clock`}
          onChange={(e) => commit(e.target.value)}
          className={`${inputClass.replace('mt-1 ', '')} min-[380px]:w-[7.5rem]`}
        />
      </div>
      <p id={`${textId}-help`} className="mt-1 text-[11px] text-stone-400">
        {help || 'Type HH:MM (e.g. 09:00), or use the clock control.'}
      </p>
    </div>
  );
}
