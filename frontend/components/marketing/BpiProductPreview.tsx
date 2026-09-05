'use client';

import { useEffect, useRef, useState } from 'react';

function easeOutCubic(t: number): number {
  return 1 - (1 - t) ** 3;
}

function useInViewOnce<T extends HTMLElement>(rootMargin = '0px 0px -10% 0px') {
  const ref = useRef<T | null>(null);
  const [inView, setInView] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el || inView) return;

    if (typeof IntersectionObserver === 'undefined') {
      setInView(true);
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) {
          setInView(true);
          observer.disconnect();
        }
      },
      { threshold: 0.25, rootMargin },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [inView, rootMargin]);

  return { ref, inView };
}

function useCountUp(target: number, active: boolean, durationMs = 1200): number {
  const [value, setValue] = useState(0);

  useEffect(() => {
    if (!active) {
      setValue(0);
      return;
    }

    let frame = 0;
    const start = performance.now();

    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / durationMs);
      setValue(Math.round(easeOutCubic(t) * target));
      if (t < 1) {
        frame = requestAnimationFrame(tick);
      }
    };

    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [active, target, durationMs]);

  return value;
}

function CountInt({
  to,
  active,
  className,
  durationMs,
}: {
  to: number;
  active: boolean;
  className?: string;
  durationMs?: number;
}) {
  const value = useCountUp(to, active, durationMs);
  return (
    <span className={className} aria-label={String(to)}>
      {value.toLocaleString('en-GB')}
    </span>
  );
}

function CountPercent({
  to,
  active,
  className,
  durationMs,
}: {
  to: number;
  active: boolean;
  className?: string;
  durationMs?: number;
}) {
  const value = useCountUp(to, active, durationMs);
  return (
    <span className={className} aria-label={`${to}%`}>
      {value}%
    </span>
  );
}

function CountMoney({
  to,
  active,
  className,
  durationMs,
}: {
  to: number;
  active: boolean;
  className?: string;
  durationMs?: number;
}) {
  const value = useCountUp(to, active, durationMs);
  return (
    <span className={className} aria-label={`£${to.toLocaleString('en-GB')}`}>
      £{value.toLocaleString('en-GB')}
    </span>
  );
}

/**
 * Marketing preview of Business Performance Intelligence.
 * Mirrors real BPI section labels from the admin product UI.
 * Numbers are illustrative layout only — not live tenant data.
 */
