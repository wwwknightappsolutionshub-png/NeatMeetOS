'use client';

import { useEffect, useRef, useState } from 'react';
import type { SignupFormField } from '@/lib/types';
import {
  lookupAddressByPostcode,
  type AddressSuggestion,
} from '@/services/signup.service';

const inputClass =
  'mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 outline-none transition focus:border-[#2f5a45] focus:ring-2 focus:ring-[#2f5a45]/20';

const UK_POSTCODE =
  /^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i;

function fieldLabel(field: SignupFormField): string {
  const alreadyOptional = /\(\s*optional\s*\)/i.test(field.label);
  if (field.required || alreadyOptional) return field.label;
  return `${field.label} (optional)`;
}

interface PostcodeAddressFieldProps {
  field: SignupFormField;
  value: string;
  onChange: (value: string) => void;
  onSelectAddress: (suggestion: AddressSuggestion) => void;
}

export function PostcodeAddressField({
  field,
  value,
  onChange,
  onSelectAddress,
}: PostcodeAddressFieldProps) {
  const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const wrapRef = useRef<HTMLDivElement>(null);
  const requestId = useRef(0);

  useEffect(() => {
    function onDocClick(e: MouseEvent) {
      if (!wrapRef.current?.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, []);

  useEffect(() => {
    const trimmed = value.trim();
    if (!UK_POSTCODE.test(trimmed)) {
      setSuggestions([]);
      setError(null);
      setOpen(false);
      return;
    }

    const id = ++requestId.current;
    const timer = window.setTimeout(() => {
      setLoading(true);
      setError(null);
      void lookupAddressByPostcode(trimmed)
        .then((data) => {
          if (id !== requestId.current) return;
          setSuggestions(data.suggestions);
          setOpen(data.suggestions.length > 0);
        })
        .catch((e) => {
          if (id !== requestId.current) return;
          setSuggestions([]);
          setOpen(false);
          setError(e instanceof Error ? e.message : 'Address lookup failed');
        })
        .finally(() => {
          if (id === requestId.current) setLoading(false);
        });
    }, 450);

    return () => window.clearTimeout(timer);
    // intentionally omit onChange to avoid re-fetch loops when formatting postcode
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value]);

  return (
    <div ref={wrapRef} className="relative block text-sm">
      <span className="font-medium text-stone-700">{fieldLabel(field)}</span>
      <input
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value.toUpperCase())}
        onFocus={() => {
          if (suggestions.length > 0) setOpen(true);
        }}
        className={inputClass}
        required={field.required}
        placeholder={field.placeholder ?? 'e.g. E1 1AA'}
        autoComplete="postal-code"
      />
      {loading ? (
        <p className="mt-1 text-[11px] text-stone-400">Looking up addresses…</p>
      ) : null}
      {error ? <p className="mt-1 text-[11px] text-red-600">{error}</p> : null}
      {field.help && !loading && !error ? (
        <p className="mt-1 text-[11px] text-stone-400">{field.help}</p>
      ) : null}

      {open && suggestions.length > 0 ? (
        <ul className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-stone-200 bg-white py-1 shadow-lg">
          {suggestions.map((suggestion) => (
            <li key={suggestion.label}>
              <button
                type="button"
                className="block w-full px-3 py-2 text-left text-sm text-stone-800 hover:bg-[#e8f0eb]"
                onClick={() => {
                  onSelectAddress(suggestion);
                  setOpen(false);
                }}
              >
                {suggestion.label}
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
