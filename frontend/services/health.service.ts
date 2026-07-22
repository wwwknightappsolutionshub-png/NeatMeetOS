import { api } from '@/lib/api-client';
import type { HealthCheck, VersionInfo } from '@/lib/types';

export async function fetchHealth(): Promise<HealthCheck> {
  return api<HealthCheck>('/health', { tenant: false });
}

export async function fetchVersion(): Promise<VersionInfo> {
  return api<VersionInfo>('/version', { tenant: false });
}
