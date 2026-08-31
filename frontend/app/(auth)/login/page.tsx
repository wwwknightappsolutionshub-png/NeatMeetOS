'use client';

import dynamic from 'next/dynamic';
import Image from 'next/image';
import { useRouter, useSearchParams } from 'next/navigation';
import {
  Suspense,
  useEffect,
  useMemo,
  useRef,
  useState,
  type FormEvent,
} from 'react';
import { TurnstileWidget } from '@/components/security/TurnstileWidget';
import { useTurnstileReady } from '@/hooks/useTurnstileReady';
import { Button } from '@/components/ui/Button';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import {
  buildInitialServiceDrafts,
  matchesBusinessType,
  UPGRADE_TOAST,
} from '@/components/auth/SignupServiceCatalogue';
import { normalizeClockValue } from '@/components/auth/ClockTimeField';
import { resolveReferralCode } from '@/lib/referral-cookie';
import { optimizeUnsplashUrl } from '@/lib/remote-image';
import { Toast, useToast } from '@/components/ui/Toast';
import type {
  SignupForm,
  SignupFormField,
  SignupPlan,
  SignupServiceDraft,
} from '@/lib/types';
import { isPasswordSecure, passwordSecurityMessage } from '@/lib/password-rules';
import {
  activateAccount,
  consumeMagicLink,
  login,
  requestMagicLink,
  requestPasswordReset,
  resetPassword,
} from '@/services/auth.service';
import {
  fetchSignupForm,
  registerSignup,
  completeWorkspaceSignup,
  selectedServicesPayload,
  type AddressSuggestion,
} from '@/services/signup.service';

const SignupServiceCatalogue = dynamic(
  () =>
    import('@/components/auth/SignupServiceCatalogue').then((m) => m.SignupServiceCatalogue),
  { loading: () => <p className="text-sm text-stone-500">Loading services…</p> },
);
const PostcodeAddressField = dynamic(
  () =>
    import('@/components/auth/PostcodeAddressField').then((m) => m.PostcodeAddressField),
);
const ClockTimeField = dynamic(
  () => import('@/components/auth/ClockTimeField').then((m) => m.ClockTimeField),
);
const SecurePasswordField = dynamic(
  () =>
    import('@/components/auth/SecurePasswordField').then((m) => m.SecurePasswordField),
);

type AuthTab = 'login' | 'signup';
type LoginMode = 'password' | 'magic' | 'forgot';

const HERO_LOGIN = optimizeUnsplashUrl(
  'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1600&q=80',
  1200,
  70,
);
const HERO_SIGNUP = optimizeUnsplashUrl(
  'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1600&q=80',
  1200,
  70,
);

const inputClass =
  'mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 outline-none transition focus:border-[#2f5a45] focus:ring-2 focus:ring-[#2f5a45]/20';

function fieldLabel(field: SignupFormField): string {
  const alreadyOptional = /\(\s*optional\s*\)/i.test(field.label);
  if (field.required || alreadyOptional) return field.label;
  return `${field.label} (optional)`;
}

function slugifyBookingUrl(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80);
}

const tabClass = (active: boolean) =>
  [
    'flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition',
    active
      ? 'bg-[#2f5a45] text-white shadow-sm'
      : 'bg-transparent text-stone-500 hover:bg-stone-100 hover:text-stone-800',
  ].join(' ');

function formatPrice(cents: number): string {
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    maximumFractionDigits: 0,
  }).format(cents / 100);
}

function postLoginRedirect(
  router: ReturnType<typeof useRouter>,
  isPlatformAdmin?: boolean,
  nextPath?: string | null,
) {
  const safeNext =
    nextPath && nextPath.startsWith('/') && !nextPath.startsWith('//')
      ? nextPath
      : null;
  router.replace(safeNext ?? (isPlatformAdmin ? '/platform' : '/admin/dashboard'));
}

