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
} from '@/services/platform.service';
import {
  fetchPlatformAiHairstyleSettings,
  fetchPlatformProfile,
  updatePlatformAiHairstyleSettings,
  updatePlatformPassword,
  updatePlatformProfile,
} from '@/services/platform.service';

export default function PlatformSettingsPage() {
  const [user, setUser] = useState<PlatformStaffUser | null>(null);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(true);
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [profileSaved, setProfileSaved] = useState(false);
  const [passwordSaved, setPasswordSaved] = useState(false);

  const [aiSettings, setAiSettings] = useState<PlatformAiHairstyleSettings | null>(null);
  const [aiProvider, setAiProvider] = useState('stub');
  const [savingAi, setSavingAi] = useState(false);
  const [aiSaved, setAiSaved] = useState(false);

  const canManageAi =
    user?.platform_role === 'owner' || user?.platform_role === 'manager';

  useEffect(() => {
    Promise.all([fetchPlatformProfile(), fetchPlatformAiHairstyleSettings()])
      .then(([profile, ai]) => {
        setUser(profile.user);
        setName(profile.user.name);
        setEmail(profile.user.email);
        setAiSettings(ai);
        setAiProvider(ai.provider);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load settings'))
      .finally(() => setLoading(false));
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

  return (
    <div className="mx-auto max-w-2xl space-y-5">
      <PlatformPageIntro
        title="Account settings"
        description="Update your platform profile and password. Staff roles are managed separately by owners."
      />

      {error ? (
        <div className="rounded-lg border border-red-500/40 bg-red-950/40 px-3 py-2 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      <PlatformCard title="Profile">
        {loading ? (
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

      <PlatformCard title="AI Hairstyle provider">
        {loading || !aiSettings ? (
          <p className="text-sm text-stone-400">Loading…</p>
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
