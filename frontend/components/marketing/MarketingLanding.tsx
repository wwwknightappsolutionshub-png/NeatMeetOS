'use client';

import dynamic from 'next/dynamic';
import Image from 'next/image';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import { BpiProductPreview } from '@/components/marketing/BpiProductPreview';
import { LandingFaq } from '@/components/marketing/LandingFaq';
import { trackMarketingEvent } from '@/lib/marketing-events';
import { resolveReferralCode } from '@/lib/referral-cookie';
import { optimizeUnsplashUrl } from '@/lib/remote-image';

const ExitIntentTrialModal = dynamic(
  () =>
    import('@/components/marketing/ExitIntentTrialModal').then(
      (m) => m.ExitIntentTrialModal,
    ),
  { ssr: false },
);

const HERO = optimizeUnsplashUrl(
  'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1800&q=75',
  1280,
  68,
);

const SESSION_KEY = 'nm_exit_intent_shown';
const ASSESSMENT_HREF = '/assessment';

const NAV = [
  { href: '#how-it-works', label: 'How it works' },
  { href: '#assessment', label: 'Growth assessment' },
  { href: '#intelligence', label: 'Business intelligence' },
  { href: '#platform', label: 'Platform' },
  { href: '#pricing', label: 'Pricing' },
  { href: '#faqs', label: 'FAQs' },
] as const;

const JOURNEY = [
  {
    title: 'Get customers',
    body: 'Capture bookings, walk-ins and customer interactions in one place.',
  },
  {
    title: 'Know customers',
    body: 'Build a clearer picture of who visits your business — and who you can reach again.',
  },
  {
    title: 'Serve customers',
    body: 'Keep appointments, customer information, payments and operations connected.',
  },
  {
    title: 'Bring them back',
    body: 'Use reminders, rebooking and marketing to encourage the next visit.',
  },
  {
    title: 'Reward loyalty',
    body: 'Build loyalty through memberships, packages, rewards and stronger relationships.',
  },
  {
    title: 'Grow repeat revenue',
    body: 'Use business intelligence to identify opportunities and take action.',
  },
] as const;

const CAPABILITY_CATEGORIES: Array<{
  title: string;
  items: string[];
}> = [
  {
    title: 'Run the day',
    items: ['Booking & scheduling', 'POS & checkout', 'Payments', 'Staff & rota', 'Inventory'],
  },
  {
    title: 'Know your customers',
    items: [
      'Customer CRM',
      'Customer history',
      'Customer notes',
      'Customer visits',
      'Customer segmentation',
    ],
  },
  {
    title: 'Bring customers back',
    items: [
      'Next visit',
      'Automated reminders',
      'Marketing',
      'Loyalty',
      'Memberships',
      'Packages',
    ],
  },
  {
    title: 'Understand your business',
    items: [
      'Business Performance Intelligence',
      'Customer intelligence',
      'Repeat-revenue opportunity',
      'Operational analytics',
      'Reporting',
    ],
  },
  {
    title: 'Build your brand',
    items: ['Gallery', 'Lookbook', 'AI Hairstyle', 'Ecommerce', 'Integrations'],
  },
];

const ASSESSMENT_SCORES = [
  'Customer visibility',
  'Retention',
  'Re-engagement',
  'Revenue visibility',
  'Overall growth',
] as const;

const PRICING = [
  {
    slug: 'basic',
    name: 'Basic',
    price: 59,
    blurb: 'For independent professionals and smaller salons getting organised.',
    outcomes: [
      'Run bookings and day-to-day ops',
      'Capture customers in CRM',
      'Payments and money tracking',
      'Single-location foundation',
    ],
    cta: 'Start with Basic',
    featured: false,
  },
  {
    slug: 'pro',
    name: 'Advanced',
    price: 99,
    blurb: 'For growing salons that want stronger retention, loyalty and business intelligence.',
    outcomes: [
      'Everything in Basic',
      'POS, inventory & memberships',
      'Loyalty and packages',
      'Analytics & Business Performance Intelligence',
      'Stronger follow-up tooling',
    ],
    cta: 'Start with Advanced',
    featured: true,
  },
  {
    slug: 'diamond',
    name: 'Diamond',
    price: 179,
    blurb: 'For established or multi-location businesses that need deeper growth control.',
    outcomes: [
      'Everything in Advanced',
      'Marketing automation',
      'Ecommerce & integrations',
      'Multi-location scale',
      'Full growth operating system',
    ],
    cta: 'Start with Diamond',
    featured: false,
  },
] as const;