function LoginAuthPage() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const magicToken = searchParams.get('magic');
  const activateToken = searchParams.get('activate');
  const resetToken = searchParams.get('reset');
  const nextPath = searchParams.get('next');
  const noticeFromQuery = searchParams.get('notice');
  const referralCode = resolveReferralCode(searchParams.get('ref')) ?? '';
  const forceSignupOnly = searchParams.get('tab') === 'signup';
  const wantSignup = forceSignupOnly || Boolean(referralCode);
  const emailFromQuery = (searchParams.get('email') ?? '').trim();

  const [tab, setTab] = useState<AuthTab>(
    activateToken || resetToken ? 'login' : wantSignup ? 'signup' : 'login',
  );
  const [loginMode, setLoginMode] = useState<LoginMode>('password');
  const [email, setEmail] = useState(emailFromQuery);
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(
    noticeFromQuery === 'password-updated'
      ? 'Password updated. You can sign in now.'
      : null,
  );
  const [loading, setLoading] = useState(false);
  const [magicConsuming, setMagicConsuming] = useState(Boolean(magicToken));
  const [workspaceOnboarding, setWorkspaceOnboarding] = useState(false);
  const [creatingPassword, setCreatingPassword] = useState(false);
  const magicConsumeAttemptedFor = useRef<string | null>(null);

  const [signupForm, setSignupForm] = useState<SignupForm | null>(null);
  const [signupLoading, setSignupLoading] = useState(false);
  const [signupStep, setSignupStep] = useState(0);
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [signupDone, setSignupDone] = useState<string | null>(null);
  const [slugAuto, setSlugAuto] = useState(true);
  const [serviceDrafts, setServiceDrafts] = useState<SignupServiceDraft[]>([]);
  const { message: toastMessage, showToast, dismissToast } = useToast();
  const turnstileReady = useTurnstileReady();
  const basicMaxServices = signupForm?.basic_max_services ?? 4;

  useEffect(() => {
    if (emailFromQuery) setEmail(emailFromQuery);
  }, [emailFromQuery]);

  useEffect(() => {
    if (noticeFromQuery === 'password-updated') {
      setNotice('Password updated. You can sign in now.');
      setLoginMode('password');
      setTab('login');
    }
  }, [noticeFromQuery]);

  useEffect(() => {
    magicConsumeAttemptedFor.current = null;
  }, [magicToken]);

  // Magic-link consume needs a mounted Turnstile widget (backend requires the token).
  // Wait until the check is ready; do not hide the widget during this step.
  useEffect(() => {
    if (!magicToken) return;
    if (!turnstileReady) return;
    if (magicConsumeAttemptedFor.current === magicToken) return;
    magicConsumeAttemptedFor.current = magicToken;

    setMagicConsuming(true);
    setError(null);
    void consumeMagicLink(magicToken)
      .then((data) => {
        if (data.workspace_incomplete) {
          setMagicConsuming(false);
          const parts = data.user.name.trim().split(/\s+/);
          setWorkspaceOnboarding(true);
          setTab('signup');
          setEmail(data.user.email);
          setAnswers((prev) => ({
            ...prev,
            owner_email: data.user.email,
            contact_email: prev.contact_email || data.user.email,
            owner_first_name: prev.owner_first_name || parts[0] || '',
            owner_last_name: prev.owner_last_name || parts.slice(1).join(' ') || '',
          }));
          setNotice('Welcome — finish Creating Your Workspace to start your trial.');
          return;
        }
        postLoginRedirect(router, data.user.is_platform_admin, nextPath);
      })
      .catch((e) => {
        setMagicConsuming(false);
        setError(e instanceof Error ? e.message : 'Magic link failed');
        router.replace('/login');
      });
    // No cleanup cancel: getTurnstileToken() resets the widget after read, which
    // flips turnstileReady false and would discard a successful consume response.
  }, [magicToken, router, turnstileReady, nextPath]);

  const awaitingTempPassword =
    forceSignupOnly && Boolean(emailFromQuery) && !workspaceOnboarding;

  useEffect(() => {
    // Defer form load until the multi-step wizard is actually shown — avoids
    // surfacing /signup/form errors on the temporary-password gate.
    if (tab !== 'signup' || signupForm || awaitingTempPassword) return;
    setSignupLoading(true);
    void fetchSignupForm()
      .then((form) => {
        setSignupForm(form);
        setAnswers((prev) => ({
          desired_plan_slug: form.default_plan_slug,
          timezone: 'Europe/London',
          country: 'GB',
          ...prev,
        }));
        setServiceDrafts(
          buildInitialServiceDrafts(
            form.service_catalogue ?? [],
            undefined,
            form.basic_max_services ?? 4,
          ),
        );
      })
      .catch((e) => {
        setError(e instanceof Error ? e.message : 'Could not load signup form');
      })
      .finally(() => setSignupLoading(false));
  }, [tab, signupForm, awaitingTempPassword]);

  const steps = signupForm?.steps ?? [];
  const currentStep = steps[signupStep];
  const isLastStep = signupStep === steps.length - 1;

  const specialMode = useMemo(() => {
    if (activateToken) return 'activate' as const;
    if (resetToken) return 'reset' as const;
    if (magicConsuming) return 'magic-consume' as const;
    return null;
  }, [activateToken, resetToken, magicConsuming]);

  async function handlePasswordLogin(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setNotice(null);
    try {
      const data = await login(email, password);
      if (data.workspace_incomplete) {
        const parts = data.user.name.trim().split(/\s+/);
        const first = parts[0] ?? '';
        const last = parts.slice(1).join(' ') || '';
        setWorkspaceOnboarding(true);
        setTab('signup');
        setAnswers((prev) => ({
          ...prev,
          owner_email: data.user.email,
          contact_email: prev.contact_email || data.user.email,
          owner_first_name: prev.owner_first_name || first,
          owner_last_name: prev.owner_last_name || last,
        }));
        setNotice('Welcome — finish Creating Your Workspace to start your trial.');
        setPassword('');
        return;
      }
      postLoginRedirect(router, data.user.is_platform_admin, nextPath);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  }

  async function handleMagicRequest(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setNotice(null);
    try {
      await requestMagicLink(email);
      setNotice('If an account exists for that email, a magic link has been sent.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not send magic link');
    } finally {
      setLoading(false);
    }
  }

  async function handleForgotRequest(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setNotice(null);
    try {
      await requestPasswordReset(email);
      setNotice(
        'If an account exists for that email, a reset link has been sent by email and WhatsApp when a registered phone number is on file.',
      );
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not send reset link');
    } finally {
      setLoading(false);
    }
  }

  async function handleActivate(e: FormEvent) {
    e.preventDefault();
    if (!activateToken) return;
    if (!isPasswordSecure(password)) {
      setError(passwordSecurityMessage(password) ?? 'Password is not strong enough.');
      return;
    }
    if (password !== passwordConfirm) {
      setError('Passwords do not match.');
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const data = await activateAccount(activateToken, password, passwordConfirm);
      postLoginRedirect(router, data.user.is_platform_admin, nextPath);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Activation failed');
    } finally {
      setLoading(false);
    }
  }

  async function handleReset(e: FormEvent) {
    e.preventDefault();
    if (!resetToken) return;
    if (!isPasswordSecure(password)) {
      setError(passwordSecurityMessage(password) ?? 'Password is not strong enough.');
      return;
    }
    if (password !== passwordConfirm) {
      setError('Passwords do not match.');
      return;
    }
    setLoading(true);
    setError(null);
    setNotice(null);
    try {
      await resetPassword(resetToken, password, passwordConfirm);
      setPassword('');
      setPasswordConfirm('');
      setLoginMode('password');
      setTab('login');
      // Drop ?reset= so specialMode leaves the reset form; keep success on login.
      router.replace('/login?notice=password-updated');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Reset failed');
    } finally {
      setLoading(false);
    }
  }

  function applyAddressSuggestion(suggestion: AddressSuggestion) {
    setAnswers((prev) => ({
      ...prev,
      address_line1: suggestion.address_line1,
      city: suggestion.city,
      postcode: suggestion.postcode,
      country: suggestion.country || prev.country || 'GB',
    }));
  }

  function setAnswer(key: string, value: string) {
    if (workspaceOnboarding && key === 'owner_email') {
      return;
    }
    if (key === 'slug') {
      const fromName = slugifyBookingUrl(answers.business_name ?? '');
      setSlugAuto(value.trim() === '' || value === fromName);
      setAnswers((prev) => ({ ...prev, slug: value }));
      return;
    }

    if (key === 'business_name') {
      setAnswers((prev) => {
        const next: Record<string, string> = { ...prev, business_name: value };
        if (slugAuto) {
          next.slug = slugifyBookingUrl(value);
        }
        return next;
      });
      return;
    }

    if (key === 'business_type') {
      setAnswers((prev) => ({ ...prev, business_type: value }));
      if (signupForm?.service_catalogue) {
        setServiceDrafts((prev) =>
          buildInitialServiceDrafts(
            signupForm.service_catalogue ?? [],
            value,
            signupForm.basic_max_services ?? 4,
            prev,
          ),
        );
      }
      return;
    }

    setAnswers((prev) => ({ ...prev, [key]: value }));
  }

  function validateStep(): string | null {
    if (!currentStep) return 'No step available';
    for (const field of currentStep.fields) {
      if (field.type === 'service_catalogue') {
        const businessType = answers.business_type ?? '';
        const selected = serviceDrafts.filter(
          (d) =>
            d.selected &&
            (d.is_custom ||
              matchesBusinessType(d.business_types, businessType)),
        );
        if (selected.length === 0) {
          return 'Select at least one service to offer';
        }
        if (selected.some((d) => d.is_custom && !d.name.trim())) {
          return 'Give each custom service a name';
        }
        if (selected.length > basicMaxServices) {
          return UPGRADE_TOAST;
        }
        continue;
      }
      if (!field.required) continue;
      const value = (answers[field.key] ?? '').trim();
      if (!value) return `${field.label} is required`;
    }
    return null;
  }

  async function handleSignupNext(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setNotice(null);
    const validationError = validateStep();
    if (validationError) {
      setError(validationError);
      return;
    }
    if (!isLastStep) {
      setSignupStep((s) => s + 1);
      return;
    }

    // Provisional funnel: Finish workspace → create permanent password first.
    if (workspaceOnboarding) {
      setCreatingPassword(true);
      setPassword('');
      setPasswordConfirm('');
      return;
    }

    setLoading(true);
    try {
      const payload = {
        ...answers,
        opening_time: normalizeClockValue(answers.opening_time ?? ''),
        closing_time: normalizeClockValue(answers.closing_time ?? ''),
        ...(referralCode ? { referral_code: referralCode } : {}),
        services: selectedServicesPayload(
          serviceDrafts.filter(
            (d) =>
              d.is_custom ||
              matchesBusinessType(d.business_types, answers.business_type ?? ''),
          ),
        ),
      };

      const result = await registerSignup(payload);
      setSignupDone(
        result.message ||
          'Check your email to activate your account and start your Basic trial.',
      );
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration failed');
    } finally {
      setLoading(false);
    }
  }

  async function handlePermanentPasswordSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setNotice(null);
    if (!isPasswordSecure(password)) {
      setError(passwordSecurityMessage(password) ?? 'Password is not strong enough.');
      return;
    }
    if (password !== passwordConfirm) {
      setError('Passwords do not match.');
      return;
    }
    setLoading(true);
    try {
      const payload = {
        ...answers,
        opening_time: normalizeClockValue(answers.opening_time ?? ''),
        closing_time: normalizeClockValue(answers.closing_time ?? ''),
        ...(referralCode ? { referral_code: referralCode } : {}),
        owner_email: email || answers.owner_email,
        services: selectedServicesPayload(
          serviceDrafts.filter(
            (d) =>
              d.is_custom ||
              matchesBusinessType(d.business_types, answers.business_type ?? ''),
          ),
        ),
      };
      const data = await completeWorkspaceSignup(
        payload,
        password,
        passwordConfirm,
      );
      postLoginRedirect(router, data.user.is_platform_admin, nextPath);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not finish workspace');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen bg-[#f7f5f2] text-stone-900">
      <Toast message={toastMessage} onDismiss={dismissToast} />
      {/* LEFT — auth forms */}
      <div className="flex w-full flex-col justify-center px-6 py-10 sm:px-10 lg:w-[46%] lg:px-14 xl:px-16">
        <div className="mx-auto w-full max-w-md">
          <div className="mb-6">
            <NeatMeetLogo size={40} withWordmark variant="color" />
          </div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
            {tab === 'signup' && !specialMode ? 'Get started' : 'Welcome'}
          </p>
          <h1 className="mt-2 text-3xl font-semibold tracking-tight text-stone-900">
            {specialMode === 'activate'
              ? 'Activate your salon'
              : specialMode === 'reset'
                ? 'Set a new password'
                : specialMode === 'magic-consume'
                  ? 'Signing you in…'
                  : tab === 'signup'
                    ? 'Creating Your Workspace'
                    : 'Sign in to your workspace'}
          </h1>
          <p className="mt-2 text-sm text-stone-500">
            {specialMode === 'activate'
              ? 'Choose a password to finish setup and start your trial.'
              : specialMode === 'reset'
                ? 'Enter a new password for your NeatMeet OS account.'
                : specialMode === 'magic-consume'
                  ? turnstileReady
                    ? 'Verifying your magic link…'
                    : 'Complete the security check below to finish signing in.'
                : workspaceOnboarding
                  ? creatingPassword
                    ? 'Choose a permanent password — your temporary unlock password will stop working.'
                    : 'Your trial account is ready — complete these steps to provision your salon.'
                  : tab === 'signup'
                  ? 'Tell us about your salon — we will set up booking, services, and your team in a few steps.'
                  : 'Staff access for salon teams and platform admins.'}
          </p>

          {!specialMode ? (
            <div className="mt-6 flex gap-1 rounded-xl border border-stone-200 bg-stone-100/80 p-1">
              <button
                type="button"
                className={`${tabClass(tab === 'login')} ${forceSignupOnly || workspaceOnboarding ? 'cursor-not-allowed opacity-45 hover:bg-transparent hover:text-stone-500' : ''}`}
                disabled={forceSignupOnly || workspaceOnboarding}
                onClick={() => {
                  if (forceSignupOnly || workspaceOnboarding) return;
                  setTab('login');
                  setError(null);
                  setNotice(null);
                }}
              >
                Login
              </button>
              <button
                type="button"
                className={tabClass(tab === 'signup')}
                onClick={() => {
                  setTab('signup');
                  setError(null);
                  setNotice(null);
                }}
              >
                Sign up
              </button>
            </div>
          ) : null}

          {error ? (
            <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {error}
            </p>
          ) : null}
          {notice ? (
            <p className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
              {notice}
            </p>
          ) : null}

          {!signupDone ? <TurnstileWidget className="mt-4" /> : null}

          {specialMode === 'magic-consume' ? (
            <p className="mt-8 text-sm text-stone-500">
              {turnstileReady
                ? 'Verifying your magic link…'
                : 'Waiting for the security check…'}
            </p>
          ) : null}

          {specialMode === 'activate' ? (
            <form onSubmit={handleActivate} className="mt-6 space-y-4">
              <SecurePasswordField
                label="Password"
                value={password}
                onChange={setPassword}
                showRules
                autoComplete="new-password"
              />
              <SecurePasswordField
                label="Confirm password"
                value={passwordConfirm}
                onChange={setPasswordConfirm}
                autoComplete="new-password"
              />
              {passwordConfirm.length > 0 && password !== passwordConfirm ? (
                <p className="text-xs text-red-600">Passwords do not match.</p>
              ) : null}
              <Button
                type="submit"
                disabled={
                  loading ||
                  !turnstileReady ||
                  !isPasswordSecure(password) ||
                  password !== passwordConfirm
                }
                className="w-full !bg-[#2f5a45]"
              >
                {loading ? 'Activating…' : 'Activate & continue'}
              </Button>
            </form>
          ) : null}

          {specialMode === 'reset' ? (
            <form onSubmit={handleReset} className="mt-6 space-y-4">
              <SecurePasswordField
                label="New password"
                value={password}
                onChange={setPassword}
                showRules
                autoComplete="new-password"
              />
              <SecurePasswordField
                label="Confirm password"
                value={passwordConfirm}
                onChange={setPasswordConfirm}
                autoComplete="new-password"
              />
              {passwordConfirm.length > 0 && password !== passwordConfirm ? (
                <p className="text-xs text-red-600">Passwords do not match.</p>
              ) : null}
              <Button
                type="submit"
                disabled={
                  loading ||
                  !turnstileReady ||
                  !isPasswordSecure(password) ||
                  password !== passwordConfirm
                }
                className="w-full !bg-[#2f5a45]"
              >
                {loading ? 'Saving…' : 'Update password'}
              </Button>
            </form>
          ) : null}

          {!specialMode && tab === 'login' ? (
            <div className="mt-6">
              <div className="mb-4 flex gap-3 text-xs font-medium">
                {(
                  [
                    ['password', 'Password'],
                    ['magic', 'Magic link'],
                    ['forgot', 'Forgot'],
                  ] as const
                ).map(([mode, label]) => (
                  <button
                    key={mode}
                    type="button"
                    onClick={() => {
                      setLoginMode(mode);
                      setError(null);
                      setNotice(null);
                    }}
                    className={
                      loginMode === mode
                        ? 'text-[#2f5a45] underline decoration-2 underline-offset-4'
                        : 'text-stone-400 hover:text-stone-700'
                    }
                  >
                    {label}
                  </button>
                ))}
              </div>

              {loginMode === 'password' ? (
                <form onSubmit={handlePasswordLogin} className="space-y-4">
                  <label className="block text-sm">
                    <span className="font-medium text-stone-700">Email</span>
                    <input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      className={inputClass}
                      required
                      autoComplete="email"
                    />
                  </label>
                  <label className="block text-sm">
                    <span className="font-medium text-stone-700">Password</span>
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className={inputClass}
                      required
                      autoComplete="current-password"
                    />
                  </label>
                  <Button
                    type="submit"
                    disabled={loading || !turnstileReady}
                    className="w-full !bg-[#2f5a45]"
                  >
                    {loading ? 'Signing in…' : 'Sign in'}
                  </Button>
                </form>
              ) : null}

              {loginMode === 'magic' ? (
                <form onSubmit={handleMagicRequest} className="space-y-4">
                  <label className="block text-sm">
                    <span className="font-medium text-stone-700">Email</span>
                    <input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      className={inputClass}
                      required
                      autoComplete="email"
                    />
                  </label>
                  <Button
                    type="submit"
                    disabled={loading || !turnstileReady}
                    className="w-full !bg-[#2f5a45]"
                  >
                    {loading ? 'Sending…' : 'Email me a magic link'}
                  </Button>
                </form>
              ) : null}

              {loginMode === 'forgot' ? (
                <form onSubmit={handleForgotRequest} className="space-y-4">
                  <label className="block text-sm">
                    <span className="font-medium text-stone-700">Email</span>
                    <input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      className={inputClass}
                      required
                      autoComplete="email"
                    />
                  </label>
                  <Button
                    type="submit"
                    disabled={loading || !turnstileReady}
                    className="w-full !bg-[#2f5a45]"
                  >
                    {loading ? 'Sending…' : 'Send reset link'}
                  </Button>
                </form>
              ) : null}

            </div>
          ) : null}

          {!specialMode && tab === 'signup' ? (
            <div className="mt-6">
              {forceSignupOnly && emailFromQuery && !workspaceOnboarding ? (
                <form onSubmit={handlePasswordLogin} className="space-y-4">
                  <p className="text-sm text-stone-600">
                    Enter the temporary unlock password from your email to open Creating Your
                    Workspace. You will choose your own permanent password at the end.
                  </p>
                  <label className="block text-sm">
                    <span className="font-medium text-stone-700">Email</span>
                    <input
                      type="email"
                      value={email}
                      readOnly
                      className={`${inputClass} bg-stone-50 text-stone-600`}
                      autoComplete="username"
                    />
                  </label>
                  <label className="block text-sm">
                    <span className="font-medium text-stone-700">Temporary unlock password</span>
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className={inputClass}
                      required
                      minLength={8}
                      autoComplete="current-password"
                      autoFocus
                    />
                  </label>
                  <Button type="submit" disabled={loading} className="w-full !bg-[#2f5a45]">
                    {loading ? 'Continuing…' : 'Continue'}
                  </Button>
                </form>
              ) : signupDone ? (
                <div className="rounded-xl border border-[#2f5a45]/25 bg-[#e8f0eb] px-4 py-5">
                  <p className="text-sm font-semibold text-[#2f5a45]">You&apos;re almost in</p>
                  <p className="mt-2 text-sm text-stone-700">{signupDone}</p>
                  <Button
                    type="button"
                    className="mt-4 !bg-[#2f5a45]"
                    onClick={() => {
                      setTab('login');
                      setLoginMode('password');
                    }}
                    disabled={forceSignupOnly}
                  >
                    Back to login
                  </Button>
                </div>
              ) : creatingPassword && workspaceOnboarding ? (
                <form onSubmit={handlePermanentPasswordSubmit} className="space-y-4">
                  <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-400">
                    Final step
                  </p>
                  <h2 className="mt-1 text-lg font-semibold text-stone-900">
                    Create new password
                  </h2>
                  <p className="mt-1 text-sm text-stone-500">
                    This becomes your permanent login with{' '}
                    <span className="font-medium text-stone-700">{email}</span>. Your temporary
                    unlock password stops working as soon as you save.
                  </p>
                  <SecurePasswordField
                    label="Create new password"
                    value={password}
                    onChange={setPassword}
                    showRules
                    autoComplete="new-password"
                    autoFocus
                  />
                  <SecurePasswordField
                    label="Confirm password"
                    value={passwordConfirm}
                    onChange={setPasswordConfirm}
                    autoComplete="new-password"
                  />
                  {passwordConfirm.length > 0 && password !== passwordConfirm ? (
                    <p className="text-xs text-red-600">Passwords do not match.</p>
                  ) : null}
                  <div className="flex gap-2 pt-2">
                    <Button
                      type="button"
                      variant="secondary"
                      onClick={() => {
                        setError(null);
                        setCreatingPassword(false);
                        setPassword('');
                        setPasswordConfirm('');
                      }}
                      disabled={loading}
                    >
                      Back
                    </Button>
                    <Button
                      type="submit"
                      disabled={
                        loading ||
                        !turnstileReady ||
                        !isPasswordSecure(password) ||
                        password !== passwordConfirm
                      }
                      className="flex-1 !bg-[#2f5a45]"
                    >
                      {loading ? 'Opening workspace…' : 'Save password & open workspace'}
                    </Button>
                  </div>
                </form>
              ) : signupLoading || !signupForm ? (
                <p className="text-sm text-stone-500">Loading signup wizard…</p>
              ) : (
                <>
                  <div className="mb-4 flex items-center gap-2">
                    {steps.map((step, i) => (
                      <div
                        key={step.id}
                        className={`h-1.5 flex-1 rounded-full ${
                          i <= signupStep ? 'bg-[#2f5a45]' : 'bg-stone-200'
                        }`}
                      />
                    ))}
                  </div>
                  <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-400">
                    Step {signupStep + 1} of {steps.length}
                  </p>
                  <h2 className="mt-1 text-lg font-semibold text-stone-900">
                    {currentStep?.title}
                  </h2>
                  {currentStep?.description ? (
                    <p className="mt-1 text-sm text-stone-500">{currentStep.description}</p>
                  ) : null}

                  <form onSubmit={handleSignupNext} className="mt-4 space-y-3">
                    {currentStep?.fields.map((field) =>
                      field.type === 'service_catalogue' ? (
                        <SignupServiceCatalogue
                          key={field.key}
                          drafts={serviceDrafts}
                          onChange={setServiceDrafts}
                          businessType={answers.business_type ?? ''}
                          maxSelectable={basicMaxServices}
                          onLimitReached={() => showToast(UPGRADE_TOAST)}
                        />
                      ) : field.key === 'postcode' ? (
                        <PostcodeAddressField
                          key={field.key}
                          field={field}
                          value={answers[field.key] ?? ''}
                          onChange={(v) => setAnswer(field.key, v)}
                          onSelectAddress={applyAddressSuggestion}
                        />
                      ) : field.type === 'time' ? (
                        <ClockTimeField
                          key={field.key}
                          label={fieldLabel(field)}
                          value={answers[field.key] ?? ''}
                          onChange={(v) => setAnswer(field.key, v)}
                          required={field.required}
                          help={field.help}
                        />
                      ) : (
                        <SignupFieldInput
                          key={field.key}
                          field={field}
                          value={answers[field.key] ?? ''}
                          onChange={(v) => setAnswer(field.key, v)}
                          plans={signupForm.plans}
                          trialDays={signupForm.trial_days}
                          locked={workspaceOnboarding && field.key === 'owner_email'}
                        />
                      ),
                    )}
                    <div className="flex gap-2 pt-2">
                      {signupStep > 0 ? (
                        <Button
                          type="button"
                          variant="secondary"
                          onClick={() => {
                            setError(null);
                            setSignupStep((s) => s - 1);
                          }}
                        >
                          Back
                        </Button>
                      ) : null}
                      <Button
                        type="submit"
                        disabled={loading || !turnstileReady}
                        className="flex-1 !bg-[#2f5a45]"
                      >
                        {loading
                          ? 'Submitting…'
                          : isLastStep
                            ? workspaceOnboarding
                              ? 'Finish workspace'
                              : 'Create salon'
                            : 'Continue'}
                      </Button>
                    </div>
                    <p className="text-[11px] text-stone-400">
                      {workspaceOnboarding
                        ? 'Next you will create your permanent password. The temporary unlock password will stop working.'
                        : `New salons start on Basic with a ${signupForm.trial_days}-day trial. Pro / Diamond stay locked until trial ends (or platform unlock).`}
                    </p>
                  </form>
                </>
              )}
            </div>
          ) : null}
        </div>
      </div>

      {/* RIGHT — brand panel */}
      <div className="relative hidden min-h-screen overflow-hidden lg:block lg:w-[54%]">
        <Image
          key={tab === 'signup' ? 'signup' : 'login'}
          src={tab === 'signup' ? HERO_SIGNUP : HERO_LOGIN}
          alt={tab === 'signup' ? 'Stylist preparing a salon workspace' : 'Salon interior'}
          fill
          priority={tab !== 'signup'}
          fetchPriority={tab === 'signup' ? 'auto' : 'high'}
          sizes="54vw"
          className="object-cover transition-opacity duration-500"
        />
        <div
          className={[
            'absolute inset-0 transition-colors duration-500',
            tab === 'signup'
              ? 'bg-gradient-to-t from-[#1c1917]/92 via-[#2f5a45]/50 to-[#1c1917]/30'
              : 'bg-gradient-to-t from-[#1c1917]/90 via-[#1c1917]/45 to-[#2f5a45]/35',
          ].join(' ')}
        />
        <div className="relative z-10 flex h-full flex-col justify-center p-12 xl:p-16">
          <NeatMeetLogo size={48} withWordmark variant="onDark" wordmarkClassName="text-white text-base" />
          <h2 className="mt-6 max-w-lg text-4xl font-semibold leading-tight tracking-tight text-white xl:text-5xl">
            {tab === 'signup'
              ? 'Your salon workspace, ready in minutes.'
              : 'Run the salon floor with calm confidence.'}
          </h2>
          <p className="mt-4 max-w-md text-base leading-relaxed text-stone-200">
            {tab === 'signup'
              ? 'Set up your business, services, and trial plan — then activate by email and start taking bookings.'
              : 'Bookings, clients, team, and payments — one operating system for modern salons that refuse chaos.'}
          </p>
        </div>
      </div>
    </div>
  );
}

