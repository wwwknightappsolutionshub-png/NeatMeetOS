import type { BookableService } from '@/lib/booking-types';
import { resolveMediaUrl } from '@/lib/media-url';

export function categoryLabel(category: string | null): string {
  if (!category) return 'Service';
  return category.charAt(0).toUpperCase() + category.slice(1);
}

export function serviceCategoryImageSrc(category: string | null): string {
  if (category?.toLowerCase() === 'colour' || category?.toLowerCase() === 'color') {
    return '/book/service-colour.jpg';
  }
  return '/book/service-hair.jpg';
}

export function resolveServiceImageSrc(
  service: Pick<BookableService, 'image_url' | 'category'>,
): string {
  const uploaded = resolveMediaUrl(service.image_url);
  if (uploaded) return uploaded;
  return serviceCategoryImageSrc(service.category);
}

export function bookServiceHref(bookHref: string, serviceId: string): string {
  const separator = bookHref.includes('?') ? '&' : '?';
  return `${bookHref}${separator}service=${encodeURIComponent(serviceId)}`;
}

export function isOtpDeliveryNotice(message: string | null | undefined): boolean {
  if (!message) return false;
  return /emailed a code|sent a code to whatsapp|texted a code/i.test(message);
}