function AssessmentLink({
  href = ASSESSMENT_HREF,
  className,
  children,
  eventLabel,
}: {
  href?: string;
  className: string;
  children: ReactNode;
  eventLabel: string;
}) {
  return (
    <Link
      href={href}
      className={className}
      onClick={() =>
        trackMarketingEvent('growth_assessment_cta_clicked', { label: eventLabel })
      }
    >
      {children}
    </Link>
  );
}

function TrialLink({
  href,
  className,
  children,
  eventLabel,
}: {
  href: string;
  className: string;
  children: ReactNode;
  eventLabel: string;
}) {
  return (
    <Link
      href={href}
      className={className}
      onClick={() => trackMarketingEvent('trial_cta_clicked', { label: eventLabel })}
    >
      {children}
    </Link>
  );
}

export function MarketingLanding() {
  const searchParams = useSearchParams();
  const [modalOpen, setModalOpen] = useState(false);
  const [modalSource, setModalSource] = useState<'exit' | 'cta'>('cta');
  const [refCode, setRefCode] = useState<string | null>(null);
  const [stickyVisible, setStickyVisible] = useState(false);
  const [navSolid, setNavSolid] = useState(false);
  const [mobileNav, setMobileNav] = useState(false);
  const modalOpenRef = useRef(false);
  modalOpenRef.current = modalOpen;

  useEffect(() => {
    setRefCode(resolveReferralCode(searchParams.get('ref')));
    trackMarketingEvent('landing_page_view');
  }, [searchParams]);

  const signupHref = `/login?tab=signup${
    refCode ? `&ref=${encodeURIComponent(refCode)}` : ''
  }`;

  const signupWithPlan = useCallback(
    (slug: string) =>
      `${signupHref}${signupHref.includes('?') ? '&' : '?'}plan=${encodeURIComponent(slug)}`,
    [signupHref],
  );

  const openTrial = useCallback((source: 'exit' | 'cta') => {
    setModalSource(source);
    setModalOpen(true);
    setMobileNav(false);
    if (source === 'exit' && typeof sessionStorage !== 'undefined') {
      sessionStorage.setItem(SESSION_KEY, '1');
    }
  }, []);

  useEffect(() => {
    let armed = false;
    let fired = false;
    let scrolledPastHero = false;
    const armTimer = window.setTimeout(() => {
      armed = true;
    }, 1800);

    const tryShowExit = () => {
      if (!armed || fired || modalOpenRef.current) return;
      if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(SESSION_KEY)) {
        return;
      }
      fired = true;
      openTrial('exit');
    };

    const onDocMouseLeave = (e: MouseEvent) => {
      if (e.clientY > 12) return;
      tryShowExit();
    };

    const onMouseOut = (e: MouseEvent) => {
      const next = e.relatedTarget as Node | null;
      if (next && document.documentElement.contains(next)) return;
      if (e.clientY > 12) return;
      tryShowExit();
    };

    const onVisibility = () => {
      if (document.visibilityState !== 'hidden') return;
      if (!scrolledPastHero) return;
      tryShowExit();
    };

    const onScroll = () => {
      setStickyVisible(window.scrollY > window.innerHeight * 0.45);
      setNavSolid(window.scrollY > 40);
      if (window.scrollY > window.innerHeight * 0.35) {
        scrolledPastHero = true;
      }
    };

    const onPageHide = () => {
      if (!scrolledPastHero) return;
      tryShowExit();
    };

    document.documentElement.addEventListener('mouseleave', onDocMouseLeave);
    document.addEventListener('mouseout', onMouseOut);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('pagehide', onPageHide);
    onScroll();

    return () => {
      window.clearTimeout(armTimer);
      document.documentElement.removeEventListener('mouseleave', onDocMouseLeave);
      document.removeEventListener('mouseout', onMouseOut);
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('pagehide', onPageHide);
    };
  }, [openTrial]);

  return (
    <div className="min-h-screen bg-[#f3f1ec] text-stone-900">
      <header
        className={[
          'fixed inset-x-0 top-0 z-40 transition-colors duration-300',
          navSolid
            ? 'border-b border-stone-200/80 bg-[#f3f1ec]/95 backdrop-blur-md'
            : 'bg-transparent',
        ].join(' ')}
      >
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
          <a href="#top" className="shrink-0">
            <NeatMeetLogo
              size={36}
              withWordmark
              variant={navSolid ? 'color' : 'onDark'}
              wordmarkClassName={navSolid ? 'text-stone-900' : 'text-white'}
            />
          </a>

          <nav className="hidden items-center gap-0.5 lg:flex" aria-label="Primary">
            {NAV.map((item) => (
              <a
                key={item.href}
                href={item.href}
                className={[
                  'rounded-lg px-2.5 py-2 text-sm font-medium transition',
                  navSolid
                    ? 'text-stone-600 hover:bg-stone-200/60 hover:text-stone-900'
                    : 'text-white/85 hover:bg-white/10 hover:text-white',
                ].join(' ')}
              >
                {item.label}
              </a>
            ))}
          </nav>

          <div className="flex items-center gap-2">
            <Link
              href="/login"
              className={[
                'hidden rounded-lg px-3 py-2 text-sm font-semibold sm:inline-flex',
                navSolid
                  ? 'text-[#2f5a45] hover:bg-stone-200/70'
                  : 'text-white hover:bg-white/10',
              ].join(' ')}
            >
              Sign in
            </Link>
            <AssessmentLink
              eventLabel="nav_primary"
              className="rounded-lg bg-[#2f5a45] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#264a39]"
            >
              Get free assessment
            </AssessmentLink>
            <button
              type="button"
              className={[
                'inline-flex h-9 w-9 items-center justify-center rounded-lg lg:hidden',
                navSolid ? 'text-stone-800' : 'text-white',
              ].join(' ')}
              aria-label={mobileNav ? 'Close menu' : 'Open menu'}
              aria-expanded={mobileNav}
              onClick={() => setMobileNav((v) => !v)}
            >
              <span className="text-lg leading-none">{mobileNav ? '✕' : '☰'}</span>
            </button>
          </div>
        </div>

        {mobileNav ? (
          <div className="border-t border-stone-200/60 bg-[#f3f1ec] px-5 py-3 lg:hidden">
            {NAV.map((item) => (
              <a
                key={item.href}
                href={item.href}
                onClick={() => setMobileNav(false)}
                className="block rounded-lg px-2 py-2.5 text-sm font-medium text-stone-700"
              >
                {item.label}
              </a>
            ))}
            <Link
              href="/login"
              className="mt-1 block rounded-lg px-2 py-2.5 text-sm font-semibold text-[#2f5a45]"
              onClick={() => setMobileNav(false)}
            >
              Sign in
            </Link>
          </div>
        ) : null}
      </header>

      {/* Hero */}
      <section id="top" className="relative isolate min-h-[100svh] overflow-hidden">
        <Image
          src={HERO}
          alt="Salon floor — NeatMeet OS salon growth operating system"
          fill
          priority
          fetchPriority="high"
          sizes="100vw"
          className="object-cover"
        />
        <div className="absolute inset-0 bg-stone-950/74" />
        <div className="absolute inset-0 bg-gradient-to-b from-stone-950/50 via-stone-950/45 to-stone-950/85" />

        <div className="relative z-10 mx-auto flex min-h-[100svh] max-w-6xl flex-col justify-center gap-10 px-5 pb-24 pt-28 sm:px-8 lg:flex-row lg:items-center lg:gap-12">
          <div className="max-w-xl text-center lg:text-left">
            <NeatMeetLogo size={48} variant="color" className="mx-auto mb-5 shadow-lg shadow-black/30 lg:mx-0" />
            <p className="text-[11px] font-semibold tracking-[0.2em] text-white/80 uppercase sm:text-xs">
              NeatMeet OS — The Salon Growth Operating System
            </p>
            <h1 className="mt-4 text-[2.2rem] font-semibold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-[3.35rem]">
              Grow Your Salon, Not Just Your Bookings.
            </h1>
            <p className="mt-5 text-base leading-relaxed text-white/85 sm:text-lg">
              Most salon owners know how much they made today. Far fewer know how many customers
              came back, who may be due for another visit, which customers are drifting away, or how
              much repeat-revenue opportunity may be sitting inside their existing customer base.
            </p>
            <p className="mt-3 text-sm leading-relaxed text-white/70 sm:text-base">
              NeatMeet OS brings bookings, customers, loyalty, payments, marketing and business
              performance intelligence together in one system built to help you grow.
            </p>
            <div className="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:flex-wrap lg:justify-start">
              <AssessmentLink
                eventLabel="hero_primary"
                className="inline-flex w-full items-center justify-center rounded-lg bg-[#2f5a45] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[#264a39] sm:w-auto"
              >
                Get Your Free Salon Growth Assessment
              </AssessmentLink>
              <TrialLink
                href={signupHref}
                eventLabel="hero_secondary"
                className="inline-flex w-full items-center justify-center rounded-lg border border-white/35 bg-white/10 px-6 py-3.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/20 sm:w-auto"
              >
                Start 30-Day Free Trial
              </TrialLink>
            </div>
            <p className="mt-4 text-sm text-white/70">2–3 minutes · Free · No obligation</p>
            {refCode ? (
              <p className="mt-4 text-sm text-white/75">
                You&apos;re joining via a salon referral — they earn a reward when you activate.
              </p>
            ) : null}
          </div>

          <div className="mx-auto w-full max-w-md shrink-0 lg:mx-0 lg:max-w-lg">
            <div className="origin-top scale-[0.92] sm:scale-100">
              <BpiProductPreview />
            </div>
          </div>
        </div>
      </section>

      {/* Problem */}
      <section id="how-it-works" className="border-b border-stone-200/70 bg-[#f3f1ec] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              The real question
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Do You Really Know How Your Salon Is Performing?
            </h2>
            <p className="mt-4 text-base leading-relaxed text-stone-600">
              You can see today&apos;s sales. You can see tomorrow&apos;s appointments. But do you
              know:
            </p>
          </div>
          <ul className="mx-auto mt-10 grid max-w-4xl gap-3 sm:grid-cols-2">
            {[
              'How many customers visited this month?',
              'How many were first-time customers?',
              'How many came back?',
              'How many haven\'t returned?',
              'Which customers are worth re-engaging?',
              'How many customers can you actually reach?',
              'How much potential repeat revenue could come from customers who return?',
            ].map((q) => (
              <li
                key={q}
                className="rounded-xl border border-stone-200/90 bg-white px-4 py-3.5 text-sm leading-relaxed text-stone-700"
              >
                {q}
              </li>
            ))}
          </ul>
          <p className="mx-auto mt-8 max-w-2xl text-center text-base leading-relaxed text-stone-600">
            Most salon software helps you run the day. NeatMeet helps you understand what happens
            next.
          </p>
          <div className="mt-8 flex justify-center">
            <AssessmentLink
              eventLabel="problem_section"
              className="rounded-lg bg-[#2f5a45] px-5 py-3 text-sm font-semibold text-white hover:bg-[#264a39]"
            >
              Get My Free Growth Assessment
            </AssessmentLink>
          </div>
        </div>
      </section>

      {/* Assessment */}
      <section id="assessment" className="border-b border-stone-200/70 bg-[#ebe8e1] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="grid items-center gap-10 lg:grid-cols-2">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
                Free salon growth assessment
              </p>
              <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
                Find Out Where Your Salon Can Grow
              </h2>
              <p className="mt-4 text-base leading-relaxed text-stone-600">
                Answer a few simple questions about your salon and discover how you currently
                perform across customer visibility, retention, re-engagement and repeat-revenue
                opportunity.
              </p>
              <div className="mt-8 flex flex-wrap items-center gap-3">
                <AssessmentLink
                  eventLabel="assessment_section"
                  className="rounded-lg bg-[#2f5a45] px-5 py-3 text-sm font-semibold text-white hover:bg-[#264a39]"
                >
                  Start My Free Assessment
                </AssessmentLink>
                <span className="text-sm text-stone-500">Free · Takes 2–3 minutes · No obligation</span>
              </div>
            </div>
            <div className="rounded-2xl border border-stone-200/90 bg-white p-6 shadow-sm sm:p-8">
              <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
                What your results include
              </p>
              <ul className="mt-5 space-y-3">
                {ASSESSMENT_SCORES.map((label) => (
                  <li
                    key={label}
                    className="flex items-center justify-between gap-3 rounded-xl border border-stone-100 bg-[#f3f1ec]/80 px-3.5 py-3"
                  >
                    <span className="text-sm font-medium text-stone-800">{label}</span>
                    <span className="font-mono text-xs text-stone-400">/100</span>
                  </li>
                ))}
              </ul>
              <p className="mt-5 text-sm leading-relaxed text-stone-600">
                Your assessment gives you an indicative view of where your salon is performing well
                and where there may be opportunities to improve — including an{' '}
                <strong className="font-semibold text-stone-800">
                  estimated repeat-revenue opportunity
                </strong>
                . It is not guaranteed lost revenue.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* BPI */}
      <section id="intelligence" className="border-b border-stone-200/70 bg-[#f3f1ec] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              Business Performance Intelligence
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              See What Is Really Happening Inside Your Salon
            </h2>
            <p className="mt-4 text-base leading-relaxed text-stone-600">
              NeatMeet OS turns everyday salon activity into practical business intelligence. See
              more than bookings and today&apos;s takings.
            </p>
          </div>

          <div className="mt-12 grid items-start gap-10 lg:grid-cols-[0.95fr_1.05fr]">
            <ul className="space-y-3 text-sm leading-relaxed text-stone-700">
              {[
                'Customers served',
                'Identified vs anonymous customers',
                'Returning customers',
                'Customers who may be due to return',
                'Customers becoming inactive',
                'Loyalty activity',
                'Estimated repeat-revenue opportunity',
                'Business performance',
                'Actions that deserve attention',
              ].map((item) => (
                <li key={item} className="flex gap-3">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#2f5a45]" aria-hidden />
                  {item}
                </li>
              ))}
              <li className="pt-2 text-stone-600">
                The objective is not to overwhelm you with charts. It is to help you answer:{' '}
                <em className="not-italic font-semibold text-stone-900">What should I do next?</em>
              </li>
            </ul>
            <BpiProductPreview />
          </div>
        </div>
      </section>

      {/* Why NeatMeet */}
      <section className="border-b border-stone-200/70 bg-[#ebe8e1] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-3xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              Why NeatMeet OS?
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Your Booking System Runs Appointments. NeatMeet Helps You Grow the Customer
              Relationship.
            </h2>
            <p className="mt-4 text-base leading-relaxed text-stone-600">
              Your existing software may already handle bookings. That&apos;s fine. NeatMeet OS is
              designed to help you understand what happens before, during and after the appointment.
            </p>
          </div>
          <ul className="mx-auto mt-12 grid max-w-4xl gap-3 sm:grid-cols-2">
            {[
              'Know your customers',
              'Understand customer behaviour',
              'Increase repeat visits',
              'Build loyalty',
              'Re-engage customers',
              'Understand business performance',
              'Identify growth opportunities',
              'Turn relationships into repeat business',
            ].map((item) => (
              <li
                key={item}
                className="rounded-xl border border-stone-200/80 bg-white px-4 py-3.5 text-sm font-medium text-stone-800"
              >
                {item}
              </li>
            ))}
          </ul>
          <p className="mx-auto mt-8 max-w-2xl text-center text-sm text-stone-600">
            During setup you can import existing customers from a file — so you are not starting
            from an empty CRM.
          </p>
        </div>
      </section>

      {/* Journey */}
      <section className="border-b border-stone-200/70 bg-[#f3f1ec] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              Customer journey
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              From First Visit to Repeat Revenue
            </h2>
          </div>
          <ol className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {JOURNEY.map((step, i) => (
              <li
                key={step.title}
                className="relative rounded-2xl border border-stone-200/90 bg-white p-5"
              >
                <span className="font-mono text-[11px] font-semibold text-[#2f5a45]">
                  {String(i + 1).padStart(2, '0')}
                </span>
                <h3 className="mt-2 text-lg font-semibold text-stone-900">{step.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-stone-600">{step.body}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      {/* Platform capabilities */}
      <section id="platform" className="border-b border-stone-200/70 bg-[#ebe8e1] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              Platform
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Everything You Need to Run and Grow Your Salon
            </h2>
            <p className="mt-3 text-base text-stone-600">
              Capabilities organised around outcomes — not a module catalogue.
            </p>
          </div>
          <div className="mt-12 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {CAPABILITY_CATEGORIES.map((cat) => (
              <div
                key={cat.title}
                className="rounded-2xl border border-stone-200/90 bg-[#f3f1ec] p-5 sm:p-6"
              >
                <h3 className="text-base font-semibold text-stone-900">{cat.title}</h3>
                <ul className="mt-4 space-y-2">
                  {cat.items.map((item) => (
                    <li key={item} className="text-sm text-stone-600">
                      {item}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Walk-in visibility */}
      <section className="border-b border-stone-200/70 bg-[#f3f1ec] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-3xl text-center">
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
            Every visit counts
          </p>
          <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
            You Shouldn&apos;t Need Every Visitor on an App to Understand the Floor
          </h2>
          <p className="mt-4 text-base leading-relaxed text-stone-600">
            NeatMeet helps salons distinguish overall visits from identifiable customer
            relationships. A visit can contribute to your understanding of customer activity even
            when the visitor has not yet become a fully identified CRM customer. When contact
            details are captured appropriately, that visit can connect to the customer&apos;s history
            and relationship with the salon.
          </p>
        </div>
      </section>

      {/* Retention */}
      <section className="border-b border-stone-200/70 bg-[#ebe8e1] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              The Next Visit Is Where Growth Happens
            </h2>
            <p className="mt-4 text-base leading-relaxed text-stone-600">
              Getting a customer through the door is only the beginning. NeatMeet helps you
              understand what happens after the appointment — and gives your team tools to encourage
              the next visit.
            </p>
          </div>
          <ul className="mx-auto mt-10 grid max-w-4xl gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {[
              'Next-visit booking',
              'Reminders',
              'Customer follow-up',
              'Loyalty',
              'Memberships',
              'Win-back marketing',
              'Customer history',
              'Targeted communication',
            ].map((item) => (
              <li
                key={item}
                className="rounded-xl border border-stone-200/80 bg-white px-4 py-3 text-center text-sm font-medium text-stone-800"
              >
                {item}
              </li>
            ))}
          </ul>
          <div className="mt-10 flex justify-center">
            <AssessmentLink
              eventLabel="retention_section"
              className="rounded-lg bg-[#2f5a45] px-5 py-3 text-sm font-semibold text-white hover:bg-[#264a39]"
            >
              See How NeatMeet Helps Customers Come Back
            </AssessmentLink>
          </div>
        </div>
      </section>

      {/* Action centre */}
      <section className="border-b border-stone-200/70 bg-[#f3f1ec] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              Turn insight into action
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Don&apos;t Just See the Numbers. Know What to Do Next.
            </h2>
            <p className="mt-4 text-base leading-relaxed text-stone-600">
              A useful dashboard should not leave you staring at charts. NeatMeet is designed to
              help you identify customers and business situations that deserve attention.
            </p>
          </div>
          <ul className="mx-auto mt-10 grid max-w-4xl gap-3 sm:grid-cols-2">
            {[
              'Customers to follow up with',
              'Customers due for another visit',
              'Inactive customers',
              'Failed or pending payments',
              'Customers eligible for offers',
              'Loyalty opportunities',
              'Estimated repeat-revenue opportunities',
            ].map((item) => (
              <li
                key={item}
                className="rounded-xl border border-stone-200/90 bg-white px-4 py-3.5 text-sm text-stone-700"
              >
                {item}
              </li>
            ))}
          </ul>
          <div className="mt-10 flex justify-center">
            <AssessmentLink
              eventLabel="action_section"
              className="rounded-lg bg-[#2f5a45] px-5 py-3 text-sm font-semibold text-white hover:bg-[#264a39]"
            >
              Discover Your Growth Opportunities
            </AssessmentLink>
          </div>
        </div>
      </section>

      {/* Imagine your day */}
      <section className="border-b border-stone-200/70 bg-[#ebe8e1] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Run the Salon. Understand the Business. Grow the Customer Base.
            </h2>
          </div>
          <div className="mt-12 grid gap-6 lg:grid-cols-2">
            <div className="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
              <h3 className="text-lg font-semibold text-stone-900">Instead of</h3>
              <ul className="mt-5 space-y-3">
                {[
                  'Juggling disconnected systems',
                  'Guessing which customers will return',
                  'Manually chasing every follow-up',
                  'Relying on spreadsheets',
                  'Trying to understand reports',
                ].map((item) => (
                  <li key={item} className="flex gap-3 text-sm text-stone-600">
                    <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-stone-300" aria-hidden />
                    {item}
                  </li>
                ))}
              </ul>
            </div>
            <div className="rounded-2xl border border-[#2f5a45]/25 bg-[#2f5a45] p-6 text-white sm:p-8">
              <h3 className="text-lg font-semibold">You can</h3>
              <ul className="mt-5 space-y-3">
                {[
                  'Manage bookings',
                  'Serve customers',
                  'Take payments',
                  'Track customer relationships',
                  'Build loyalty',
                  'Automate follow-up',
                  'See business performance',
                  'Identify opportunities',
                  'Take action',
                ].map((item) => (
                  <li key={item} className="flex gap-3 text-sm text-white/90">
                    <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-white/80" aria-hidden />
                    {item}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* Trust */}
      <section className="border-b border-stone-200/70 bg-[#f3f1ec] px-5 py-12 sm:px-8">
        <div className="mx-auto max-w-6xl text-center">
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
            Built for
          </p>
          <p className="mt-3 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm font-medium text-stone-700">
            <span>Hair salons</span>
            <span>Barbershops</span>
            <span>Beauty studios</span>
            <span>Spas</span>
            <span>Multi-location beauty businesses</span>
          </p>
        </div>
      </section>

      {/* Pricing */}
      <section id="pricing" className="border-b border-stone-200/70 bg-[#ebe8e1] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              Pricing
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Simple Pricing. Built to Grow With Your Salon.
            </h2>
            <p className="mt-4 text-base text-stone-600">
              Start with the tools you need today and move up as your salon grows. Billed monthly.
              30-day free trial available.
            </p>
          </div>

          <div className="mt-12 grid gap-5 lg:grid-cols-3">
            {PRICING.map((plan) => (
              <div
                key={plan.slug}
                className={[
                  'relative flex flex-col rounded-2xl border p-6 sm:p-7',
                  plan.featured
                    ? 'border-[#2f5a45] bg-white shadow-lg shadow-[#2f5a45]/10'
                    : 'border-stone-200/90 bg-[#f3f1ec]',
                ].join(' ')}
              >
                {plan.featured ? (
                  <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-[#2f5a45] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">
                    Recommended
                  </span>
                ) : null}
                <h3 className="text-lg font-semibold text-stone-900">{plan.name}</h3>
                <p className="mt-1 text-sm text-stone-600">{plan.blurb}</p>
                <p className="mt-5">
                  <span className="text-4xl font-semibold tabular-nums text-stone-900">
                    £{plan.price}
                  </span>
                  <span className="text-sm text-stone-500"> / month</span>
                </p>
                <ul className="mt-6 flex-1 space-y-2.5">
                  {plan.outcomes.map((item) => (
                    <li key={item} className="flex gap-2 text-sm text-stone-700">
                      <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#2f5a45]" aria-hidden />
                      {item}
                    </li>
                  ))}
                </ul>
                <Link
                  href={signupWithPlan(plan.slug)}
                  className={[
                    'mt-8 inline-flex items-center justify-center rounded-lg px-4 py-3 text-sm font-semibold transition',
                    plan.featured
                      ? 'bg-[#2f5a45] text-white hover:bg-[#264a39]'
                      : 'border border-stone-300 bg-white text-stone-900 hover:bg-stone-50',
                  ].join(' ')}
                  onClick={() =>
                    trackMarketingEvent('pricing_cta_clicked', {
                      plan: plan.slug,
                      label: plan.name,
                    })
                  }
                >
                  {plan.cta}
                </Link>
                <p className="mt-3 text-center text-xs text-stone-500">30-day free trial</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section id="faqs" className="border-b border-stone-200/70 bg-[#f3f1ec] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto mb-10 max-w-2xl text-center">
            <h2 className="text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Frequently Asked Questions
            </h2>
          </div>
          <LandingFaq />
        </div>
      </section>

      {/* Trial */}
      <section id="trial" className="bg-[#2f5a45] px-5 py-16 text-white sm:px-8 sm:py-24">
        <div className="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
          <div>
            <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
              Ready to See What NeatMeet Can Do for Your Salon?
            </h2>
            <p className="mt-4 max-w-xl text-base leading-relaxed text-white/85">
              Start with a free Salon Growth Assessment to see where you stand — or begin your
              30-day free trial and experience one connected system for running your salon,
              understanding your customers and building stronger repeat business.
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <AssessmentLink
                eventLabel="final_assessment"
                className="rounded-lg bg-white px-5 py-3.5 text-sm font-semibold text-[#2f5a45] hover:bg-[#f3f1ec]"
              >
                Get Your Free Growth Assessment
              </AssessmentLink>
              <TrialLink
                href={signupHref}
                eventLabel="final_trial"
                className="rounded-lg border border-white/35 px-5 py-3.5 text-sm font-semibold text-white hover:bg-white/10"
              >
                Start 30-Day Free Trial
              </TrialLink>
            </div>
            <p className="mt-4 text-sm text-white/70">
              No card required to start · Cancel anytime · Import your clients
            </p>
          </div>
          <div className="rounded-2xl border border-white/15 bg-[#264a39]/80 p-6 sm:p-7">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-white/70">
              Conversion path
            </p>
            <ol className="mt-4 space-y-3 text-sm text-white/90">
              <li>1. Assess how your salon is performing</li>
              <li>2. See indicative growth and opportunity scores</li>
              <li>3. Start a trial when you are ready to act</li>
            </ol>
            <p className="mt-6 border-t border-white/15 pt-4 text-[11px] text-white/55">
              Already have login details?{' '}
              <Link href="/login" className="font-semibold text-white underline-offset-2 hover:underline">
                Sign in
              </Link>
            </p>
          </div>
        </div>
      </section>

      <footer className="border-t border-stone-200 bg-[#f3f1ec] px-5 py-12 text-sm text-stone-500 sm:px-8">
        <div className="mx-auto max-w-6xl">
          <div className="flex flex-col gap-8 lg:flex-row lg:justify-between">
            <div>
              <NeatMeetLogo size={28} withWordmark variant="color" wordmarkClassName="text-stone-700" />
              <p className="mt-3 max-w-xs text-sm text-stone-600">
                Salon Growth Operating System — get customers, know customers, serve them, bring
                them back, reward loyalty, grow repeat revenue.
              </p>
            </div>
            <div className="grid gap-8 sm:grid-cols-2">
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-stone-400">
                  Navigate
                </p>
                <ul className="mt-3 space-y-2">
                  {NAV.map((item) => (
                    <li key={item.href}>
                      <a href={item.href} className="hover:text-stone-800">
                        {item.label}
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-stone-400">
                  Account
                </p>
                <ul className="mt-3 space-y-2">
                  <li>
                    <Link href="/login" className="font-medium text-[#2f5a45]">
                      Sign in
                    </Link>
                  </li>
                  <li>
                    <Link href={signupHref} className="hover:text-stone-800">
                      Start free trial
                    </Link>
                  </li>
                  <li>
                    <Link href={ASSESSMENT_HREF} className="hover:text-stone-800">
                      Growth assessment
                    </Link>
                  </li>
                  <li>
                    <Link href="/terms" className="hover:text-stone-800">
                      Terms &amp; Conditions
                    </Link>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <p className="mt-10 border-t border-stone-200 pt-6 text-xs text-stone-400">
            © {new Date().getFullYear()} NeatMeet OS. All rights reserved.
          </p>
        </div>
      </footer>

      {stickyVisible && !modalOpen ? (
        <div className="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-[#f3f1ec]/95 p-3 backdrop-blur sm:hidden">
          <AssessmentLink
            eventLabel="sticky_mobile"
            className="flex w-full items-center justify-center rounded-lg bg-[#2f5a45] py-3 text-center text-sm font-semibold text-white"
          >
            Get Free Growth Assessment
          </AssessmentLink>
        </div>
      ) : null}

      {modalOpen ? (
        <ExitIntentTrialModal
          open={modalOpen}
          onClose={() => setModalOpen(false)}
          referralCode={refCode}
          source={modalSource}
        />
      ) : null}
    </div>
  );
}
