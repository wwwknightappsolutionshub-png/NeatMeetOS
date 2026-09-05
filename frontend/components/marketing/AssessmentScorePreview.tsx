'use client';

import { useEffect, useState } from 'react';

const BARS: Array<{ label: string; value: number }> = [
  { label: 'Customer visibility', value: 58 },
  { label: 'Retention', value: 64 },
  { label: 'Re-engagement', value: 52 },
  { label: 'Revenue visibility', value: 47 },
];

/**
 * Illustrative assessment results sheet — layout only, not a real score.
 */
export function AssessmentScorePreview() {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const id = window.requestAnimationFrame(() => setReady(true));
    return () => window.cancelAnimationFrame(id);
  }, []);

  return (
    <div
      className="relative overflow-hidden rounded-2xl border border-stone-200/90 bg-white p-6 shadow-xl shadow-stone-900/10 sm:p-8"
      aria-label="Illustrative assessment results preview"
    >
      <div className="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-[#2f5a45]/10 blur-2xl" aria-hidden />
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
            Sample results sheet
          </p>
          <p className="mt-1 text-sm text-stone-500">Indicative layout — not your score</p>
        </div>
        <div className="relative flex h-24 w-24 items-center justify-center">
          <svg viewBox="0 0 96 96" className="absolute inset-0 h-full w-full -rotate-90" aria-hidden>
            <circle cx="48" cy="48" r="40" fill="none" stroke="#e7e5e4" strokeWidth="8" />
            <circle
              cx="48"
              cy="48"
              r="40"
              fill="none"
              stroke="#2f5a45"
              strokeWidth="8"
              strokeLinecap="round"
              strokeDasharray={`${2 * Math.PI * 40}`}
              strokeDashoffset={
                ready ? `${2 * Math.PI * 40 * (1 - 0.72)}` : `${2 * Math.PI * 40}`
              }
              className="transition-[stroke-dashoffset] duration-1000 ease-out"
            />
          </svg>
          <div className="text-center">
            <p className="text-2xl font-semibold tabular-nums text-stone-900">72</p>
            <p className="text-[10px] text-stone-500">/100</p>
          </div>
        </div>
      </div>

      <p className="mt-2 text-sm font-semibold text-stone-900">Your Salon Growth Score</p>

      <ul className="mt-6 space-y-4">
        {BARS.map((bar) => (
          <li key={bar.label}>
            <div className="mb-1.5 flex items-center justify-between text-sm">
              <span className="font-medium text-stone-700">{bar.label}</span>
              <span className="font-semibold tabular-nums text-[#2f5a45]">{bar.value}/100</span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-stone-200/80">
              <div
                className="h-full rounded-full bg-[#2f5a45] transition-all duration-1000 ease-out"
                style={{ width: ready ? `${bar.value}%` : '0%' }}
              />
            </div>
          </li>
        ))}
      </ul>

      <div className="mt-6 rounded-xl bg-[#f3f1ec] px-4 py-3">
        <p className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#2f5a45]">
          Estimated repeat-revenue opportunity
        </p>
        <p className="mt-1 text-2xl font-semibold tabular-nums text-stone-900">
          £— <span className="text-sm font-medium text-stone-500">/ month</span>
        </p>
        <p className="mt-1 text-xs leading-relaxed text-stone-500">
          Shown as a blank on purpose — your real estimate appears after the assessment.
        </p>
      </div>
    </div>
  );
}