export function BpiProductPreview({
  elevated = false,
  className = '',
}: {
  elevated?: boolean;
  className?: string;
}) {
  const { ref, inView } = useInViewOnce<HTMLDivElement>();
  const barWidth = useCountUp(68, inView, 1100);

  return (
    <div
      ref={ref}
      className={[
        'overflow-hidden rounded-2xl border border-stone-200/90 bg-white',
        elevated
          ? 'shadow-2xl shadow-stone-900/20 ring-1 ring-black/5'
          : 'shadow-xl shadow-stone-900/5',
        className,
      ].join(' ')}
      aria-label="Illustrative preview of Business Performance Intelligence"
    >
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 bg-[#f8f7f4] px-4 py-3 sm:px-5">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
            Business Performance Intelligence
          </p>
          <p className="text-sm font-semibold text-stone-900">Salon overview</p>
        </div>
        <span className="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-amber-900">
          Illustrative preview
        </span>
      </div>

      <div className="space-y-5 p-4 sm:p-5">
        <section>
          <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-500">
            Customers served
          </p>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {[
              ['Today', 42],
              ['This week', 186],
              ['This month', 712],
              ['Returning (MTD)', 418],
            ].map(([label, value]) => (
              <div
                key={String(label)}
                className="rounded-xl border border-stone-100 bg-[#f3f1ec]/70 px-3 py-3"
              >
                <p className="text-[10px] font-medium uppercase tracking-wide text-stone-500">
                  {label}
                </p>
                <p className="mt-1 text-xl font-semibold tabular-nums text-stone-900">
                  <CountInt to={value as number} active={inView} />
                </p>
              </div>
            ))}
          </div>
        </section>

        <section>
          <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-500">
            Customer intelligence
          </p>
          <div className="rounded-xl border border-stone-100 bg-[#f3f1ec]/50 p-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div>
                <p className="text-[10px] font-semibold uppercase tracking-[0.14em] text-stone-500">
                  Customer visibility
                </p>
                <p className="mt-1 text-3xl font-semibold tabular-nums text-[#2f5a45]">
                  <CountPercent to={68} active={inView} />
                </p>
                <p className="mt-1 text-xs text-stone-500">
                  Identified customers you can remessage vs anonymous visits
                </p>
              </div>
              <div className="text-xs text-stone-600">
                <p>
                  Identified:{' '}
                  <span className="font-semibold text-stone-900">
                    <CountInt to={484} active={inView} />
                  </span>
                </p>
                <p>
                  Anonymous:{' '}
                  <span className="font-semibold text-stone-900">
                    <CountInt to={228} active={inView} />
                  </span>
                </p>
              </div>
            </div>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-stone-200">
              <div
                className="h-full rounded-full bg-[#2f5a45] transition-none"
                style={{ width: `${barWidth}%` }}
              />
            </div>
          </div>
          <div className="mt-2 grid grid-cols-3 gap-2">
            <div className="rounded-lg border border-stone-100 px-2.5 py-2.5">
              <p className="text-[10px] text-stone-500">Returning %</p>
              <p className="text-sm font-semibold tabular-nums text-stone-900">
                <CountPercent to={59} active={inView} durationMs={1100} />
              </p>
            </div>
            <div className="rounded-lg border border-stone-100 px-2.5 py-2.5">
              <p className="text-[10px] text-stone-500">First-time %</p>
              <p className="text-sm font-semibold tabular-nums text-stone-900">
                <CountPercent to={41} active={inView} durationMs={1100} />
              </p>
            </div>
            <div className="rounded-lg border border-stone-100 px-2.5 py-2.5">
              <p className="text-[10px] text-stone-500">Unidentified gap</p>
              <p className="text-sm font-semibold tabular-nums text-stone-900">
                <CountInt to={228} active={inView} durationMs={1100} />
              </p>
            </div>
          </div>
        </section>

        <section>
          <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-500">
            Repeat-revenue opportunity
          </p>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div className="rounded-xl border border-stone-100 bg-white px-3 py-3 shadow-sm">
              <p className="text-[10px] font-medium text-stone-500">Due soon</p>
              <p className="mt-1 text-lg font-semibold tabular-nums text-stone-900">
                <CountInt to={63} active={inView} />
              </p>
            </div>
            <div className="rounded-xl border border-stone-100 bg-white px-3 py-3 shadow-sm">
              <p className="text-[10px] font-medium text-stone-500">Overdue / at-risk</p>
              <p className="mt-1 text-lg font-semibold tabular-nums text-stone-900">
                <CountInt to={41} active={inView} />
              </p>
            </div>
            <div className="rounded-xl border border-stone-100 bg-white px-3 py-3 shadow-sm">
              <p className="text-[10px] font-medium text-stone-500">Est. opportunity</p>
              <p className="mt-1 text-lg font-semibold tabular-nums text-stone-900">
                <CountMoney to={2850} active={inView} durationMs={1400} />
              </p>
            </div>
            <div className="rounded-xl border border-stone-100 bg-white px-3 py-3 shadow-sm">
              <p className="text-[10px] font-medium text-stone-500">Joiners not visited</p>
              <p className="mt-1 text-lg font-semibold tabular-nums text-stone-900">
                <CountInt to={17} active={inView} />
              </p>
            </div>
          </div>
          <p className="mt-2 text-[11px] leading-relaxed text-stone-500">
            Estimated opportunity is indicative — based on customer activity patterns, not guaranteed
            lost revenue.
          </p>
        </section>

        <section>
          <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-500">
            Action centre
          </p>
          <ul className="space-y-2">
            <li className="rounded-lg border border-stone-100 bg-[#f8f7f4] px-3 py-2.5 text-xs leading-relaxed text-stone-700">
              <CountInt to={41} active={inView} className="font-semibold tabular-nums" /> customers
              overdue for a return visit — open Marketing to re-engage
            </li>
            <li className="rounded-lg border border-stone-100 bg-[#f8f7f4] px-3 py-2.5 text-xs leading-relaxed text-stone-700">
              <CountInt to={63} active={inView} className="font-semibold tabular-nums" /> customers due
              soon — encourage next-visit booking
            </li>
            <li className="rounded-lg border border-stone-100 bg-[#f8f7f4] px-3 py-2.5 text-xs leading-relaxed text-stone-700">
              <CountInt to={17} active={inView} className="font-semibold tabular-nums" /> CRM joiners
              still waiting for a first appointment
            </li>
          </ul>
        </section>
      </div>
    </div>
  );
}
