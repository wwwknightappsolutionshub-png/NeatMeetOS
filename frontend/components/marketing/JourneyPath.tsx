'use client';

import { useEffect, useRef, useState } from 'react';

type JourneyStep = {
  title: string;
  body: string;
};

const DWELL_MS = 5000;

export function JourneyPath({ steps }: { steps: readonly JourneyStep[] }) {
  const ref = useRef<HTMLOListElement | null>(null);
  const [active, setActive] = useState(false);
  const [focus, setFocus] = useState(0);
  const count = steps.length;

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    if (typeof IntersectionObserver === 'undefined') {
      setActive(true);
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) {
          setActive(true);
          observer.disconnect();
        }
      },
      { threshold: 0.35 },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    if (!active || count < 2) return;
    const id = window.setInterval(() => {
      setFocus((i) => (i + 1) % count);
    }, DWELL_MS);
    return () => window.clearInterval(id);
  }, [active, count]);

  const pulseLeft =
    count <= 1 ? '50%' : `${((focus + 0.5) / count) * 100}%`;

  return (
    <ol ref={ref} className="relative mt-12">
      {/* Desktop / large progress line + traveling flash that dwells on each step */}
      <div
        className="pointer-events-none absolute left-0 right-0 top-5 hidden h-px overflow-visible xl:block"
        aria-hidden
      >
        <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-[#2f5a45]/20 via-[#2f5a45]/50 to-[#2f5a45]/20" />
        <div
          className={[
            'absolute top-1/2 h-[3px] w-16 -translate-x-1/2 -translate-y-1/2 rounded-full transition-[left] duration-700 ease-in-out',
            active ? 'opacity-100' : 'opacity-0',
          ].join(' ')}
          style={{
            left: pulseLeft,
            background:
              'linear-gradient(90deg, transparent, rgba(47,90,69,0.95), transparent)',
            boxShadow: '0 0 12px 2px rgba(47,90,69,0.45)',
          }}
        />
        <div
          className={[
            'absolute top-1/2 h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#2f5a45] transition-[left] duration-700 ease-in-out',
            active ? 'opacity-100' : 'opacity-0',
          ].join(' ')}
          style={{
            left: pulseLeft,
            boxShadow: '0 0 0 6px rgba(47,90,69,0.2)',
          }}
        />
      </div>
      <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 lg:gap-3">
        {steps.map((step, i) => {
          const lit = active && i === focus;
          return (
            <li
              key={step.title}
              className={[
                'relative nm-journey-step',
                active ? 'nm-journey-step-visible' : '',
              ]
                .filter(Boolean)
                .join(' ')}
              style={{ transitionDelay: active ? `${120 + i * 90}ms` : undefined }}
            >
              <div className="flex items-start gap-3 xl:flex-col xl:items-center xl:text-center">
                <span
                  className={[
                    'relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 font-mono text-xs font-semibold transition duration-500 xl:mx-auto',
                    lit
                      ? 'scale-110 border-[#2f5a45] bg-[#2f5a45] text-white shadow-md shadow-[#2f5a45]/30'
                      : 'border-[#2f5a45] bg-[#f3f1ec] text-[#2f5a45]',
                  ].join(' ')}
                >
                  {String(i + 1).padStart(2, '0')}
                </span>
                <div className="min-w-0 pt-0.5 xl:pt-3">
                  <h3
                    className={[
                      'text-base font-semibold xl:text-sm',
                      lit ? 'text-[#2f5a45]' : 'text-stone-900',
                    ].join(' ')}
                  >
                    {step.title}
                  </h3>
                  <p className="mt-1.5 text-sm leading-relaxed text-stone-600 xl:text-xs">
                    {step.body}
                  </p>
                </div>
              </div>
            </li>
          );
        })}
      </div>
    </ol>
  );
}
