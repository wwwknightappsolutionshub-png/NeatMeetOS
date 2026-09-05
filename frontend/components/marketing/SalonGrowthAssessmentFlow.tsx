'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useEffect, useMemo, useState } from 'react';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import { TurnstileFormGate } from '@/components/security/TurnstileBootstrap';
import { useTurnstileReady } from '@/hooks/useTurnstileReady';
import { resolveReferralCode } from '@/lib/referral-cookie';
import type {
  SalonGrowthAssessmentAnswers,
  SalonGrowthAssessmentResult,
} from '@/lib/growth-assessment-types';
import {
  fetchSalonGrowthAssessmentResult,
  requestSalonGrowthAssessmentWhatsApp,
  submitSalonGrowthAssessment,
} from '@/services/growth-assessment.service';

type StepId =
  | 'business'
  | 'visibility'
  | 'returners'
  | 'tracking'
  | 'due'
  | 'retention'
  | 'encourage'
  | 'spend'
  | 'missed'
  | 'software'
  | 'software_detail'
  | 'contact'
  | 'results';

const STEPS: StepId[] = [
  'business',
  'visibility',
  'returners',
  'tracking',
  'due',
  'retention',
  'encourage',
  'spend',
  'missed',
  'software',
  'software_detail',
  'contact',
  'results',
];

function optionBtn(
  selected: boolean,
  className = '',
): string {
  return [
    'w-full rounded-xl border px-4 py-3.5 text-left text-sm font-medium transition',
    selected
      ? 'border-[#2f5a45] bg-[#2f5a45] text-white shadow-sm'
      : 'border-stone-200 bg-white text-stone-800 hover:border-[#2f5a45]/50 hover:bg-[#f3f1ec]',
    className,
  ].join(' ');
}

function ScoreBar({ label, value }: { label: string; value: number }) {
  return (
    <div>
      <div className="mb-1.5 flex items-center justify-between text-sm">
        <span className="font-medium text-stone-700">{label}</span>
        <span className="font-semibold tabular-nums text-[#2f5a45]">{value}/100</span>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-stone-200/80">
        <div
          className="h-full rounded-full bg-[#2f5a45] transition-all duration-700 ease-out"
          style={{ width: `${Math.max(4, Math.min(100, value))}%` }}
        />
      </div>
    </div>
  );
}