function SignupFieldInput({
  field,
  value,
  onChange,
  plans,
  trialDays,
  locked = false,
}: {
  field: SignupFormField;
  value: string;
  onChange: (value: string) => void;
  plans: SignupPlan[];
  trialDays: number;
  locked?: boolean;
}) {
  if (field.type === 'plan_picker') {
    return (
      <div>
        <span className="text-sm font-medium text-stone-700">{field.label}</span>
        <div className="mt-2 grid gap-2">
          {plans.map((plan) => {
            const selected = value === plan.slug;
            const locked = Boolean(plan.locked_until_trial_end);
            return (
              <button
                key={plan.slug}
                type="button"
                onClick={() => onChange(plan.slug)}
                className={[
                  'rounded-xl border px-4 py-3 text-left transition',
                  selected
                    ? 'border-[#2f5a45] bg-[#e8f0eb] ring-2 ring-[#2f5a45]/25'
                    : 'border-stone-200 bg-white hover:border-stone-300',
                ].join(' ')}
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-stone-900">{plan.name}</p>
                    <p className="mt-0.5 text-xs text-stone-500">
                      {formatPrice(plan.display_price_cents)}/mo
                      {plan.is_default ? ` · ${trialDays}-day trial` : ''}
                    </p>
                  </div>
                  {locked ? (
                    <span className="shrink-0 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">
                      Interest · locked
                    </span>
                  ) : (
                    <span className="shrink-0 rounded-md bg-[#e8f0eb] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#2f5a45]">
                      Starts here
                    </span>
                  )}
                </div>
                {locked ? (
                  <p className="mt-1.5 text-[11px] text-stone-500">
                    You can register interest now. You&apos;ll start on Basic until
                    trial ends or a platform admin unlocks higher tiers.
                  </p>
                ) : null}
              </button>
            );
          })}
        </div>
      </div>
    );
  }

  if (field.type === 'select' && field.options?.length) {
    return (
      <label className="block text-sm">
        <span className="font-medium text-stone-700">
          {fieldLabel(field)}
        </span>
        <select
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className={inputClass}
          required={field.required}
        >
          <option value="">Select…</option>
          {field.options.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
        {field.help ? <p className="mt-1 text-[11px] text-stone-400">{field.help}</p> : null}
      </label>
    );
  }

  const inputType =
    field.type === 'email'
      ? 'email'
      : field.type === 'tel'
        ? 'tel'
        : field.type === 'time'
          ? 'time'
          : 'text';

  return (
    <label className="block text-sm">
      <span className="font-medium text-stone-700">
        {fieldLabel(field)}
      </span>
      <input
        type={inputType}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={`${inputClass}${locked ? ' cursor-not-allowed bg-stone-100 text-stone-600' : ''}`}
        required={field.required}
        placeholder={field.placeholder}
        disabled={locked}
        readOnly={locked}
      />
      {locked ? (
        <p className="mt-1 text-[11px] text-stone-400">
          Locked to your trial login email.
        </p>
      ) : null}
      {field.key === 'slug' && value.trim() ? (
        <p className="mt-1 text-[11px] text-stone-400">
          https://…/book/{value.trim()}
        </p>
      ) : null}
      {field.help ? <p className="mt-1 text-[11px] text-stone-400">{field.help}</p> : null}
    </label>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center bg-[#f7f5f2] text-sm text-stone-500">
          Loading…
        </div>
      }
    >
      <LoginAuthPage />
    </Suspense>
  );
}
