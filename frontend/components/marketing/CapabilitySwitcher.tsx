'use client';

import { useState } from 'react';

type Category = {
  title: string;
  items: string[];
};

export function CapabilitySwitcher({
  categories,
}: {
  categories: Category[];
}) {
  const [active, setActive] = useState(0);
  const current = categories[active] ?? categories[0];

  return (
    <div className="mt-12 overflow-hidden rounded-2xl border border-stone-200/90 bg-white shadow-sm">
      <div
        className="flex justify-center gap-1 overflow-x-auto border-b border-stone-100 bg-[#f8f7f4] p-2 sm:flex-wrap sm:justify-center"
        role="tablist"
        aria-label="Platform capability categories"
      >
        {categories.map((cat, i) => {
          const selected = i === active;
          return (
            <button
              key={cat.title}
              type="button"
              role="tab"
              aria-selected={selected}
              id={`cap-tab-${i}`}
              aria-controls={`cap-panel-${i}`}
              className={[
                'shrink-0 rounded-lg px-3 py-2 text-center text-sm font-medium transition',
                selected
                  ? 'bg-[#2f5a45] text-white shadow-sm'
                  : 'text-stone-600 hover:bg-white hover:text-stone-900',
              ].join(' ')}
              onClick={() => setActive(i)}
            >
              {cat.title}
            </button>
          );
        })}
      </div>
      <div
        id={`cap-panel-${active}`}
        role="tabpanel"
        aria-labelledby={`cap-tab-${active}`}
        className="grid gap-8 p-6 sm:grid-cols-[0.9fr_1.1fr] sm:p-8"
      >
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
            Outcome
          </p>
          <h3 className="mt-2 text-2xl font-semibold tracking-tight text-stone-900">
            {current.title}
          </h3>
          <p className="mt-3 text-sm leading-relaxed text-stone-600">
            Capabilities that support this outcome — selected from the live NeatMeet platform.
          </p>
        </div>
        <ul className="columns-1 gap-x-8 sm:columns-2">
          {current.items.map((item) => (
            <li
              key={item}
              className="mb-3 break-inside-avoid border-l-2 border-[#2f5a45]/35 pl-3 text-sm font-medium text-stone-800"
            >
              {item}
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
