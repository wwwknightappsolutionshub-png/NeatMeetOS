import { redirect } from 'next/navigation';

/** Audit log is platform-admin only — tenants are redirected away. */
export default function AuditSettingsPage() {
  redirect('/admin/dashboard');
}
