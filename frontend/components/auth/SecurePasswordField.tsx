'use client';

import { useId, useState } from 'react';
import {
  evaluatePasswordRules,
  PASSWORD_RULES,
  type PasswordRuleId,
} from '@/lib/password-rules';

const inputClass =
  'w-full rounded-lg border border-stone-300 bg-white py-2 pl-3 pr-11 text-sm text-stone-900 outline-none transition focus:border-[#2f5a45] focus:ring-2 focus:ring-[#2f5a45]/20';

function EyeIcon({ open }: { open: boolean }) {
  if (open) {
    return (
      <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" aria-hidden>
        <path
          d="M3 3l18 18M10.6 10.7a2 2 0 002.8 2.8M9.9 5.2A10.4 10.4 0 0112 5c5 0 9.3 3.1 11 7.5a11.8 11.8 0 01-4.1 5.1M6.1 6.1A11.8 11.8 0 001 12.5C2.7 16.9 7 20 12 20c1.4 0 2.7-.2 3.9-.7"
          stroke="currentColor"
          strokeWidth="1.75"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
    );
  }

  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" aria-hidden>
      <path
        d="M2 12.5C3.7 8.1 8 5 12 5s8.3 3.1 10 7.5c-1.7 4.4-6 7.5-10 7.5S3.7 16.9 2 12.5z"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinejoin="round"
      />
      <circle cx="12" cy="12.5" r="2.5" stroke="currentColor" strokeWidth="1.75" />
    </svg>
  );
}

interface SecurePasswordFieldProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  autoComplete?: string;
  autoFocus?: boolean;
  showRules?: boolean;
  required?: boolean;
  name?: string;
  id?: string;
}

export function SecurePasswordField({
  label,
  value,
  onChange,
  autoComplete = 'new-password',
  autoFocus = false,
  showRules = false,
  required = true,
  name,
  id,
}: SecurePasswordFieldProps) {
  const generatedId = useId();
  const fieldId = id ?? generatedId;
  const [visible, setVisible] = useState(false);
  const rules = evaluatePasswordRules(value);
  const showChecklist = showRules && value.length > 0;

  return (
    <div className="block text-sm">
      <label htmlFor={fieldId} className="font-medium text-stone-700">
        {label}
      </label>
      <div className="relative mt-1">
        <input
          id={fieldId}
          name={name}
          type={visible ? 'text' : 'password'}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className={inputClass}
          required={required}
          minLength={8}
          autoComplete={autoComplete}
          autoFocus={autoFocus}
          spellCheck={false}
        />
        <button
          type="button"
          className="absolute inset-y-0 right-0 flex items-center px-3 text-stone-500 transition hover:text-stone-800"
          onClick={() => setVisible((prev) => !prev)}
          aria-label={visible ? 'Hide password' : 'Show password'}
          aria-pressed={visible}
          tabIndex={0}
        >
          <EyeIcon open={visible} />
        </button>
      </div>
      {showChecklist ? (
        <ul className="mt-2 space-y-1" aria-live="polite">
          {PASSWORD_RULES.map((rule) => {
            const ok = rules[rule.id as PasswordRuleId];
            return (
              <li
                key={rule.id}
                className={`flex items-center gap-2 text-xs ${
                  ok ? 'text-emerald-700' : 'text-stone-500'
                }`}
              >
                <span
                  className={`inline-flex h-3.5 w-3.5 items-center justify-center rounded-full text-[10px] font-bold ${
                    ok ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-400'
                  }`}
                  aria-hidden
                >
                  {ok ? '✓' : '·'}
                </span>
                {rule.label}
              </li>
            );
          })}
        </ul>
      ) : showRules ? (
        <p className="mt-1.5 text-xs text-stone-500">
          Use 8+ characters with upper, lower, number, and a special character.
        </p>
      ) : null}
    </div>
  );
}
