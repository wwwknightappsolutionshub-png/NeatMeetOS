'use client';

import { FormEvent, useEffect, useState } from 'react';
import {
  PlatformButton,
  PlatformCard,
  PlatformField,
  PlatformPageIntro,
  platformInputClass,
} from '@/components/platform/ui';
import type {
  PlatformAiHairstyleSettings,
  PlatformStaffUser,
  PlatformWhatsAppSettings,
} from '@/services/platform.service';
import {
  fetchPlatformAiHairstyleSettings,
  fetchPlatformProfile,
  fetchPlatformWhatsAppSettings,
  purgePlatformWhatsAppStale,
  testPlatformWhatsApp,
  updatePlatformAiHairstyleSettings,
  updatePlatformPassword,
  updatePlatformProfile,
  updatePlatformWhatsAppSettings,
  uploadPlatformSignupWelcomeBanner,
  clearPlatformSignupWelcomeBanner,
} from '@/services/platform.service';

export default function PlatformSettingsPage() {
  const [user, setUser] = useState<PlatformStaffUser | null>(null);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loadingProfile, setLoadingProfile] = useState(true);
  const [loadingWa, setLoadingWa] = useState(true);
  const [loadingAi, setLoadingAi] = useState(true);
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [waError, setWaError] = useState<string | null>(null);
  const [aiError, setAiError] = useState<string | null>(null);
  const [profileSaved, setProfileSaved] = useState(false);
  const [passwordSaved, setPasswordSaved] = useState(false);

  const [aiSettings, setAiSettings] = useState<PlatformAiHairstyleSettings | null>(null);
  const [aiProvider, setAiProvider] = useState('stub');
  const [savingAi, setSavingAi] = useState(false);
  const [aiSaved, setAiSaved] = useState(false);

  const [wa, setWa] = useState<PlatformWhatsAppSettings | null>(null);
  const [waEnabled, setWaEnabled] = useState(false);
  const [waProvider, setWaProvider] = useState('genius');
  const [waApiKey, setWaApiKey] = useState('');
  const [waSessionId, setWaSessionId] = useState('');
  const [waBaseUrl, setWaBaseUrl] = useState('https://restapi.geniusdevel.com');
  const [waMetaPhoneId, setWaMetaPhoneId] = useState('');
  const [waMetaToken, setWaMetaToken] = useState('');
  const [waTwilioSid, setWaTwilioSid] = useState('');
  const [waTwilioToken, setWaTwilioToken] = useState('');
  const [waTwilioFrom, setWaTwilioFrom] = useState('');
  const [waTestPhone, setWaTestPhone] = useState('');
  const [waTestMessage, setWaTestMessage] = useState('');
  const [waPurgeHours, setWaPurgeHours] = useState('1');
  const [waSignupEnabled, setWaSignupEnabled] = useState(true);
  const [waTrialBody, setWaTrialBody] = useState('');
  const [waActivationBody, setWaActivationBody] = useState('');
  const [waBannerUrl, setWaBannerUrl] = useState<string | null>(null);
  const [savingWa, setSavingWa] = useState(false);
  const [testingWa, setTestingWa] = useState(false);
  const [purgingWa, setPurgingWa] = useState(false);
  const [uploadingBanner, setUploadingBanner] = useState(false);
  const [waSaved, setWaSaved] = useState(false);
  const [waTestNote, setWaTestNote] = useState<string | null>(null);
  const [waPurgeNote, setWaPurgeNote] = useState<string | null>(null);

  const canManageAi =
    user?.platform_role === 'owner' || user?.platform_role === 'manager';

  function applyWhatsAppSettings(whatsapp: PlatformWhatsAppSettings) {
    setWa(whatsapp);
    setWaEnabled(whatsapp.enabled);
    setWaProvider(whatsapp.provider);
    setWaSessionId(whatsapp.session_id ?? '');
    setWaBaseUrl(whatsapp.base_url || 'https://restapi.geniusdevel.com');
    setWaMetaPhoneId(whatsapp.meta_phone_number_id ?? '');
    setWaTwilioSid(whatsapp.twilio_account_sid ?? '');
    setWaTwilioFrom(whatsapp.twilio_from ?? '');
    setWaSignupEnabled(whatsapp.signup_welcome?.enabled ?? true);
    setWaTrialBody(whatsapp.signup_welcome?.trial_body ?? '');
    setWaActivationBody(whatsapp.signup_welcome?.activation_body ?? '');
    setWaBannerUrl(whatsapp.signup_welcome?.banner.url ?? null);
  }

  useEffect(() => {
    setLoadingProfile(true);
    fetchPlatformProfile()
      .then((profile) => {
        setUser(profile.user);
        setName(profile.user.name);
        setEmail(profile.user.email);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load profile'))
      .finally(() => setLoadingProfile(false));

    setLoadingWa(true);
    setWaError(null);
    fetchPlatformWhatsAppSettings()
      .then((whatsapp) => applyWhatsAppSettings(whatsapp))
      .catch((e) =>
        setWaError(e instanceof Error ? e.message : 'Failed to load WhatsApp settings'),
      )
      .finally(() => setLoadingWa(false));

    setLoadingAi(true);
    setAiError(null);
    fetchPlatformAiHairstyleSettings()
      .then((ai) => {
        setAiSettings(ai);
        setAiProvider(ai.provider);
      })
      .catch((e) =>
        setAiError(e instanceof Error ? e.message : 'Failed to load AI settings'),
      )
      .finally(() => setLoadingAi(false));
  }, []);

  async function handleProfile(e: FormEvent) {
    e.preventDefault();
    setSavingProfile(true);
    setError(null);
    setProfileSaved(false);
    try {
      const data = await updatePlatformProfile({ name, email });
      setUser(data.user);
      setProfileSaved(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Profile update failed');
    } finally {
      setSavingProfile(false);
    }
  }

  async function handlePassword(e: FormEvent) {
    e.preventDefault();
    setSavingPassword(true);
    setError(null);
    setPasswordSaved(false);
    try {
      await updatePlatformPassword({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      });
      setCurrentPassword('');
      setPassword('');
      setPasswordConfirmation('');
      setPasswordSaved(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Password update failed');
    } finally {
      setSavingPassword(false);
    }
  }

  async function handleAi(e: FormEvent) {
    e.preventDefault();
    setSavingAi(true);
    setError(null);
    setAiSaved(false);
    try {
      const next = await updatePlatformAiHairstyleSettings({ provider: aiProvider });
      setAiSettings(next);
      setAiProvider(next.provider);
      setAiSaved(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'AI provider update failed');
    } finally {
      setSavingAi(false);
    }
  }

  async function handleWhatsApp(e: FormEvent) {
    e.preventDefault();
    setSavingWa(true);
    setError(null);
    setWaSaved(false);
    setWaTestNote(null);
    try {
      const payload: Parameters<typeof updatePlatformWhatsAppSettings>[0] = {
        enabled: waEnabled,
        provider: waProvider,
        session_id: waSessionId || null,
        base_url: waBaseUrl || null,
        meta_phone_number_id: waMetaPhoneId || null,
        twilio_account_sid: waTwilioSid || null,
        twilio_from: waTwilioFrom || null,
        signup_welcome_enabled: waSignupEnabled,
        signup_welcome_trial_body: waTrialBody || null,
        signup_welcome_activation_body: waActivationBody || null,
      };
      if (waApiKey.trim()) payload.api_key = waApiKey.trim();
      if (waMetaToken.trim()) payload.meta_access_token = waMetaToken.trim();
      if (waTwilioToken.trim()) payload.twilio_auth_token = waTwilioToken.trim();

      const next = await updatePlatformWhatsAppSettings(payload);
      applyWhatsAppSettings(next);
      setWaApiKey('');
      setWaMetaToken('');
      setWaTwilioToken('');
      setWaSaved(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'WhatsApp settings update failed');
    } finally {
      setSavingWa(false);
    }
  }

  async function handleWhatsAppTest(e: FormEvent) {
    e.preventDefault();
    setTestingWa(true);
    setError(null);
    setWaTestNote(null);
    try {
      const result = await testPlatformWhatsApp({
        phone: waTestPhone,
        message: waTestMessage.trim() || undefined,
      });
      setWaTestNote(`Sent to ${result.phone} via ${result.provider}.`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'WhatsApp test failed');
    } finally {
      setTestingWa(false);
    }
  }

  async function handleWhatsAppPurge(e: FormEvent) {
    e.preventDefault();
    setPurgingWa(true);
    setError(null);
    setWaPurgeNote(null);
    try {
      const hours = Math.max(0, Number(waPurgeHours) || 1);
      const result = await purgePlatformWhatsAppStale({
        include_failed_jobs: true,
        include_stale_messages: true,
        older_than_hours: hours,
      });
      const refreshed = await fetchPlatformWhatsAppSettings();
      setWa(refreshed);
      setWaPurgeNote(
        `Purged ${result.deleted_jobs} jobs, ${result.deleted_failed_jobs} failed jobs, ${result.cancelled_messages} stale messages.`,
      );
    } catch (err) {
      setError(err instanceof Error ? err.message : 'WhatsApp purge failed');
    } finally {
      setPurgingWa(false);
    }
  }

  async function handleBannerUpload(file: File | null) {
    if (!file) return;
    setUploadingBanner(true);
    setError(null);
    try {
      const next = await uploadPlatformSignupWelcomeBanner(file);
      setWa(next);
      setWaBannerUrl(next.signup_welcome?.banner.url ?? null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Banner upload failed');
    } finally {
      setUploadingBanner(false);
    }
  }

  async function handleBannerClear() {
    setUploadingBanner(true);
    setError(null);
    try {
      const next = await clearPlatformSignupWelcomeBanner();
      setWa(next);
      setWaBannerUrl(next.signup_welcome?.banner.url ?? null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not clear banner');
    } finally {
      setUploadingBanner(false);
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-5">
      <PlatformPageIntro
        title="Account settings"
        description="Update your platform profile, password, and outbound messaging providers."
      />

      {error ? (
        <div className="rounded-lg border border-red-500/40 bg-red-950/40 px-3 py-2 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      <PlatformCard title="Profile">
        {loadingProfile ? (
          <p className="text-sm text-stone-400">Loading…</p>
        ) : (
          <form onSubmit={handleProfile} className="space-y-3">
            <PlatformField label="Name">
              <input
                className={platformInputClass}
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                minLength={2}
              />
            </PlatformField>
            <PlatformField label="Email">
              <input
                type="email"
                className={platformInputClass}
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </PlatformField>
            <PlatformField label="Role">
              <input
                className={platformInputClass}
                value={user?.platform_role_label ?? user?.platform_role ?? '—'}
                disabled
              />
            </PlatformField>
            <div className="flex items-center gap-3 pt-1">
              <PlatformButton type="submit" disabled={savingProfile}>
                {savingProfile ? 'Saving…' : 'Save profile'}
              </PlatformButton>
              {profileSaved ? <span className="text-sm text-emerald-400">Saved</span> : null}
            </div>
          </form>
        )}
      </PlatformCard>

      <PlatformCard title="WhatsApp platform sender">
        {loadingWa ? (
          <p className="text-sm text-stone-400">Loading…</p>
        ) : waError || !wa ? (
          <div className="space-y-2">
            <p className="text-sm text-red-300">
              {waError || 'WhatsApp settings could not be loaded.'}
            </p>
            <p className="text-xs text-stone-500">
              If this is a 404 after deploy, run{' '}
              <code className="text-stone-300">php artisan route:clear</code> then{' '}
              <code className="text-stone-300">php artisan route:cache</code> on the VPS.
            </p>
            <PlatformButton
              type="button"
              onClick={() => {
                setLoadingWa(true);
                setWaError(null);
                fetchPlatformWhatsAppSettings()
                  .then((whatsapp) => applyWhatsAppSettings(whatsapp))
                  .catch((e) =>
                    setWaError(
                      e instanceof Error ? e.message : 'Failed to load WhatsApp settings',
                    ),
                  )
                  .finally(() => setLoadingWa(false));
              }}
            >
              Retry
            </PlatformButton>
          </div>
        ) : (
          <div className="space-y-4">
            <p className="text-sm text-stone-400">
              Platform fallback for booking confirm / cancel / reschedule and signup welcome when a
              salon has not connected its own WhatsApp session. Genius API is the default provider (
              <code className="text-stone-300">POST /api/send</code> with{' '}
              <code className="text-stone-300">x-api-key</code>).
            </p>
            <span
              className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                wa.enabled && wa.configured
                  ? 'bg-emerald-500/15 text-emerald-300'
                  : 'bg-white/10 text-stone-300'
              }`}
            >
              {wa.enabled && wa.configured ? `Active · ${wa.provider}` : `Inactive · ${wa.provider}`}
            </span>
            <form onSubmit={handleWhatsApp} className="space-y-3">
              <label className="flex items-center gap-2 text-sm text-stone-200">
                <input
                  type="checkbox"
                  checked={waEnabled}
                  disabled={!canManageAi}
                  onChange={(e) => setWaEnabled(e.target.checked)}
                />
                Enable platform WhatsApp sender
              </label>
              <PlatformField label="Provider">
                <select
                  className={platformInputClass}
                  value={waProvider}
                  disabled={!canManageAi}
                  onChange={(e) => setWaProvider(e.target.value)}
                >
                  {wa.providers.map((p) => (
                    <option key={p.key} value={p.key}>
                      {p.label}
                      {p.live ? '' : ' (stored only)'}
                    </option>
                  ))}
                </select>
              </PlatformField>

              {waProvider === 'genius' ? (
                <div className="grid gap-3 sm:grid-cols-2">
                  <PlatformField label="API key (leave blank to keep)">
                    <input
                      type="password"
                      className={platformInputClass}
                      value={waApiKey}
                      disabled={!canManageAi}
                      onChange={(e) => setWaApiKey(e.target.value)}
                      placeholder={wa.has_api_key ? 'api-… (saved)' : 'api-…'}
                      autoComplete="off"
                    />
                  </PlatformField>
                  <PlatformField label="Session ID">
                    <input
                      className={platformInputClass}
                      value={waSessionId}
                      disabled={!canManageAi}
                      onChange={(e) => setWaSessionId(e.target.value)}
                      placeholder="session_xxxxxxxxxxx"
                    />
                  </PlatformField>
                  <div className="sm:col-span-2">
                    <PlatformField label="Base URL">
                      <input
                        className={platformInputClass}
                        value={waBaseUrl}
                        disabled={!canManageAi}
                        onChange={(e) => setWaBaseUrl(e.target.value)}
                        placeholder="https://restapi.geniusdevel.com"
                      />
                    </PlatformField>
                  </div>
                </div>
              ) : null}

              {waProvider === 'meta' ? (
                <>
                  <PlatformField label="Meta phone number ID">
                    <input
                      className={platformInputClass}
                      value={waMetaPhoneId}
                      disabled={!canManageAi}
                      onChange={(e) => setWaMetaPhoneId(e.target.value)}
                    />
                  </PlatformField>
                  <PlatformField label="Meta access token">
                    <input
                      type="password"
                      className={platformInputClass}
                      value={waMetaToken}
                      disabled={!canManageAi}
                      onChange={(e) => setWaMetaToken(e.target.value)}
                      placeholder={wa.has_meta_access_token ? '•••••••• (saved)' : ''}
                      autoComplete="off"
                    />
                  </PlatformField>
                </>
              ) : null}

              {waProvider === 'twilio' ? (
                <>
                  <PlatformField label="Twilio account SID">
                    <input
                      className={platformInputClass}
                      value={waTwilioSid}
                      disabled={!canManageAi}
                      onChange={(e) => setWaTwilioSid(e.target.value)}
                    />
                  </PlatformField>
                  <PlatformField label="Twilio auth token">
                    <input
                      type="password"
                      className={platformInputClass}
                      value={waTwilioToken}
                      disabled={!canManageAi}
                      onChange={(e) => setWaTwilioToken(e.target.value)}
                      placeholder={wa.has_twilio_auth_token ? '•••••••• (saved)' : ''}
                      autoComplete="off"
                    />
                  </PlatformField>
                  <PlatformField label="Twilio from (WhatsApp)">
                    <input
                      className={platformInputClass}
                      value={waTwilioFrom}
                      disabled={!canManageAi}
                      onChange={(e) => setWaTwilioFrom(e.target.value)}
                      placeholder="whatsapp:+1415…"
                    />
                  </PlatformField>
                </>
              ) : null}

              <p className="text-xs text-stone-500">
                Status: {wa.configured ? 'credentials present' : 'incomplete'} · API key:{' '}
                {wa.has_api_key ? 'saved' : 'missing'}
              </p>

              {canManageAi ? (
                <div className="flex items-center gap-3 pt-1">
                  <PlatformButton type="submit" disabled={savingWa}>
                    {savingWa ? 'Saving…' : 'Save WhatsApp'}
                  </PlatformButton>
                  {waSaved ? <span className="text-sm text-emerald-400">Saved</span> : null}
                </div>
              ) : (
                <p className="text-xs text-stone-500">Only owners and managers can change this.</p>
              )}
            </form>

            {canManageAi && waProvider === 'genius' ? (
              <form
                onSubmit={handleWhatsAppTest}
                className="space-y-3 rounded-lg border border-stone-700/60 bg-stone-950/30 p-4"
              >
                <p className="text-sm font-medium text-stone-200">Send test message</p>
                <p className="text-xs text-stone-500">
                  Uses the saved platform credentials (same path as signup welcome WhatsApp). Save
                  settings before testing if you just changed keys.
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                  <PlatformField label="Phone (E.164)">
                    <input
                      className={platformInputClass}
                      value={waTestPhone}
                      onChange={(e) => setWaTestPhone(e.target.value)}
                      placeholder="+447700900123"
                      required
                    />
                  </PlatformField>
                  <PlatformField label="Message (optional)">
                    <input
                      className={platformInputClass}
                      value={waTestMessage}
                      onChange={(e) => setWaTestMessage(e.target.value)}
                      placeholder="NeatMeet OS platform WhatsApp test…"
                    />
                  </PlatformField>
                </div>
                <div className="flex items-center gap-3">
                  <PlatformButton type="submit" disabled={testingWa || !waTestPhone.trim()}>
                    {testingWa ? 'Sending…' : 'Send test WhatsApp'}
                  </PlatformButton>
                  {waTestNote ? <span className="text-sm text-emerald-400">{waTestNote}</span> : null}
                </div>
              </form>
            ) : null}

            {canManageAi ? (
              <form onSubmit={handleWhatsAppPurge} className="space-y-3 border-t border-stone-700/60 pt-4">
                <p className="text-sm font-medium text-stone-200">Purge stale WhatsApp messages</p>
                <p className="text-xs text-stone-500">
                  Clears queued notification jobs and cancels stale WhatsApp notification rows
                  (queued / processing / failed). Pending queue:{' '}
                  {wa.queue?.pending_jobs ?? 0} · reserved: {wa.queue?.reserved_jobs ?? 0} · failed
                  jobs: {wa.queue?.failed_jobs ?? 0} · stale messages: {wa.queue?.stale_messages ?? 0}.
                </p>
                <PlatformField label="Older than (hours)">
                  <input
                    type="number"
                    min={0}
                    max={720}
                    className={platformInputClass}
                    value={waPurgeHours}
                    onChange={(e) => setWaPurgeHours(e.target.value)}
                  />
                </PlatformField>
                <div className="flex items-center gap-3">
                  <PlatformButton type="submit" disabled={purgingWa}>
                    {purgingWa ? 'Purging…' : 'Purge stale WhatsApp'}
                  </PlatformButton>
                  {waPurgeNote ? <span className="text-sm text-emerald-400">{waPurgeNote}</span> : null}
                </div>
              </form>
            ) : null}

            {canManageAi ? (
              <div className="space-y-3 border-t border-stone-700/60 pt-4">
                <p className="text-sm font-medium text-stone-200">Tenant signup welcome WhatsApp</p>
                <p className="text-xs text-stone-500">
                  Sent with the welcome-trial / activation emails. Uses platform Genius credentials.
                  Placeholders: {'{{name}}'}, {'{{email}}'}, {'{{password}}'}, {'{{salon}}'},{' '}
                  {'{{link}}'}.
                </p>
                <label className="flex items-center gap-2 text-sm text-stone-300">
                  <input
                    type="checkbox"
                    checked={waSignupEnabled}
                    onChange={(e) => setWaSignupEnabled(e.target.checked)}
                  />
                  Enable signup welcome WhatsApp
                </label>
                <PlatformField label="Welcome trial message">
                  <textarea
                    className={`${platformInputClass} min-h-[140px] font-mono text-xs`}
                    value={waTrialBody}
                    onChange={(e) => setWaTrialBody(e.target.value)}
                  />
                </PlatformField>
                <PlatformField label="Activation message">
                  <textarea
                    className={`${platformInputClass} min-h-[140px] font-mono text-xs`}
                    value={waActivationBody}
                    onChange={(e) => setWaActivationBody(e.target.value)}
                  />
                </PlatformField>
                <PlatformField label="Banner image (static)">
                  <input
                    type="file"
                    accept="image/*"
                    className="block w-full text-sm text-stone-400 file:mr-3 file:rounded-md file:border-0 file:bg-stone-700 file:px-3 file:py-1.5 file:text-stone-100"
                    disabled={uploadingBanner}
                    onChange={(e) => void handleBannerUpload(e.target.files?.[0] ?? null)}
                  />
                </PlatformField>
                {waBannerUrl ? (
                  <div className="flex flex-wrap items-start gap-3">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={waBannerUrl}
                      alt="Signup welcome banner"
                      className="h-24 w-auto rounded-md border border-stone-700 object-cover"
                    />
                    <PlatformButton
                      type="button"
                      disabled={uploadingBanner}
                      onClick={() => void handleBannerClear()}
                    >
                      {uploadingBanner ? 'Working…' : 'Remove banner'}
                    </PlatformButton>
                  </div>
                ) : (
                  <p className="text-xs text-stone-500">No banner uploaded yet.</p>
                )}
                <p className="text-xs text-stone-500">
                  Save WhatsApp above to persist message copy. Banner uploads immediately.
                </p>
              </div>
            ) : null}
          </div>
        )}
      </PlatformCard>

      <PlatformCard title="AI Hairstyle provider">
        {loadingAi ? (
          <p className="text-sm text-stone-400">Loading…</p>
        ) : aiError || !aiSettings ? (
          <p className="text-sm text-red-300">{aiError || 'AI settings could not be loaded.'}</p>
        ) : (
          <form onSubmit={handleAi} className="space-y-3">
            <p className="text-sm text-stone-400">
              Choose the generation backend for entitled salons. Secrets stay in server env
              (`REPLICATE_API_TOKEN`). Production should set `AI_HAIRSTYLE_ALLOW_STUB=false`.
            </p>
            {!aiSettings.allow_stub ? (
              <p className="text-xs text-amber-400/90">
                Stub is disabled on this server — Replicate only.
              </p>
            ) : null}
            <PlatformField label="Active provider">
              <select
                className={platformInputClass}
                value={aiProvider}
                disabled={!canManageAi}
                onChange={(e) => setAiProvider(e.target.value)}
              >
                {aiSettings.providers.map((p) => (
                  <option key={p.key} value={p.key}>
                    {p.label}
                  </option>
                ))}
              </select>
            </PlatformField>
            <p className="text-xs text-stone-500">
              {aiSettings.providers.find((p) => p.key === aiProvider)?.description}
            </p>
            <p className="text-xs text-stone-500">
              Replicate token: {aiSettings.replicate_configured ? 'configured' : 'missing'} · Model:{' '}
              {aiSettings.replicate_model}
            </p>
            {canManageAi ? (
              <div className="flex items-center gap-3 pt-1">
                <PlatformButton type="submit" disabled={savingAi}>
                  {savingAi ? 'Saving…' : 'Save provider'}
                </PlatformButton>
                {aiSaved ? <span className="text-sm text-emerald-400">Saved</span> : null}
              </div>
            ) : (
              <p className="text-xs text-stone-500">Only owners and managers can change this.</p>
            )}
          </form>
        )}
      </PlatformCard>

      <PlatformCard title="Change password">
        <form onSubmit={handlePassword} className="space-y-3">
          <PlatformField label="Current password">
            <input
              type="password"
              className={platformInputClass}
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              required
              autoComplete="current-password"
            />
          </PlatformField>
          <PlatformField label="New password">
            <input
              type="password"
              className={platformInputClass}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              minLength={8}
              autoComplete="new-password"
            />
          </PlatformField>
          <PlatformField label="Confirm new password">
            <input
              type="password"
              className={platformInputClass}
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              required
              minLength={8}
              autoComplete="new-password"
            />
          </PlatformField>
          <div className="flex items-center gap-3 pt-1">
            <PlatformButton type="submit" disabled={savingPassword}>
              {savingPassword ? 'Updating…' : 'Update password'}
            </PlatformButton>
            {passwordSaved ? <span className="text-sm text-emerald-400">Password updated</span> : null}
          </div>
        </form>
      </PlatformCard>
    </div>
  );
}