export function SalonGrowthAssessmentFlow() {
  const searchParams = useSearchParams();
  const turnstileReady = useTurnstileReady();
  const [stepIndex, setStepIndex] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<SalonGrowthAssessmentResult | null>(null);
  const [whatsappBusy, setWhatsappBusy] = useState(false);
  const [whatsappNote, setWhatsappNote] = useState<string | null>(null);

  const [businessName, setBusinessName] = useState('');
  const [businessType, setBusinessType] = useState('');
  const [staffBand, setStaffBand] = useState('');
  const [customersBand, setCustomersBand] = useState('');
  const [answers, setAnswers] = useState<Partial<SalonGrowthAssessmentAnswers>>({
    encourage_return_methods: [],
    software_helps_with: [],
  });
  const [contactName, setContactName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [postcode, setPostcode] = useState('');
  const [consent, setConsent] = useState(false);
  const [sendWhatsApp, setSendWhatsApp] = useState(false);
  const [hpTrap, setHpTrap] = useState('');

  const step = STEPS[stepIndex] ?? 'business';

  const visibleSteps = useMemo(() => {
    return STEPS.filter((id) => {
      if (id === 'software_detail' && answers.uses_software !== 'yes') return false;
      if (id === 'results') return false;
      return true;
    });
  }, [answers.uses_software]);

  const progress = useMemo(() => {
    const idx = visibleSteps.indexOf(step === 'results' ? 'contact' : step);
    if (step === 'results') return 100;
    if (idx < 0) return 0;
    return Math.round(((idx + 1) / visibleSteps.length) * 100);
  }, [step, visibleSteps]);

  useEffect(() => {
    const token = searchParams.get('token');
    if (!token) return;
    void (async () => {
      try {
        const data = await fetchSalonGrowthAssessmentResult(token);
        setResult(data);
        setStepIndex(STEPS.indexOf('results'));
      } catch {
        /* ignore invalid token */
      }
    })();
  }, [searchParams]);

  function setAnswer<K extends keyof SalonGrowthAssessmentAnswers>(
    key: K,
    value: SalonGrowthAssessmentAnswers[K],
  ) {
    setAnswers((prev) => ({ ...prev, [key]: value }));
  }

  function toggleMulti(key: 'encourage_return_methods' | 'software_helps_with', value: string) {
    setAnswers((prev) => {
      const current = [...(prev[key] ?? [])];
      const i = current.indexOf(value);
      if (i >= 0) current.splice(i, 1);
      else current.push(value);
      if (key === 'encourage_return_methods' && value === 'nothing') {
        return { ...prev, [key]: current.includes('nothing') ? ['nothing'] : current };
      }
      if (key === 'encourage_return_methods') {
        return { ...prev, [key]: current.filter((v) => v !== 'nothing') };
      }
      return { ...prev, [key]: current };
    });
  }

  function goNext() {
    setError(null);
    if (step === 'business') {
      if (!businessName.trim() || !businessType || !customersBand) {
        setError('Please complete your business details to continue.');
        return;
      }
    }
    if (step === 'visibility' && !answers.knows_last_month_visitors) {
      setError('Please choose an answer.');
      return;
    }
    if (step === 'returners' && !answers.knows_how_many_returned) {
      setError('Please choose an answer.');
      return;
    }
    if (step === 'tracking' && !answers.tracking_method) {
      setError('Please choose an answer.');
      return;
    }
    if (step === 'due' && !answers.knows_when_due_return) {
      setError('Please choose an answer.');
      return;
    }
    if (step === 'retention' && !answers.return_percentage_band) {
      setError('Please choose an answer.');
      return;
    }
    if (step === 'encourage' && !(answers.encourage_return_methods?.length)) {
      setError('Select at least one option.');
      return;
    }
    if (step === 'spend' && !answers.avg_spend_band) {
      setError('Please choose an answer.');
      return;
    }
    if (step === 'missed' && !answers.knows_missed_revenue) {
      setError('Please choose an answer.');
      return;
    }
    if (step === 'software' && !answers.uses_software) {
      setError('Please choose an answer.');
      return;
    }
    if (step === 'software_detail') {
      if (!(answers.software_helps_with?.length) || !answers.software_satisfaction) {
        setError('Please complete both questions.');
        return;
      }
    }
    if (step === 'contact') {
      void submit();
      return;
    }

    let next = stepIndex + 1;
    if (STEPS[next] === 'software_detail' && answers.uses_software !== 'yes') {
      next += 1;
    }
    setStepIndex(next);
  }

  function goBack() {
    setError(null);
    if (stepIndex <= 0 || step === 'results') return;
    let prev = stepIndex - 1;
    if (STEPS[prev] === 'software_detail' && answers.uses_software !== 'yes') {
      prev -= 1;
    }
    setStepIndex(Math.max(0, prev));
  }

  async function submit() {
    if (!contactName.trim() || !email.trim() || !phone.trim()) {
      setError('Name, email and mobile are required.');
      return;
    }
    if (!consent) {
      setError('Please confirm you are happy for NeatMeet to contact you about this assessment.');
      return;
    }
    if (!turnstileReady) {
      setError('Please complete the security check before submitting.');
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      const ref = resolveReferralCode(searchParams.get('ref'));
      const payloadAnswers: SalonGrowthAssessmentAnswers = {
        knows_last_month_visitors: answers.knows_last_month_visitors!,
        knows_how_many_returned: answers.knows_how_many_returned!,
        tracking_method: answers.tracking_method!,
        knows_when_due_return: answers.knows_when_due_return!,
        return_percentage_band: answers.return_percentage_band!,
        encourage_return_methods: answers.encourage_return_methods ?? [],
        avg_spend_band: answers.avg_spend_band!,
        knows_missed_revenue: answers.knows_missed_revenue!,
        uses_software: answers.uses_software!,
        software_helps_with: answers.software_helps_with,
        software_satisfaction: answers.software_satisfaction,
        staff_band: staffBand || undefined,
        customers_per_month_band: customersBand,
        business_type: businessType,
        business_name: businessName.trim(),
      };

      const data = await submitSalonGrowthAssessment({
        business_name: businessName.trim(),
        business_type: businessType,
        staff_band: staffBand || undefined,
        customers_per_month_band: customersBand,
        contact_name: contactName.trim(),
        email: email.trim(),
        phone: phone.trim(),
        postcode: postcode.trim() || undefined,
        marketing_consent: consent,
        send_whatsapp: sendWhatsApp,
        source: 'landing',
        referral_code: ref,
        hp_trap: hpTrap,
        answers: payloadAnswers,
      });
      setResult(data);
      setStepIndex(STEPS.indexOf('results'));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unable to complete assessment.');
    } finally {
      setSubmitting(false);
    }
  }

  async function sendWhatsAppAgain() {
    if (!result?.public_token) return;
    setWhatsappBusy(true);
    setWhatsappNote(null);
    try {
      const res = await requestSalonGrowthAssessmentWhatsApp(result.public_token);
      setResult((prev) =>
        prev
          ? { ...prev, whatsapp_delivery_status: res.whatsapp_delivery_status }
          : prev,
      );
      if (res.whatsapp_delivery_status === 'sent') {
        setWhatsappNote('Sent to WhatsApp.');
      } else {
        setWhatsappNote(
          res.whatsapp_delivery_error ||
            'WhatsApp could not be delivered right now. Your email copy is still on its way.',
        );
      }
    } catch (e) {
      setWhatsappNote(e instanceof Error ? e.message : 'WhatsApp request failed.');
    } finally {
      setWhatsappBusy(false);
    }
  }

  return (
    <div className="min-h-screen bg-[#f3f1ec] text-stone-900">
      <header className="border-b border-stone-200/80 bg-[#f3f1ec]/95 backdrop-blur-md">
        <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
          <Link href="/">
            <NeatMeetLogo size={34} withWordmark variant="color" />
          </Link>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
            Growth assessment
          </p>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-5 py-10 sm:px-8 sm:py-14">
        {step !== 'results' ? (
          <div className="mb-8">
            <div className="mb-2 flex items-center justify-between text-xs text-stone-500">
              <span>About 2–3 minutes</span>
              <span className="tabular-nums">{progress}%</span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-stone-200">
              <div
                className="h-full rounded-full bg-[#2f5a45] transition-all duration-500"
                style={{ width: `${progress}%` }}
              />
            </div>
          </div>
        ) : null}

        <div className="rounded-2xl border border-stone-200/90 bg-white p-6 shadow-sm sm:p-9">
          {step === 'business' ? (
            <div className="space-y-5 nm-assess-step">
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
                About your business
              </p>
              <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                Let&apos;s start with the basics
              </h1>
              <p className="text-sm leading-relaxed text-stone-600">
                This is a business diagnostic — not a sales form. Your answers shape an
                indicative growth score and repeat-revenue opportunity.
              </p>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold text-stone-700">Business name</span>
                <input
                  className="w-full rounded-xl border border-stone-200 px-3.5 py-3 outline-none focus:border-[#2f5a45]"
                  value={businessName}
                  onChange={(e) => setBusinessName(e.target.value)}
                  placeholder="e.g. Northside Barber Co."
                />
              </label>
              <div>
                <p className="mb-2 text-sm font-semibold text-stone-700">Business type</p>
                <div className="grid gap-2 sm:grid-cols-2">
                  {[
                    ['hair_salon', 'Hair Salon'],
                    ['barber_shop', 'Barber Shop'],
                    ['beauty_salon', 'Beauty Salon'],
                    ['spa', 'Spa'],
                    ['other', 'Other'],
                  ].map(([v, label]) => (
                    <button
                      key={v}
                      type="button"
                      className={optionBtn(businessType === v)}
                      onClick={() => setBusinessType(v)}
                    >
                      {label}
                    </button>
                  ))}
                </div>
              </div>
              <div>
                <p className="mb-2 text-sm font-semibold text-stone-700">Number of staff</p>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                  {[
                    ['1', 'Just me'],
                    ['2_3', '2–3'],
                    ['4_8', '4–8'],
                    ['9_15', '9–15'],
                    ['16_plus', '16+'],
                  ].map(([v, label]) => (
                    <button
                      key={v}
                      type="button"
                      className={optionBtn(staffBand === v)}
                      onClick={() => setStaffBand(v)}
                    >
                      {label}
                    </button>
                  ))}
                </div>
              </div>
              <div>
                <p className="mb-2 text-sm font-semibold text-stone-700">
                  Approximately how many customers does your business serve each month?
                </p>
                <div className="grid gap-2 sm:grid-cols-2">
                  {[
                    ['0_100', '0–100'],
                    ['101_250', '101–250'],
                    ['251_500', '251–500'],
                    ['501_1000', '501–1,000'],
                    ['1000_plus', '1,000+'],
                  ].map(([v, label]) => (
                    <button
                      key={v}
                      type="button"
                      className={optionBtn(customersBand === v)}
                      onClick={() => setCustomersBand(v)}
                    >
                      {label}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          ) : null}

          {step === 'visibility' ? (
            <ChoiceStep
              eyebrow="Customer return behaviour"
              title="Do you currently know exactly how many customers visited your business last month?"
              options={[
                ['yes_exactly', 'Yes — I know exactly'],
                ['approximately', 'Approximately'],
                ['no', 'No'],
              ]}
              value={answers.knows_last_month_visitors}
              onPick={(v) => setAnswer('knows_last_month_visitors', v)}
            />
          ) : null}

          {step === 'returners' ? (
            <ChoiceStep
              eyebrow="Customer return behaviour"
              title="Do you know how many of those customers came back?"
              options={[
                ['yes', 'Yes'],
                ['approximately', 'Approximately'],
                ['no', 'No'],
              ]}
              value={answers.knows_how_many_returned}
              onPick={(v) => setAnswer('knows_how_many_returned', v)}
            />
          ) : null}

          {step === 'tracking' ? (
            <ChoiceStep
              eyebrow="Customer return behaviour"
              title="How do you currently keep track of customers?"
              options={[
                ['booking_software', 'Booking software'],
                ['spreadsheet', 'Spreadsheet'],
                ['notebook', 'Notebook / manual records'],
                ['crm', 'CRM'],
                ['loyalty_system', 'Loyalty system'],
                ['nothing', 'Nothing consistently'],
                ['other', 'Other'],
              ]}
              value={answers.tracking_method}
              onPick={(v) => setAnswer('tracking_method', v)}
            />
          ) : null}

          {step === 'due' ? (
            <ChoiceStep
              eyebrow="Customer return behaviour"
              title="After a customer's visit, do you normally know when they are due to return?"
              options={[
                ['always', 'Always'],
                ['sometimes', 'Sometimes'],
                ['rarely', 'Rarely'],
                ['never', 'Never'],
              ]}
              value={answers.knows_when_due_return}
              onPick={(v) => setAnswer('knows_when_due_return', v)}
            />
          ) : null}

          {step === 'retention' ? (
            <ChoiceStep
              eyebrow="Customer retention"
              title="What percentage of your customers would you say return regularly?"
              options={[
                ['under_20', 'Under 20%'],
                ['20_40', '20–40%'],
                ['41_60', '41–60%'],
                ['61_80', '61–80%'],
                ['over_80', 'Over 80%'],
                ['not_sure', "I'm not sure"],
              ]}
              value={answers.return_percentage_band}
              onPick={(v) => setAnswer('return_percentage_band', v)}
            />
          ) : null}

          {step === 'encourage' ? (
            <div className="space-y-5">
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
                Customer retention
              </p>
              <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                How do you currently encourage customers to return?
              </h1>
              <p className="text-sm text-stone-600">Select all that apply.</p>
              <div className="grid gap-2 sm:grid-cols-2">
                {[
                  ['loyalty_rewards', 'Loyalty / rewards'],
                  ['sms', 'SMS'],
                  ['whatsapp', 'WhatsApp'],
                  ['email', 'Email'],
                  ['phone_calls', 'Phone calls'],
                  ['next_appointment', 'Next appointment booking'],
                  ['discounts', 'Discounts / offers'],
                  ['nothing', 'Nothing systematic'],
                ].map(([v, label]) => (
                  <button
                    key={v}
                    type="button"
                    className={optionBtn(!!answers.encourage_return_methods?.includes(v))}
                    onClick={() => toggleMulti('encourage_return_methods', v)}
                  >
                    {label}
                  </button>
                ))}
              </div>
            </div>
          ) : null}

          {step === 'spend' ? (
            <ChoiceStep
              eyebrow="Revenue"
              title="What is your average customer spend per visit?"
              options={[
                ['under_20', 'Under £20'],
                ['20_40', '£20–£40'],
                ['41_60', '£41–£60'],
                ['61_80', '£61–£80'],
                ['81_100', '£81–£100'],
                ['100_plus', '£100+'],
                ['not_sure', "I'm not sure"],
              ]}
              value={answers.avg_spend_band}
              onPick={(v) => setAnswer('avg_spend_band', v)}
            />
          ) : null}

          {step === 'missed' ? (
            <ChoiceStep
              eyebrow="Revenue"
              title="If a customer doesn't return when expected, do you currently know how much potential revenue that represents?"
              options={[
                ['yes', 'Yes'],
                ['no', 'No'],
                ['not_sure', "I'm not sure"],
              ]}
              value={answers.knows_missed_revenue}
              onPick={(v) => setAnswer('knows_missed_revenue', v)}
            />
          ) : null}

          {step === 'software' ? (
            <ChoiceStep
              eyebrow="Current software"
              title="Do you currently use software to manage your business?"
              options={[
                ['yes', 'Yes'],
                ['no', 'No'],
              ]}
              value={answers.uses_software}
              onPick={(v) => setAnswer('uses_software', v)}
            />
          ) : null}

          {step === 'software_detail' ? (
            <div className="space-y-6">
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
                Current software
              </p>
              <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                What does it mainly help you with?
              </h1>
              <div className="grid gap-2 sm:grid-cols-2">
                {[
                  ['booking', 'Booking'],
                  ['payments_pos', 'Payments / POS'],
                  ['customer_records', 'Customer records'],
                  ['loyalty', 'Loyalty'],
                  ['marketing', 'Marketing'],
                  ['reporting', 'Reporting'],
                  ['all_of_the_above', 'All of the above'],
                  ['other', 'Other'],
                ].map(([v, label]) => (
                  <button
                    key={v}
                    type="button"
                    className={optionBtn(!!answers.software_helps_with?.includes(v))}
                    onClick={() => toggleMulti('software_helps_with', v)}
                  >
                    {label}
                  </button>
                ))}
              </div>
              <div>
                <h2 className="mb-3 text-lg font-semibold">
                  Are you satisfied with how well your current system helps you bring customers
                  back?
                </h2>
                <div className="grid gap-2">
                  {[
                    ['very_satisfied', 'Very satisfied'],
                    ['satisfied', 'Satisfied'],
                    ['neutral', 'Neutral'],
                    ['not_very_satisfied', 'Not very satisfied'],
                    ['not_at_all', 'Not at all'],
                  ].map(([v, label]) => (
                    <button
                      key={v}
                      type="button"
                      className={optionBtn(answers.software_satisfaction === v)}
                      onClick={() => setAnswer('software_satisfaction', v)}
                    >
                      {label}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          ) : null}

          {step === 'contact' ? (
            <div className="space-y-5">
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
                Your results
              </p>
              <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                Where should we send your assessment?
              </h1>
              <p className="text-sm leading-relaxed text-stone-600">
                You&apos;ve answered the diagnostic. Enter your details to unlock your Salon Growth
                Score, indicative opportunity, and a copy by email.
              </p>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold">Your name</span>
                <input
                  className="w-full rounded-xl border border-stone-200 px-3.5 py-3 outline-none focus:border-[#2f5a45]"
                  value={contactName}
                  onChange={(e) => setContactName(e.target.value)}
                  autoComplete="name"
                />
              </label>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold">Email</span>
                <input
                  type="email"
                  className="w-full rounded-xl border border-stone-200 px-3.5 py-3 outline-none focus:border-[#2f5a45]"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  autoComplete="email"
                />
              </label>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold">Mobile / WhatsApp</span>
                <input
                  className="w-full rounded-xl border border-stone-200 px-3.5 py-3 outline-none focus:border-[#2f5a45]"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  autoComplete="tel"
                  placeholder="07…"
                />
              </label>
              <label className="block text-sm">
                <span className="mb-1.5 block font-semibold">
                  Postcode <span className="font-normal text-stone-500">(optional)</span>
                </span>
                <input
                  className="w-full rounded-xl border border-stone-200 px-3.5 py-3 outline-none focus:border-[#2f5a45]"
                  value={postcode}
                  onChange={(e) => setPostcode(e.target.value)}
                  autoComplete="postal-code"
                />
              </label>
              <label className="flex items-start gap-3 text-sm text-stone-600">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={consent}
                  onChange={(e) => setConsent(e.target.checked)}
                />
                <span>
                  I agree NeatMeet may email me this assessment and contact me about how the
                  platform can help my business.
                </span>
              </label>
              <label className="flex items-start gap-3 text-sm text-stone-600">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={sendWhatsApp}
                  onChange={(e) => setSendWhatsApp(e.target.checked)}
                />
                <span>Also send my assessment to WhatsApp</span>
              </label>
              <input
                type="text"
                name="website"
                tabIndex={-1}
                autoComplete="off"
                className="absolute -left-[9999px] h-0 w-0 opacity-0"
                value={hpTrap}
                onChange={(e) => setHpTrap(e.target.value)}
                aria-hidden
              />
              <TurnstileFormGate className="mt-2" size="compact" />
            </div>
          ) : null}

          {step === 'results' && result ? (
            <div className="space-y-8">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
                  Your salon growth assessment
                </p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                  {result.business_name}
                </h1>
                <p className="mt-2 text-sm text-stone-500">{result.indicative_note}</p>
              </div>

              <div className="rounded-2xl bg-[#2f5a45] px-6 py-8 text-center text-white">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">
                  Your salon growth score
                </p>
                <p className="mt-3 text-5xl font-semibold tabular-nums sm:text-6xl">
                  {result.score_overall}
                  <span className="text-2xl font-medium text-white/70"> / 100</span>
                </p>
              </div>

              <div className="space-y-4">
                <ScoreBar label="Customer Visibility" value={result.score_visibility} />
                <ScoreBar label="Retention" value={result.score_retention} />
                <ScoreBar label="Revenue Visibility" value={result.score_revenue_visibility} />
                <ScoreBar label="Re-engagement" value={result.score_reengagement} />
              </div>

              <div className="rounded-xl border border-stone-200 bg-[#f3f1ec] p-5">
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
                  Potential repeat-revenue opportunity
                </p>
                <p className="mt-2 text-3xl font-semibold tabular-nums text-stone-900">
                  {result.estimated_opportunity_display}
                  <span className="text-base font-medium text-stone-500"> / month</span>
                </p>
                <p className="mt-2 text-sm leading-relaxed text-stone-600">
                  {result.estimate_disclaimer}
                </p>
              </div>

              <div>
                <h2 className="text-lg font-semibold">Where your biggest opportunity is</h2>
                <p className="mt-1 text-base font-medium text-[#2f5a45]">
                  {result.primary_opportunity_label}
                </p>
                <p className="mt-2 text-sm leading-relaxed text-stone-600">
                  {result.opportunity_narrative}
                </p>
              </div>

              <div>
                <h2 className="text-lg font-semibold">What NeatMeet can help you do</h2>
                <ul className="mt-3 space-y-2">
                  {result.neatmeet_capabilities.map((item) => (
                    <li
                      key={item}
                      className="flex gap-2 text-sm text-stone-700 before:mt-2 before:h-1.5 before:w-1.5 before:shrink-0 before:rounded-full before:bg-[#2f5a45] before:content-['']"
                    >
                      {item}
                    </li>
                  ))}
                </ul>
              </div>

              <div className="rounded-xl border border-dashed border-stone-300 bg-white p-5">
                <p className="text-sm font-medium text-stone-800">
                  You&apos;ve seen the opportunity. NeatMeet helps turn that visibility into
                  action.
                </p>
                <div className="mt-4 flex flex-wrap gap-3">
                  <Link
                    href="/#product"
                    className="rounded-lg bg-[#2f5a45] px-5 py-3 text-sm font-semibold text-white hover:bg-[#264a39]"
                  >
                    See how NeatMeet can help
                  </Link>
                  <Link
                    href="/login?tab=signup"
                    className="rounded-lg border border-stone-300 bg-white px-5 py-3 text-sm font-semibold text-stone-800 hover:bg-stone-50"
                  >
                    Book a conversation / start trial
                  </Link>
                </div>
                <button
                  type="button"
                  disabled={whatsappBusy}
                  onClick={() => void sendWhatsAppAgain()}
                  className="mt-4 text-sm font-semibold text-[#2f5a45] underline-offset-2 hover:underline disabled:opacity-60"
                >
                  {whatsappBusy ? 'Sending…' : 'Send my assessment to WhatsApp'}
                </button>
                {whatsappNote ? (
                  <p className="mt-2 text-xs text-stone-500">{whatsappNote}</p>
                ) : null}
                <p className="mt-3 text-xs text-stone-500">
                  Email status: {result.email_delivery_status}
                  {result.whatsapp_delivery_status !== 'not_requested'
                    ? ` · WhatsApp: ${result.whatsapp_delivery_status}`
                    : ''}
                </p>
              </div>
            </div>
          ) : null}

          {error ? (
            <p className="mt-5 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {error}
            </p>
          ) : null}

          {step !== 'results' ? (
            <div className="mt-8 flex items-center justify-between gap-3">
              <button
                type="button"
                onClick={goBack}
                disabled={stepIndex === 0 || submitting}
                className="rounded-lg px-4 py-2.5 text-sm font-semibold text-stone-600 hover:bg-stone-100 disabled:opacity-40"
              >
                Back
              </button>
              <button
                type="button"
                onClick={goNext}
                disabled={submitting || (step === 'contact' && !turnstileReady)}
                className="rounded-lg bg-[#2f5a45] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#264a39] disabled:opacity-60"
              >
                {submitting
                  ? 'Calculating…'
                  : step === 'contact'
                    ? 'See my assessment'
                    : 'Continue'}
              </button>
            </div>
          ) : null}
        </div>

        <p className="mt-8 text-center text-xs text-stone-500">
          Get customers → Know customers → Serve → Bring them back → Reward loyalty → Grow
          repeat revenue
        </p>
      </main>
    </div>
  );
}

function ChoiceStep({
  eyebrow,
  title,
  options,
  value,
  onPick,
}: {
  eyebrow: string;
  title: string;
  options: Array<[string, string]>;
  value?: string;
  onPick: (v: string) => void;
}) {
  return (
    <div className="space-y-5">
      <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
        {eyebrow}
      </p>
      <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{title}</h1>
      <div className="grid gap-2">
        {options.map(([v, label]) => (
          <button
            key={v}
            type="button"
            className={optionBtn(value === v)}
            onClick={() => onPick(v)}
          >
            {label}
          </button>
        ))}
      </div>
    </div>
  );
}
