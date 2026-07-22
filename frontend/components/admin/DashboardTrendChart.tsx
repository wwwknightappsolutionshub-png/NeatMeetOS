'use client';

import type { BookingDailyRow } from '@/lib/analytics-types';

interface DashboardTrendChartProps {
  daily: BookingDailyRow[];
  title?: string;
}

/**
 * Lightweight SVG dual-series chart (no chart library dependency).
 */
export function DashboardTrendChart({ daily, title = 'Appointments (7 days)' }: DashboardTrendChartProps) {
  const width = 640;
  const height = 220;
  const pad = { top: 24, right: 16, bottom: 36, left: 36 };
  const innerW = width - pad.left - pad.right;
  const innerH = height - pad.top - pad.bottom;

  const points = daily.length > 0 ? daily : [{ date: new Date().toISOString().slice(0, 10), total: 0, completed: 0 }];
  const maxY = Math.max(1, ...points.map((p) => Math.max(p.total, p.completed)));

  function xAt(i: number): number {
    if (points.length === 1) return pad.left + innerW / 2;
    return pad.left + (i / (points.length - 1)) * innerW;
  }

  function yAt(value: number): number {
    return pad.top + innerH - (value / maxY) * innerH;
  }

  function pathFor(key: 'total' | 'completed'): string {
    return points
      .map((p, i) => `${i === 0 ? 'M' : 'L'} ${xAt(i).toFixed(1)} ${yAt(p[key]).toFixed(1)}`)
      .join(' ');
  }

  function areaFor(key: 'total' | 'completed'): string {
    const line = pathFor(key);
    const lastX = xAt(points.length - 1);
    const firstX = xAt(0);
    const base = yAt(0);
    return `${line} L ${lastX.toFixed(1)} ${base.toFixed(1)} L ${firstX.toFixed(1)} ${base.toFixed(1)} Z`;
  }

  return (
    <div className="rounded-2xl border border-zinc-200/80 bg-gradient-to-b from-white to-zinc-50 p-5 shadow-sm">
      <div className="mb-3 flex items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-zinc-800">{title}</h2>
          <p className="text-xs text-zinc-500">Total vs completed volume</p>
        </div>
        <div className="flex items-center gap-3 text-xs text-zinc-600">
          <span className="inline-flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full bg-zinc-800" /> Total
          </span>
          <span className="inline-flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full bg-emerald-600" /> Completed
          </span>
        </div>
      </div>

      <svg viewBox={`0 0 ${width} ${height}`} className="h-52 w-full" role="img" aria-label={title}>
        {[0, 0.25, 0.5, 0.75, 1].map((t) => {
          const y = pad.top + innerH * (1 - t);
          return (
            <g key={t}>
              <line
                x1={pad.left}
                x2={width - pad.right}
                y1={y}
                y2={y}
                stroke="#e4e4e7"
                strokeWidth={1}
              />
              <text x={pad.left - 8} y={y + 3} textAnchor="end" className="fill-zinc-400" fontSize={10}>
                {Math.round(maxY * t)}
              </text>
            </g>
          );
        })}

        <path d={areaFor('total')} fill="rgba(24,24,27,0.06)" />
        <path d={pathFor('total')} fill="none" stroke="#18181b" strokeWidth={2.25} strokeLinejoin="round" />
        <path d={pathFor('completed')} fill="none" stroke="#059669" strokeWidth={2} strokeLinejoin="round" strokeDasharray="4 3" />

        {points.map((p, i) => (
          <g key={p.date}>
            <circle cx={xAt(i)} cy={yAt(p.total)} r={3.5} fill="#18181b" />
            <circle cx={xAt(i)} cy={yAt(p.completed)} r={3} fill="#059669" />
            <text
              x={xAt(i)}
              y={height - 12}
              textAnchor="middle"
              className="fill-zinc-500"
              fontSize={10}
            >
              {p.date.slice(5)}
            </text>
          </g>
        ))}
      </svg>
    </div>
  );
}
