export interface SalonReview {
  id: string;
  author_name: string;
  rating: number;
  body: string;
  is_published: boolean;
  display_order: number;
  created_at: string | null;
}
