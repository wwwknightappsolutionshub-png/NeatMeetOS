type JourneyStep = {
  title: string;
  body: string;
};

export function JourneyPath({ steps }: { steps: readonly JourneyStep[] }) {
  return (
    <ol className="relative mt-12">
      {/* Desktop progress line */}
      <div
        className="pointer-events-none absolute left-0 right-0 top-5 hidden h-px bg-gradient-to-r from-[#2f5a45]/20 via-[#2f5a45]/50 to-[#2f5a45]/20 lg:block"
        aria-hidden
      />
      <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-6 lg:gap-3">
        {steps.map((step, i) => (
          <li key={step.title} className="relative">
            <div className="flex items-start gap-3 lg:flex-col lg:items-center lg:text-center">
              <span className="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-[#2f5a45] bg-[#f3f1ec] font-mono text-xs font-semibold text-[#2f5a45] lg:mx-auto">
                {String(i + 1).padStart(2, '0')}
              </span>
              <div className="min-w-0 pt-0.5 lg:pt-3">
                <h3 className="text-base font-semibold text-stone-900 lg:text-sm">{step.title}</h3>
                <p className="mt-1.5 text-sm leading-relaxed text-stone-600 lg:text-xs">
                  {step.body}
                </p>
              </div>
            </div>
          </li>
        ))}
      </div>
    </ol>
  );
}
