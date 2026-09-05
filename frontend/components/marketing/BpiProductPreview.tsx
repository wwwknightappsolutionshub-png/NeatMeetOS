/**
 * Marketing preview of Business Performance Intelligence.
 * Mirrors real BPI section labels from the admin product UI.
 * Numbers are illustrative layout only — not live tenant data.
 */
export function BpiProductPreview() {
  return (
    <div
      className="overflow-hidden rounded-2xl border border-stone-200/90 bg-white shadow-xl shadow-stone-900/5"
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
              ['Today', '42'],
              ['This week', '186'],
              ['This month', '712'],
              ['Returning (MTD)', '418'],
            ].map(([label, value]) => (
              <div
                key={label}
                className="rounded-xl border border-stone-100 bg-[#f3f1ec]/70 px-3 py-3"
              >
                <p className="text-[10px] font-medium uppercase tracking-wide text-stone-500">
                  {label}
                </p>
                <p className="mt-1 text-xl font-semibold tabular-nums text-stone-900">{value}</p>
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
                <p className="mt-1 text-3xl font-semibold tabular-nums text-[#2f5a45]">68%</p>
                <p className="mt-1 text-xs text-stone-500">
                  Identified customers you can remessage vs anonymous visits
                </p>
              </div>
              <div className="text-xs text-stone-600">
                <p>
                  Identified: <span className="font-semibold text-stone-900">484</span>
                </p>
                <p>
                  Anonymous: <span className="font-semibold text-stone-900">228</span>
                </p>
              </div>
            </div>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-stone-200">
              <div className="h-full w-[68%] rounded-full bg-[#2f5a45]" />
            </div>
          </div>
          <div className="mt-2 grid grid-cols-3 gap-2">
            {[
              ['Returning %', '59%'],
              ['First-time %', '41%'],
              ['Unidentified gap', '228'],
            ].map(([label, value]) => (
              <div key={label} className="rounded-lg border border-stone-100 px-2.5 py-2.5">
                <p className="text-[10px] text-stone-500">{label}</p>
                <p className="text-sm font-semibold tabular-nums text-stone-900">{value}</p>
              </div>
            ))}
          </div>
        </section>

        <section>
          <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-500">
            Repeat-revenue opportunity
          </p>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {[
              ['Due soon', '63'],
              ['Overdue / at-risk', '41'],
              ['Est. opportunity', '£2,850'],
              ['Joiners not visited', '17'],
            ].map(([label, value]) => (
              <div
                key={label}
                className="rounded-xl border border-stone-100 bg-white px-3 py-3 shadow-sm"
              >
                <p className="text-[10px] font-medium text-stone-500">{label}</p>
                <p className="mt-1 text-lg font-semibold tabular-nums text-stone-900">{value}</p>
              </div>
            ))}
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
            {[
              '41 customers overdue for a return visit — open Marketing to re-engage',
              '63 customers due soon — encourage next-visit booking',
              '17 CRM joiners still waiting for a first appointment',
            ].map((item) => (
              <li
                key={item}
                className="rounded-lg border border-stone-100 bg-[#f8f7f4] px-3 py-2.5 text-xs leading-relaxed text-stone-700"
              >
                {item}
              </li>
            ))}
          </ul>
        </section>
      </div>
    </div>
  );
}
