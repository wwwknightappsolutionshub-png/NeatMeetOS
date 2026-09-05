<?php

use App\Domains\AiHairstyle\Http\Controllers\Admin\AdminAiHairstyleController;
use App\Domains\Notifications\Http\Controllers\Admin\TenantWhatsAppSettingsController;
use App\Domains\Notifications\Http\Controllers\Platform\PlatformSignupWhatsAppWelcomeImageController;
use App\Domains\Notifications\Http\Controllers\Platform\PlatformWhatsAppSettingsController;
use App\Domains\AiHairstyle\Http\Controllers\Platform\PlatformAiHairstyleSettingController;
use App\Domains\AiHairstyle\Http\Controllers\PublicBook\PublicAiHairstyleController;
use App\Domains\Booking\Http\Controllers\Admin\AppointmentCommerceController;
use App\Domains\Booking\Http\Controllers\Admin\AppointmentController;
use App\Domains\Booking\Http\Controllers\Admin\AppointmentPackageController;
use App\Domains\Booking\Http\Controllers\Admin\BookableServiceController;
use App\Domains\Booking\Http\Controllers\Admin\BookingBoardController;
use App\Domains\Booking\Http\Controllers\Admin\BookingChangeRequestController;
use App\Domains\Booking\Http\Controllers\Admin\RecurrenceSeriesController;
use App\Domains\Booking\Http\Controllers\Admin\WaitlistController;
use App\Domains\Booking\Http\Controllers\Admin\WalkInController;
use App\Domains\Booking\Http\Controllers\Platform\PlatformBookingPolicyController;
use App\Domains\Booking\Http\Controllers\PublicBooking\OnlineBookingController;
use App\Domains\Booking\Http\Controllers\PublicBooking\PublicSalonReviewController;
use App\Domains\Booking\Http\Controllers\Admin\SalonReviewController;
use App\Domains\Booking\Http\Controllers\Admin\StaffSosAlertController;
use App\Domains\Ecommerce\Http\Controllers\Admin\EcommerceOrderController;
use App\Domains\Ecommerce\Http\Controllers\Admin\EcommerceProductController;
use App\Domains\Ecommerce\Http\Controllers\ShopController;
use App\Domains\Gallery\Http\Controllers\Admin\GalleryWorkController;
use App\Domains\Gallery\Http\Controllers\PublicGalleryController;
use App\Domains\GrowthAssessment\Http\Controllers\Platform\PlatformSalonGrowthAssessmentController;
use App\Domains\GrowthAssessment\Http\Controllers\Public\PublicSalonGrowthAssessmentController;
use App\Domains\Lookbook\Http\Controllers\Admin\LookbookItemController;
use App\Domains\Money\Http\Controllers\Admin\MoneyNotebookController;
use App\Domains\Lookbook\Http\Controllers\PublicLookbookController;
use App\Domains\Crm\Http\Controllers\PublicJoin\PublicClientCaptureController;
use App\Domains\Crm\Http\Controllers\PublicMember\MemberPortalController;
use App\Domains\Crm\Http\Controllers\PublicMember\MemberMessagesController;
use App\Domains\Crm\Http\Controllers\PublicMember\MemberLooksController;
use App\Domains\Crm\Http\Controllers\PublicMember\NextVisitMemberController;
use App\Domains\Crm\Http\Controllers\Admin\ClientConsentController;
use App\Domains\Crm\Http\Controllers\Admin\ClientController;
use App\Domains\Crm\Http\Controllers\Admin\ClientImportController;
use App\Domains\Crm\Http\Controllers\Admin\ClientDocumentController;
use App\Domains\Crm\Http\Controllers\Admin\ClientFormulaController;
use App\Domains\Crm\Http\Controllers\Admin\ClientNoteController;
use App\Domains\Crm\Http\Controllers\Admin\ClientPhotoController;
use App\Domains\Crm\Http\Controllers\Admin\ClientTagController;
use App\Domains\Crm\Http\Controllers\Admin\ClientThreadController;
use App\Domains\Crm\Http\Controllers\Admin\AdminMessagesController;
use App\Domains\Crm\Http\Controllers\Admin\ClientTimelineController;
use App\Domains\Crm\Http\Controllers\Admin\ClientVisitController;
use App\Domains\Crm\Http\Controllers\Admin\NextVisitController;
use App\Domains\Staff\Http\Controllers\Admin\StaffAbsenceController;
use App\Domains\Staff\Http\Controllers\Admin\StaffAvailabilityController;
use App\Domains\Staff\Http\Controllers\Admin\StaffProfileController;
use App\Domains\Staff\Http\Controllers\Admin\StaffProviderController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformAdminController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformUpgradeCampaignController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformTenantBroadcastController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformReferralSettingController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformPresenceController;
use App\Domains\Identity\Http\Controllers\UpgradeOfferController;
use App\Domains\Identity\Http\Controllers\Admin\TenantOwnerNoticeController;
use App\Domains\Identity\Http\Controllers\Admin\TenantOwnerPushSubscriptionController;
use App\Domains\Identity\Http\Controllers\Admin\TenantPlatformReferralController;
use App\Domains\Identity\Http\Controllers\Admin\TenantPresenceController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformAuditLogController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformModuleController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformNotificationController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformProfileController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformSignupFormController;
use App\Domains\Identity\Http\Controllers\Platform\PlatformStaffController;
use App\Domains\Identity\Http\Controllers\Admin\BrandingController;
use App\Domains\Identity\Http\Controllers\Admin\LocationController;
use App\Domains\Identity\Http\Controllers\Admin\OrganizationController;
use App\Domains\Identity\Http\Controllers\Admin\RoleController;
use App\Domains\Identity\Http\Controllers\Admin\SubscriptionController;
use App\Domains\Identity\Http\Controllers\Admin\TeamMemberController;
use App\Domains\Identity\Http\Controllers\Admin\WorkspaceController;
use App\Domains\Identity\Http\Controllers\AuthController;
use App\Domains\Identity\Http\Controllers\AuthLinkController;
use App\Domains\Identity\Http\Controllers\HealthController;
use App\Domains\Identity\Http\Controllers\PublicSignupController;
use App\Domains\Identity\Http\Controllers\ShellController;
use App\Domains\Identity\Http\Controllers\VersionController;
use App\Domains\Inventory\Http\Controllers\Admin\InventoryConsumptionController;
use App\Domains\Inventory\Http\Controllers\Admin\InventoryItemController;
use App\Domains\Inventory\Http\Controllers\Admin\InventoryLevelController;
use App\Domains\Inventory\Http\Controllers\Admin\InventoryMovementController;
use App\Domains\Inventory\Http\Controllers\Admin\InventorySupplierController;
use App\Domains\Inventory\Http\Controllers\Admin\ServiceConsumptionRuleController;
use App\Domains\Memberships\Http\Controllers\Admin\ClientMembershipController;
use App\Domains\Memberships\Http\Controllers\Admin\ClientPackageController;
use App\Domains\Memberships\Http\Controllers\Admin\LoyaltyLedgerController;
use App\Domains\Memberships\Http\Controllers\Admin\MembershipCommerceController;
use App\Domains\Memberships\Http\Controllers\Admin\MembershipPlanController;
use App\Domains\Memberships\Http\Controllers\Admin\MembershipSummaryController;
use App\Domains\Memberships\Http\Controllers\Admin\PackageProductController;
use App\Domains\Memberships\Http\Controllers\Admin\WalletLedgerController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingAudienceController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingAutomationController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingCampaignController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingMessageController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingReportingController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingRunController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingSuppressionController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingTemplateController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingWorkflowController;
use App\Domains\Marketing\Http\Controllers\Admin\MarketingWorkflowExecutionController;
use App\Domains\Analytics\Http\Controllers\Admin\AnalyticsController;
use App\Domains\Analytics\Http\Controllers\Admin\AnalyticsExportController;
use App\Domains\Analytics\Http\Controllers\Admin\AnalyticsSavedReportController;
use App\Domains\Memberships\Http\Controllers\PublicMembership\PublicMembershipLandingController;
use App\Domains\Integrations\Http\Controllers\Admin\ProviderAccountController;
use App\Domains\Integrations\Http\Controllers\Admin\ProviderDeliveryAttemptController;
use App\Domains\Integrations\Http\Controllers\Admin\ProviderWebhookEventController;
use App\Domains\Integrations\Http\Controllers\ProviderWebhookIngestController;
use App\Domains\Notifications\Http\Controllers\Admin\NotificationMessageController;
use App\Domains\Notifications\Http\Controllers\Admin\NotificationPreferenceController;
use App\Domains\Notifications\Http\Controllers\Admin\NotificationReportingController;
use App\Domains\Notifications\Http\Controllers\Admin\NotificationSettingController;
use App\Domains\Notifications\Http\Controllers\Admin\NotificationTemplateController;
use App\Domains\Notifications\Http\Controllers\Admin\NotificationTimelineController;
use App\Domains\Booking\Http\Controllers\Admin\ReservationPaymentDocumentController;
use App\Domains\Booking\Http\Controllers\PublicBooking\PublicReservationPaymentController;
use App\Domains\Payments\Http\Controllers\Admin\DepositPaymentController;
use App\Domains\Payments\Http\Controllers\Admin\TenantPaymentsSettingsController;
use App\Domains\Pos\Http\Controllers\Admin\CheckoutAdvancedController;
use App\Domains\Pos\Http\Controllers\Admin\CheckoutController;
use App\Domains\Pos\Http\Controllers\Admin\CheckoutMembershipController;
use App\Domains\Pos\Http\Controllers\Admin\PosCatalogController;
use App\Domains\Payments\Http\Controllers\Admin\PaymentRefundController;
use App\Domains\Payments\Http\Controllers\Admin\PaymentReportingController;
use App\Domains\Payments\Http\Controllers\Admin\PaymentTransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);
    Route::get('/version', VersionController::class);
    Route::get('/public/whatsapp/signup-welcome-banner', [PlatformSignupWhatsAppWelcomeImageController::class, 'show']);

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware(['ip.ban', 'throttle:auth-login', 'turnstile']);
    Route::post('/auth/magic-link', [AuthLinkController::class, 'requestMagic'])->middleware(['ip.ban', 'throttle:public-signup', 'turnstile']);
    Route::post('/auth/magic-link/consume', [AuthLinkController::class, 'consumeMagic'])->middleware(['ip.ban', 'throttle:auth-login', 'turnstile']);
    Route::post('/auth/forgot-password', [AuthLinkController::class, 'requestPasswordReset'])->middleware(['ip.ban', 'throttle:public-signup', 'turnstile']);
    Route::post('/auth/reset-password', [AuthLinkController::class, 'resetPassword'])->middleware(['ip.ban', 'throttle:public-signup', 'turnstile']);

    Route::middleware(['ip.ban', 'throttle:public-signup'])->group(function () {
        Route::post('/growth-assessments', [PublicSalonGrowthAssessmentController::class, 'store'])->middleware('turnstile');
        Route::get('/growth-assessments/{token}', [PublicSalonGrowthAssessmentController::class, 'show']);
        Route::post('/growth-assessments/{token}/whatsapp', [PublicSalonGrowthAssessmentController::class, 'sendWhatsApp'])->middleware('turnstile');
    });

    Route::prefix('signup')->middleware(['ip.ban', 'throttle:public-signup'])->group(function () {
        Route::get('/form', [PublicSignupController::class, 'form']);
        Route::post('/register', [PublicSignupController::class, 'register'])->middleware('turnstile');
        Route::post('/lead', [PublicSignupController::class, 'lead'])->middleware('turnstile');
        Route::post('/activate', [PublicSignupController::class, 'activate'])->middleware('turnstile');
        Route::post('/upload-service-image', [PublicSignupController::class, 'uploadServiceImage'])->middleware('turnstile');
        Route::get('/address-lookup', [PublicSignupController::class, 'addressLookup']);
    });

    Route::middleware(['auth:sanctum', 'throttle:public-signup'])->group(function () {
        Route::post('/signup/complete-workspace', [PublicSignupController::class, 'completeWorkspace']);
    });

    // Module 4 — Public online booking portal (tenant via X-Tenant-Slug / X-Tenant-ID).
    Route::middleware(['throttle:public-book', 'tenant.resolve', 'tenant.require'])->prefix('book')->group(function () {
        Route::get('/catalog', [OnlineBookingController::class, 'catalog']);
        Route::get('/slots', [OnlineBookingController::class, 'slots']);
        Route::get('/guest-contact', [OnlineBookingController::class, 'lookupGuest']);
        Route::get('/memberships', [PublicMembershipLandingController::class, 'show']);
        Route::post('/appointments', [OnlineBookingController::class, 'book'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);
        Route::post('/reservation-proof', [PublicReservationPaymentController::class, 'storeProof'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);
        Route::get('/appointments/{bookingReference}', [OnlineBookingController::class, 'showManaged']);
        Route::post('/appointments/{bookingReference}/cancel', [OnlineBookingController::class, 'cancelManaged'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);
        Route::get('/change-requests', [OnlineBookingController::class, 'showChangeRequest']);
        Route::post('/change-requests/resolve', [OnlineBookingController::class, 'resolveChangeRequest'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);
        Route::get('/reviews', [PublicSalonReviewController::class, 'index']);
        Route::post('/reviews', [PublicSalonReviewController::class, 'store'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);

        Route::prefix('ai-hairstyle')->group(function () {
            Route::post('/sessions', [PublicAiHairstyleController::class, 'store'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);
            Route::get('/sessions/{id}', [PublicAiHairstyleController::class, 'show']);
            Route::post('/sessions/{id}/generate', [PublicAiHairstyleController::class, 'generate'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);
            Route::post('/sessions/{id}/select', [PublicAiHairstyleController::class, 'select'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);
            Route::post('/sessions/{id}/submit', [PublicAiHairstyleController::class, 'submit'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);
        });
    });

    // Module 2 extension — Public CRM join form (QR lead capture; WhatsApp required).
    Route::middleware(['ip.ban', 'throttle:public-join', 'tenant.resolve', 'tenant.require'])->prefix('join')->group(function () {
        Route::get('/bootstrap', [PublicClientCaptureController::class, 'bootstrap']);
        Route::post('/clients', [PublicClientCaptureController::class, 'store'])->middleware('turnstile');
    });

    // Module 2 / 9 extension — Membership PWA client portal (email + WhatsApp OTP login).
    Route::middleware(['ip.ban', 'throttle:public-member', 'tenant.resolve', 'tenant.require'])->prefix('member')->group(function () {
        Route::get('/bootstrap', [MemberPortalController::class, 'bootstrap']);
        Route::post('/login/request-otp', [MemberPortalController::class, 'requestOtp'])->middleware(['throttle:public-member-otp', 'turnstile']);
        Route::post('/login', [MemberPortalController::class, 'login'])->middleware('turnstile');
        Route::get('/me', [MemberPortalController::class, 'me']);
        Route::get('/dashboard', [MemberPortalController::class, 'dashboard']);
        Route::get('/visits', [MemberPortalController::class, 'visits']);
        Route::get('/loyalty', [MemberPortalController::class, 'loyalty']);
        Route::get('/offers', [MemberPortalController::class, 'offers']);
        Route::post('/purchases', [MemberPortalController::class, 'purchase']);
        Route::get('/gifts', [MemberPortalController::class, 'gifts']);
        Route::post('/gifts', [MemberPortalController::class, 'createGift']);
        Route::post('/gifts/claim', [MemberPortalController::class, 'claimGift']);
        Route::post('/push-subscriptions', [MemberPortalController::class, 'subscribePush']);
        Route::delete('/push-subscriptions', [MemberPortalController::class, 'unsubscribePush']);
        Route::get('/notices', [MemberPortalController::class, 'notices']);
        Route::post('/notices/{id}/read', [MemberPortalController::class, 'markNoticeRead']);
        Route::get('/messages', [MemberMessagesController::class, 'index']);
        Route::get('/messages/threads', [MemberMessagesController::class, 'threads']);
        Route::post('/messages/threads', [MemberMessagesController::class, 'store']);
        Route::post('/messages/threads/read', [MemberMessagesController::class, 'markThreadRead']);
        Route::get('/referral', [MemberPortalController::class, 'referral']);
        Route::post('/referral/email-invites', [MemberPortalController::class, 'sendReferralEmailInvites']);
        Route::post('/check-in', [MemberPortalController::class, 'checkIn']);
        Route::post('/check-out', [MemberPortalController::class, 'checkOut']);
        Route::get('/visit-status', [MemberPortalController::class, 'visitStatus']);
        Route::get('/next-visit', [NextVisitMemberController::class, 'upcoming']);
        Route::post('/next-visit/schedule', [NextVisitMemberController::class, 'schedule']);
        Route::get('/next-visit/threads', [MemberMessagesController::class, 'threads']);
        Route::get('/looks', [MemberLooksController::class, 'index']);
        Route::post('/looks', [MemberLooksController::class, 'store']);
        Route::delete('/looks/{id}', [MemberLooksController::class, 'destroy']);
        Route::post('/logout', [MemberPortalController::class, 'logout']);
    });

    // Module 11 extension — Public ecommerce shop (click-and-collect).
    Route::middleware(['throttle:public-book', 'tenant.resolve', 'tenant.require'])->prefix('shop')->group(function () {
        Route::get('/products', [ShopController::class, 'products']);
        Route::post('/orders', [ShopController::class, 'placeOrder'])->middleware(['ip.ban', 'throttle:public-book-write', 'turnstile']);
    });

    // Gallery + Lookbook public surfaces (feature-gated in controllers).
    Route::middleware(['throttle:public-book', 'tenant.resolve', 'tenant.require'])->group(function () {
        Route::get('/gallery/works', [PublicGalleryController::class, 'index']);
        Route::get('/lookbook/items', [PublicLookbookController::class, 'index']);
    });

    // Module 13A — Public provider webhook intake (HMAC verified in service; rate-limited).
    Route::post('/integrations/webhooks/{driver}', [ProviderWebhookIngestController::class, 'store'])
        ->middleware('throttle:public-webhooks');

    Route::middleware(['auth:sanctum', 'tenant.resolve'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/shell', ShellController::class);
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/upgrade-offer', [UpgradeOfferController::class, 'show']);
        Route::post('/upgrade-offer/claim', [UpgradeOfferController::class, 'claim']);
    });

    // Platform super-admin (cross-tenant; gated by users.is_platform_admin + platform_role).
    Route::middleware(['auth:sanctum', 'platform.admin'])->prefix('platform')->group(function () {
        Route::get('/profile', [PlatformProfileController::class, 'show']);
        Route::put('/profile', [PlatformProfileController::class, 'update']);
        Route::put('/profile/password', [PlatformProfileController::class, 'updatePassword']);

        Route::get('/overview', [PlatformAdminController::class, 'overview']);
        Route::get('/tenants', [PlatformAdminController::class, 'tenants']);
        Route::get('/pwa-users', [PlatformPresenceController::class, 'pwaUsers']);
        Route::get('/billing/invoices', [PlatformAdminController::class, 'invoices']);
        Route::get('/billing/invoices/{id}', [PlatformAdminController::class, 'showInvoice']);

        Route::get('/notifications', [PlatformNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [PlatformNotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [PlatformNotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [PlatformNotificationController::class, 'markRead']);

        Route::get('/modules', [PlatformModuleController::class, 'index']);
        Route::get('/tenants/{id}/modules', [PlatformModuleController::class, 'showTenant']);
        Route::get('/tenants/{id}/booking-policy', [PlatformBookingPolicyController::class, 'show']);

        Route::get('/audit-logs', [PlatformAuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [PlatformAuditLogController::class, 'show']);

        Route::get('/upgrade-campaigns/settings', [PlatformUpgradeCampaignController::class, 'settings']);
        Route::get('/upgrade-campaigns/templates', [PlatformUpgradeCampaignController::class, 'templates']);

        Route::get('/referral-settings', [PlatformReferralSettingController::class, 'show']);
        Route::get('/ai-hairstyle-settings', [PlatformAiHairstyleSettingController::class, 'show']);
        Route::get('/whatsapp-settings', [PlatformWhatsAppSettingsController::class, 'show']);

        Route::get('/signup-forms', [PlatformSignupFormController::class, 'index']);
        Route::get('/signup-forms/{id}', [PlatformSignupFormController::class, 'show']);

        Route::get('/growth-assessments', [PlatformSalonGrowthAssessmentController::class, 'index']);
        Route::get('/growth-assessments/{id}', [PlatformSalonGrowthAssessmentController::class, 'show']);

        Route::middleware('platform.role:owner,manager')->group(function () {
            Route::patch('/growth-assessments/{id}', [PlatformSalonGrowthAssessmentController::class, 'update']);
            Route::put('/growth-assessments/{id}', [PlatformSalonGrowthAssessmentController::class, 'update']);
            Route::post('/tenants/{id}/unlock-tiers', [PlatformAdminController::class, 'unlockTenantTier']);
            Route::post('/tenants/{id}/suspend', [PlatformAdminController::class, 'suspendTenant']);
            Route::post('/tenants/{id}/unsuspend', [PlatformAdminController::class, 'unsuspendTenant']);
            Route::put('/tenants/{id}/owner-email', [PlatformAdminController::class, 'updateTenantOwnerEmail']);
            Route::post('/tenants/{id}/owner-email', [PlatformAdminController::class, 'updateTenantOwnerEmail']);
            Route::put('/tenants/{id}/owner-phone', [PlatformAdminController::class, 'updateTenantOwnerPhone']);
            Route::post('/tenants/{id}/owner-phone', [PlatformAdminController::class, 'updateTenantOwnerPhone']);
            Route::post('/tenants/{id}/impersonate', [PlatformAdminController::class, 'impersonateTenant']);
            Route::post('/tenants/{id}/poke', [PlatformPresenceController::class, 'poke']);
            Route::post('/pwa-users/push', [PlatformPresenceController::class, 'pushPwaUsers']);
            Route::post('/billing/invoices/{id}/mark-paid', [PlatformAdminController::class, 'markInvoicePaid']);
            Route::post('/billing/invoices/{id}/fail-payment', [PlatformAdminController::class, 'failInvoicePayment']);
            Route::post('/billing/process', [PlatformAdminController::class, 'processBilling']);

            Route::put('/plans/{id}/modules', [PlatformModuleController::class, 'updatePlan']);
            Route::put('/tenants/{id}/modules', [PlatformModuleController::class, 'updateTenant']);
            Route::put('/tenants/{id}/booking-policy', [PlatformBookingPolicyController::class, 'update']);

            Route::put('/upgrade-campaigns/settings', [PlatformUpgradeCampaignController::class, 'updateSettings']);
            Route::put('/upgrade-campaigns/templates/{id}', [PlatformUpgradeCampaignController::class, 'updateTemplate']);
            Route::post('/upgrade-campaigns/dispatch', [PlatformUpgradeCampaignController::class, 'dispatchNow']);

            Route::post('/broadcasts', [PlatformTenantBroadcastController::class, 'store']);
            Route::put('/referral-settings', [PlatformReferralSettingController::class, 'update']);
            Route::put('/ai-hairstyle-settings', [PlatformAiHairstyleSettingController::class, 'update']);
            Route::put('/whatsapp-settings', [PlatformWhatsAppSettingsController::class, 'update']);
            Route::post('/whatsapp-settings/test', [PlatformWhatsAppSettingsController::class, 'test']);
            Route::get('/whatsapp-settings/queue', [PlatformWhatsAppSettingsController::class, 'queueStatus']);
            Route::post('/whatsapp-settings/purge', [PlatformWhatsAppSettingsController::class, 'purge']);
            Route::post('/whatsapp-settings/signup-welcome-banner', [PlatformWhatsAppSettingsController::class, 'uploadSignupWelcomeBanner']);
            Route::delete('/whatsapp-settings/signup-welcome-banner', [PlatformWhatsAppSettingsController::class, 'clearSignupWelcomeBanner']);

            Route::post('/signup-forms', [PlatformSignupFormController::class, 'store']);
            Route::put('/signup-forms/{id}', [PlatformSignupFormController::class, 'update']);
            Route::delete('/signup-forms/{id}', [PlatformSignupFormController::class, 'destroy']);
        });

        Route::middleware('platform.role:owner')->group(function () {
            Route::post('/tenants/{id}/purge', [PlatformAdminController::class, 'purgeTenant']);
            Route::get('/staff', [PlatformStaffController::class, 'index']);
            Route::post('/staff', [PlatformStaffController::class, 'store']);
            Route::put('/staff/{id}', [PlatformStaffController::class, 'update']);
            Route::put('/staff/{id}/password', [PlatformStaffController::class, 'updatePassword']);
            Route::delete('/staff/{id}', [PlatformStaffController::class, 'destroy']);
        });
    });

    Route::middleware(['auth:sanctum', 'tenant.resolve', 'team.member'])->prefix('admin')->group(function () {
        Route::middleware('permission:identity.view')->group(function () {
            Route::get('/organization', [OrganizationController::class, 'show']);
            Route::get('/branding', [BrandingController::class, 'show']);
            Route::get('/locations', [LocationController::class, 'index']);
            Route::get('/locations/{id}', [LocationController::class, 'show']);
            Route::get('/workspaces', [WorkspaceController::class, 'index']);
            Route::get('/workspaces/{id}', [WorkspaceController::class, 'show']);
            Route::get('/team-members', [TeamMemberController::class, 'index']);
            Route::get('/team-members/{id}', [TeamMemberController::class, 'show']);
            Route::get('/roles', [RoleController::class, 'index']);
            Route::get('/roles/{id}', [RoleController::class, 'show']);
            Route::get('/permissions', [RoleController::class, 'permissions']);
            Route::get('/subscription', [SubscriptionController::class, 'show']);
            Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
            Route::get('/owner-notices', [TenantOwnerNoticeController::class, 'index']);
            Route::post('/owner-notices/{id}/read', [TenantOwnerNoticeController::class, 'markRead']);
            Route::get('/platform-referral', [TenantPlatformReferralController::class, 'show']);
            Route::post('/presence/heartbeat', [TenantPresenceController::class, 'heartbeat']);
        });

        Route::middleware('permission:identity.manage')->group(function () {
            Route::put('/organization', [OrganizationController::class, 'update']);
            Route::put('/branding', [BrandingController::class, 'update']);
            Route::post('/branding/upload-emblem', [BrandingController::class, 'uploadEmblem']);
            Route::post('/branding/upload-hero', [BrandingController::class, 'uploadHeroImage']);
            Route::post('/branding/upload-logo', [BrandingController::class, 'uploadLogo']);
            Route::post('/locations', [LocationController::class, 'store']);
            Route::put('/locations/{id}', [LocationController::class, 'update']);
            Route::patch('/locations/{id}/status', [LocationController::class, 'updateStatus']);
            Route::post('/workspaces', [WorkspaceController::class, 'store']);
            Route::put('/workspaces/{id}', [WorkspaceController::class, 'update']);
            Route::patch('/workspaces/{id}/status', [WorkspaceController::class, 'updateStatus']);
            Route::post('/team-members', [TeamMemberController::class, 'store']);
            Route::put('/team-members/{id}', [TeamMemberController::class, 'update']);
            Route::patch('/team-members/{id}/status', [TeamMemberController::class, 'updateStatus']);
            Route::post('/subscription/change-plan', [SubscriptionController::class, 'changePlan']);
            Route::post('/owner-push-subscriptions', [TenantOwnerPushSubscriptionController::class, 'store']);
            Route::delete('/owner-push-subscriptions', [TenantOwnerPushSubscriptionController::class, 'destroy']);
        });

        Route::middleware('permission:identity.access.manage')->group(function () {
            Route::post('/roles', [RoleController::class, 'store']);
            Route::put('/roles/{id}', [RoleController::class, 'update']);
            Route::patch('/roles/{id}/archive', [RoleController::class, 'archive']);
            Route::put('/roles/{id}/permissions', [RoleController::class, 'updatePermissions']);
            Route::put('/team-members/{id}/roles', [RoleController::class, 'updateTeamMemberRoles']);
        });

        Route::middleware('permission:crm.view')->group(function () {
            Route::get('/clients', [ClientController::class, 'index']);
            Route::get('/clients/{id}', [ClientController::class, 'show']);
            Route::get('/clients/{id}/export', [ClientController::class, 'export']);
            Route::get('/clients/{id}/visits', [ClientVisitController::class, 'index']);
            Route::get('/visits/open', [ClientVisitController::class, 'open']);
            Route::get('/crm/tags', [ClientTagController::class, 'index']);
            Route::get('/clients/{clientId}/notes', [ClientNoteController::class, 'index']);
            Route::get('/clients/{clientId}/consents', [ClientConsentController::class, 'index']);
            Route::get('/clients/{clientId}/timeline', [ClientTimelineController::class, 'index']);
            Route::get('/clients/{clientId}/formulas', [ClientFormulaController::class, 'index']);
            Route::get('/clients/{clientId}/formulas/{id}', [ClientFormulaController::class, 'show']);
            Route::get('/clients/{clientId}/photos', [ClientPhotoController::class, 'index']);
            Route::get('/clients/{clientId}/documents', [ClientDocumentController::class, 'index']);
            Route::get('/messages/conversations', [AdminMessagesController::class, 'conversations']);
            Route::get('/clients/{clientId}/threads', [ClientThreadController::class, 'index']);
        });

        Route::middleware('permission:crm.manage')->group(function () {
            Route::post('/clients', [ClientController::class, 'store']);
            Route::post('/clients/import/preview', [ClientImportController::class, 'preview']);
            Route::post('/clients/import', [ClientImportController::class, 'store']);
            Route::put('/clients/{id}', [ClientController::class, 'update']);
            Route::patch('/clients/{id}/status', [ClientController::class, 'updateStatus']);
            Route::post('/clients/{id}/erase', [ClientController::class, 'erase']);
            Route::post('/clients/{clientId}/threads', [ClientThreadController::class, 'store']);
            Route::post('/clients/{clientId}/threads/read', [ClientThreadController::class, 'markRead']);
            Route::post('/crm/tags', [ClientTagController::class, 'store']);
            Route::put('/clients/{clientId}/tags', [ClientTagController::class, 'syncClientTags']);
            Route::post('/clients/{clientId}/notes', [ClientNoteController::class, 'store']);
            Route::post('/clients/{clientId}/consents', [ClientConsentController::class, 'store']);
            Route::post('/clients/{clientId}/formulas', [ClientFormulaController::class, 'store']);
            Route::put('/clients/{clientId}/formulas/{id}', [ClientFormulaController::class, 'update']);
            Route::patch('/clients/{clientId}/formulas/{id}/archive', [ClientFormulaController::class, 'archive']);
            Route::post('/clients/{clientId}/photos', [ClientPhotoController::class, 'store']);
            Route::patch('/clients/{clientId}/photos/{id}/archive', [ClientPhotoController::class, 'archive']);
            Route::post('/clients/{clientId}/documents', [ClientDocumentController::class, 'store']);
            Route::patch('/clients/{clientId}/documents/{id}/archive', [ClientDocumentController::class, 'archive']);
        });

        Route::middleware('permission:staff.view')->group(function () {
            Route::get('/staff', [StaffProviderController::class, 'index']);
            Route::get('/staff/{teamMemberId}', [StaffProviderController::class, 'show']);
            Route::get('/staff/{teamMemberId}/availability', [StaffAvailabilityController::class, 'index']);
            Route::get('/staff/{teamMemberId}/absences', [StaffAbsenceController::class, 'index']);
        });

        Route::middleware('permission:staff.manage')->group(function () {
            Route::put('/staff/{teamMemberId}/profile', [StaffProfileController::class, 'update']);
            Route::put('/staff/{teamMemberId}/operating-scope', [StaffProfileController::class, 'updateOperatingScope']);
            Route::post('/staff/{teamMemberId}/availability', [StaffAvailabilityController::class, 'store']);
            Route::put('/staff/{teamMemberId}/availability/{id}', [StaffAvailabilityController::class, 'update']);
            Route::patch('/staff/{teamMemberId}/availability/{id}/archive', [StaffAvailabilityController::class, 'archive']);
            Route::post('/staff/{teamMemberId}/absences', [StaffAbsenceController::class, 'store']);
            Route::put('/staff/{teamMemberId}/absences/{id}', [StaffAbsenceController::class, 'update']);
            Route::patch('/staff/{teamMemberId}/absences/{id}/cancel', [StaffAbsenceController::class, 'cancel']);
        });

        Route::middleware('permission:booking.view')->group(function () {
            Route::get('/booking-services', [BookableServiceController::class, 'index']);
            Route::get('/appointments', [AppointmentController::class, 'index']);
            Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
            Route::get('/appointments/{id}/checkout-import', [AppointmentCommerceController::class, 'checkoutImport']);
            Route::get('/appointments/{id}/eligible-packages', [AppointmentPackageController::class, 'eligiblePackages']);
            Route::get('/booking-board/day', [BookingBoardController::class, 'day']);
            Route::get('/walk-ins', [WalkInController::class, 'index']);
            Route::get('/recurrence-series/{id}', [RecurrenceSeriesController::class, 'show']);
            Route::get('/waitlist', [WaitlistController::class, 'index']);
            Route::get('/waitlist/{id}', [WaitlistController::class, 'show']);
            Route::get('/reviews', [SalonReviewController::class, 'index']);
            Route::get('/staff-sos-alerts', [StaffSosAlertController::class, 'index']);
        });

        Route::middleware('permission:booking.manage')->group(function () {
            Route::post('/booking-services', [BookableServiceController::class, 'store']);
            Route::post('/booking-services/upload-image', [BookableServiceController::class, 'uploadImage']);
            Route::put('/booking-services/{id}', [BookableServiceController::class, 'update']);
            Route::patch('/booking-services/{id}/archive', [BookableServiceController::class, 'archive']);
            Route::put('/reviews/{id}', [SalonReviewController::class, 'update']);
            Route::delete('/reviews/{id}', [SalonReviewController::class, 'destroy']);
            Route::post('/appointments', [AppointmentController::class, 'store']);
            Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
            Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
            Route::patch('/appointments/{id}/correct-status', [AppointmentController::class, 'correctStatus']);
            Route::post('/appointments/{id}/rebook', [AppointmentController::class, 'rebook']);
            Route::patch('/appointments/{id}/workspace', [AppointmentController::class, 'reassignWorkspace']);
            Route::patch('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
            Route::patch('/appointments/{id}/deposit-status', [AppointmentController::class, 'updateDepositStatus']);
            Route::get('/booking-policy', [BookingChangeRequestController::class, 'policy']);
            Route::get('/booking-change-requests', [BookingChangeRequestController::class, 'index']);
            Route::get('/booking-change-requests/{id}', [BookingChangeRequestController::class, 'show']);
            Route::post('/booking-change-requests/{id}/accept', [BookingChangeRequestController::class, 'accept']);
            Route::post('/booking-change-requests/{id}/decline', [BookingChangeRequestController::class, 'decline']);
            Route::post('/appointments/{id}/postpone-request', [BookingChangeRequestController::class, 'postpone']);
            Route::post('/appointments/{appointmentId}/service-lines/{serviceLineId}/package-reserve', [AppointmentPackageController::class, 'reservePackage']);
            Route::post('/appointments/{appointmentId}/service-lines/{serviceLineId}/package-release', [AppointmentPackageController::class, 'releasePackage']);
            Route::post('/walk-ins', [WalkInController::class, 'store']);
            Route::patch('/walk-ins/{id}/seat', [WalkInController::class, 'seat']);
            Route::post('/recurrence-series', [RecurrenceSeriesController::class, 'store']);
            Route::patch('/recurrence-series/{id}/cancel', [RecurrenceSeriesController::class, 'cancel']);
            Route::post('/waitlist', [WaitlistController::class, 'store']);
            Route::put('/waitlist/{id}', [WaitlistController::class, 'update']);
            Route::post('/waitlist/{id}/fulfill', [WaitlistController::class, 'fulfill']);
            Route::post('/staff-sos-alerts/{id}/acknowledge', [StaffSosAlertController::class, 'acknowledge']);
            Route::post('/staff-sos-alerts/{id}/shift', [StaffSosAlertController::class, 'shift']);
        });

        Route::middleware('permission:payments.reporting.view')->group(function () {
            Route::get('/payments/summary', [PaymentReportingController::class, 'summary']);
            Route::get('/payments/failed', [PaymentReportingController::class, 'failed']);
            Route::get('/payments/deposits', [PaymentReportingController::class, 'deposits']);
        });

        Route::middleware('permission:payments.view')->group(function () {
            Route::get('/payments', [PaymentTransactionController::class, 'index']);
            Route::get('/payments/settings', [TenantPaymentsSettingsController::class, 'show']);
            Route::get('/payments/reservation-documents', [ReservationPaymentDocumentController::class, 'index']);
            Route::get('/payments/reservation-documents/{id}', [ReservationPaymentDocumentController::class, 'show']);
            Route::get('/payments/{id}', [PaymentTransactionController::class, 'show']);
            Route::get('/payments/{id}/refunds', [PaymentRefundController::class, 'index']);
            Route::get('/appointments/{id}/deposit', [DepositPaymentController::class, 'show']);
        });

        Route::middleware('permission:payments.manage')->group(function () {
            Route::put('/payments/settings', [TenantPaymentsSettingsController::class, 'update']);
            Route::post('/payments/reservation-documents/{id}/confirm', [ReservationPaymentDocumentController::class, 'confirm']);
            Route::post('/payments/reservation-documents/{id}/reject', [ReservationPaymentDocumentController::class, 'reject']);
            Route::post('/payments/manual', [PaymentTransactionController::class, 'storeManual']);
            Route::post('/payments/payment-link', [PaymentTransactionController::class, 'storePaymentLink']);
            Route::post('/payments/{id}/mark-succeeded', [PaymentTransactionController::class, 'markSucceeded']);
            Route::post('/payments/{id}/mark-failed', [PaymentTransactionController::class, 'markFailed']);
            Route::post('/payments/{id}/cancel', [PaymentTransactionController::class, 'cancel']);
            Route::post('/appointments/{id}/deposit/pay', [DepositPaymentController::class, 'pay']);
            Route::post('/appointments/{id}/deposit/waive', [DepositPaymentController::class, 'waive']);
        });

        Route::middleware('permission:payments.refund')->group(function () {
            Route::post('/payments/{id}/refunds', [PaymentRefundController::class, 'store']);
            Route::post('/appointments/{id}/deposit/refund', [DepositPaymentController::class, 'refund']);
        });

        Route::middleware('permission:inventory.reporting.view')->group(function () {
            Route::get('/inventory/levels', [InventoryLevelController::class, 'index']);
        });

        Route::middleware('permission:inventory.view')->group(function () {
            Route::get('/inventory/items', [InventoryItemController::class, 'index']);
            Route::get('/inventory/items/{id}', [InventoryItemController::class, 'show']);
            Route::get('/inventory/items/{id}/levels', [InventoryLevelController::class, 'forItem']);
            Route::get('/inventory/suppliers', [InventorySupplierController::class, 'index']);
            Route::get('/inventory/suppliers/{id}', [InventorySupplierController::class, 'show']);
            Route::get('/inventory/movements', [InventoryMovementController::class, 'index']);
            Route::get('/inventory/service-consumption-rules', [ServiceConsumptionRuleController::class, 'index']);
        });

        Route::middleware('permission:inventory.manage')->group(function () {
            Route::post('/inventory/items', [InventoryItemController::class, 'store']);
            Route::put('/inventory/items/{id}', [InventoryItemController::class, 'update']);
            Route::patch('/inventory/items/{id}/archive', [InventoryItemController::class, 'archive']);
            Route::patch('/inventory/items/{id}/activate', [InventoryItemController::class, 'activate']);
            Route::put('/inventory/items/{id}/levels/{locationId}', [InventoryLevelController::class, 'update']);
            Route::post('/inventory/suppliers', [InventorySupplierController::class, 'store']);
            Route::put('/inventory/suppliers/{id}', [InventorySupplierController::class, 'update']);
            Route::patch('/inventory/suppliers/{id}/archive', [InventorySupplierController::class, 'archive']);
            Route::patch('/inventory/suppliers/{id}/activate', [InventorySupplierController::class, 'activate']);
            Route::post('/inventory/service-consumption-rules', [ServiceConsumptionRuleController::class, 'store']);
            Route::put('/inventory/service-consumption-rules/{id}', [ServiceConsumptionRuleController::class, 'update']);
            Route::patch('/inventory/service-consumption-rules/{id}/archive', [ServiceConsumptionRuleController::class, 'archive']);
        });

        Route::middleware('permission:inventory.adjust')->group(function () {
            Route::post('/inventory/movements', [InventoryMovementController::class, 'store']);
            Route::post('/inventory/consume', [InventoryConsumptionController::class, 'consume']);
        });

        Route::middleware('permission:ecommerce.view')->group(function () {
            Route::get('/ecommerce/products', [EcommerceProductController::class, 'index']);
            Route::get('/ecommerce/products/{id}', [EcommerceProductController::class, 'show']);
            Route::get('/ecommerce/orders', [EcommerceOrderController::class, 'index']);
            Route::get('/ecommerce/orders/{id}', [EcommerceOrderController::class, 'show']);
        });

        Route::middleware('permission:ecommerce.manage')->group(function () {
            Route::post('/ecommerce/products', [EcommerceProductController::class, 'store']);
            Route::put('/ecommerce/products/{id}', [EcommerceProductController::class, 'update']);
            Route::patch('/ecommerce/products/{id}/status', [EcommerceProductController::class, 'updateStatus']);
            Route::patch('/ecommerce/orders/{id}/status', [EcommerceOrderController::class, 'updateStatus']);
        });

        Route::middleware('permission:money.view')->group(function () {
            Route::get('/money/summary', [MoneyNotebookController::class, 'summary']);
            Route::get('/money/ledger', [MoneyNotebookController::class, 'ledger']);
        });

        Route::middleware('permission:money.manage')->group(function () {
            Route::post('/money/entries', [MoneyNotebookController::class, 'store']);
            Route::delete('/money/entries/{id}', [MoneyNotebookController::class, 'destroy']);
        });

        Route::middleware('permission:gallery.view')->group(function () {
            Route::get('/gallery/works', [GalleryWorkController::class, 'index']);
        });

        Route::middleware('permission:gallery.manage')->group(function () {
            Route::post('/gallery/works', [GalleryWorkController::class, 'store']);
            Route::post('/gallery/works/upload-image', [GalleryWorkController::class, 'uploadImage']);
            Route::post('/gallery/works/reorder', [GalleryWorkController::class, 'reorder']);
            Route::put('/gallery/works/{id}', [GalleryWorkController::class, 'update']);
            Route::delete('/gallery/works/{id}', [GalleryWorkController::class, 'destroy']);
        });

        Route::middleware('permission:lookbook.view')->group(function () {
            Route::get('/lookbook/items', [LookbookItemController::class, 'index']);
        });

        Route::middleware('permission:lookbook.manage')->group(function () {
            Route::put('/lookbook/items/{id}', [LookbookItemController::class, 'update']);
            Route::post('/lookbook/items/reorder', [LookbookItemController::class, 'reorder']);
            Route::post('/lookbook/items/{id}/replace-image', [LookbookItemController::class, 'replaceImage']);
            Route::post('/lookbook/items/{id}/hide', [LookbookItemController::class, 'hide']);
            Route::post('/lookbook/items/{id}/publish', [LookbookItemController::class, 'publish']);
        });

        Route::middleware('permission:ai_hairstyle.view')->group(function () {
            Route::get('/ai-hairstyle/sessions', [AdminAiHairstyleController::class, 'index']);
        });

        Route::middleware('permission:ai_hairstyle.manage')->group(function () {
            Route::post('/ai-hairstyle/sessions/{id}/accept', [AdminAiHairstyleController::class, 'accept']);
            Route::post('/ai-hairstyle/sessions/{id}/decline', [AdminAiHairstyleController::class, 'decline']);
        });

        Route::middleware('permission:next_visit.view')->group(function () {
            Route::get('/next-visit/upcoming', [NextVisitController::class, 'upcoming']);
        });

        Route::middleware('permission:next_visit.manage')->group(function () {
            Route::post('/next-visit/nudge', [NextVisitController::class, 'nudge']);
        });

        Route::middleware('permission:pos.view')->group(function () {
            Route::get('/pos/checkouts', [CheckoutController::class, 'index']);
            Route::get('/pos/checkouts/{id}', [CheckoutController::class, 'show']);
            Route::get('/pos/checkouts/{id}/payments', [CheckoutController::class, 'listPayments']);
            Route::get('/pos/checkouts/{id}/refunds', [CheckoutAdvancedController::class, 'listRefunds']);
            Route::get('/pos/checkouts/{id}/receipts', [CheckoutAdvancedController::class, 'listReceipts']);
            Route::get('/pos/checkouts/{id}/membership-options', [CheckoutMembershipController::class, 'membershipOptions']);
            Route::get('/pos/gift-cards/{code}', [CheckoutAdvancedController::class, 'lookupGiftCard']);
            Route::get('/pos/catalog/services', [PosCatalogController::class, 'services']);
            Route::get('/pos/catalog/retail', [PosCatalogController::class, 'retail']);
            Route::get('/pos/appointments/eligible', [PosCatalogController::class, 'eligibleAppointments']);
        });

        Route::middleware('permission:pos.manage')->group(function () {
            Route::post('/pos/checkouts', [CheckoutController::class, 'store']);
            Route::put('/pos/checkouts/{id}', [CheckoutController::class, 'update']);
            Route::post('/pos/checkouts/{id}/lines/service', [CheckoutController::class, 'addServiceLine']);
            Route::post('/pos/checkouts/{id}/lines/retail', [CheckoutController::class, 'addRetailLine']);
            Route::put('/pos/checkouts/{id}/lines/{lineId}', [CheckoutController::class, 'updateLine']);
            Route::delete('/pos/checkouts/{id}/lines/{lineId}', [CheckoutController::class, 'removeLine']);
            Route::post('/pos/checkouts/{id}/import-appointment', [CheckoutController::class, 'importAppointment']);
            Route::post('/pos/checkouts/{id}/apply-deposit-credit', [CheckoutController::class, 'applyDepositCredit']);
            Route::delete('/pos/checkouts/{id}/deposit-credit', [CheckoutController::class, 'removeDepositCredit']);
            Route::post('/pos/checkouts/{id}/payments', [CheckoutController::class, 'recordPayments']);
            Route::post('/pos/checkouts/{id}/lines/gift-card', [CheckoutController::class, 'addGiftCardLine']);
            Route::post('/pos/checkouts/{id}/apply-gift-card', [CheckoutAdvancedController::class, 'applyGiftCard']);
            Route::delete('/pos/checkouts/{id}/gift-card', [CheckoutAdvancedController::class, 'removeGiftCard']);
            Route::post('/pos/checkouts/{id}/returns', [CheckoutAdvancedController::class, 'processReturn']);
            Route::post('/pos/checkouts/{id}/apply-wallet', [CheckoutMembershipController::class, 'applyWallet']);
            Route::post('/pos/checkouts/{id}/remove-wallet', [CheckoutMembershipController::class, 'removeWallet']);
            Route::post('/pos/checkouts/{id}/apply-loyalty', [CheckoutMembershipController::class, 'applyLoyalty']);
            Route::post('/pos/checkouts/{id}/remove-loyalty', [CheckoutMembershipController::class, 'removeLoyalty']);
            Route::post('/pos/checkouts/{id}/lines/{lineId}/apply-package', [CheckoutMembershipController::class, 'applyPackage']);
            Route::post('/pos/checkouts/{id}/lines/{lineId}/remove-package', [CheckoutMembershipController::class, 'removePackage']);
        });

        Route::middleware('permission:pos.refund')->group(function () {
            Route::post('/pos/checkouts/{id}/refunds', [CheckoutAdvancedController::class, 'createRefund']);
        });

        Route::middleware('permission:pos.checkout.reopen')->group(function () {
            Route::post('/pos/checkouts/{id}/reopen', [CheckoutAdvancedController::class, 'reopen']);
        });

        Route::middleware('permission:pos.receipt.manage')->group(function () {
            Route::post('/pos/checkouts/{id}/receipts/resend', [CheckoutAdvancedController::class, 'resendReceipt']);
        });

        Route::middleware('permission:pos.checkout.complete')->group(function () {
            Route::post('/pos/checkouts/{id}/complete', [CheckoutController::class, 'complete']);
            Route::post('/pos/checkouts/{id}/void', [CheckoutController::class, 'void']);
        });

        Route::middleware('permission:memberships.view')->group(function () {
            Route::get('/memberships/plans', [MembershipPlanController::class, 'index']);
            Route::get('/memberships/plans/{id}', [MembershipPlanController::class, 'show']);
            Route::get('/memberships/packages', [PackageProductController::class, 'index']);
            Route::get('/memberships/packages/{id}', [PackageProductController::class, 'show']);
            Route::get('/memberships/client-memberships', [ClientMembershipController::class, 'index']);
            Route::get('/memberships/client-memberships/{id}', [ClientMembershipController::class, 'show']);
            Route::get('/memberships/wallet-entries', [WalletLedgerController::class, 'index']);
            Route::get('/memberships/clients/{clientId}/wallet', [WalletLedgerController::class, 'clientBalance']);
            Route::get('/memberships/loyalty-entries', [LoyaltyLedgerController::class, 'index']);
            Route::get('/memberships/clients/{clientId}/loyalty', [LoyaltyLedgerController::class, 'clientBalance']);
            Route::get('/memberships/client-packages', [ClientPackageController::class, 'index']);
            Route::get('/memberships/client-packages/{id}', [ClientPackageController::class, 'show']);
            Route::get('/memberships/clients/{clientId}/eligible-packages', [MembershipCommerceController::class, 'eligiblePackages']);
            Route::get('/memberships/clients/{clientId}/wallet-summary', [MembershipCommerceController::class, 'walletSummary']);
            Route::get('/memberships/clients/{clientId}/loyalty-summary', [MembershipCommerceController::class, 'loyaltySummary']);
            Route::get('/memberships/settings/loyalty-redemption', [MembershipCommerceController::class, 'showLoyaltySettings']);
        });

        Route::middleware('permission:memberships.manage')->group(function () {
            Route::post('/memberships/plans', [MembershipPlanController::class, 'store']);
            Route::put('/memberships/plans/{id}', [MembershipPlanController::class, 'update']);
            Route::patch('/memberships/plans/{id}/archive', [MembershipPlanController::class, 'archive']);
            Route::post('/memberships/packages', [PackageProductController::class, 'store']);
            Route::put('/memberships/packages/{id}', [PackageProductController::class, 'update']);
            Route::patch('/memberships/packages/{id}/archive', [PackageProductController::class, 'archive']);
            Route::post('/memberships/client-memberships', [ClientMembershipController::class, 'store']);
            Route::put('/memberships/client-memberships/{id}', [ClientMembershipController::class, 'update']);
            Route::patch('/memberships/client-memberships/{id}/pause', [ClientMembershipController::class, 'pause']);
            Route::patch('/memberships/client-memberships/{id}/resume', [ClientMembershipController::class, 'resume']);
            Route::patch('/memberships/client-memberships/{id}/cancel', [ClientMembershipController::class, 'cancel']);
            Route::post('/memberships/wallet-entries', [WalletLedgerController::class, 'store']);
            Route::post('/memberships/loyalty-entries', [LoyaltyLedgerController::class, 'store']);
            Route::post('/memberships/client-packages', [ClientPackageController::class, 'store']);
            Route::post('/memberships/client-packages/{id}/redeem', [ClientPackageController::class, 'redeem']);
            Route::post('/memberships/client-packages/{id}/restore', [ClientPackageController::class, 'restore']);
            Route::put('/memberships/settings/loyalty-redemption', [MembershipCommerceController::class, 'updateLoyaltySettings']);
        });

        Route::middleware('permission:memberships.reporting.view')->group(function () {
            Route::get('/memberships/summary', [MembershipSummaryController::class, 'show']);
        });

        Route::middleware('permission:marketing.view')->group(function () {
            Route::get('/marketing/templates', [MarketingTemplateController::class, 'index']);
            Route::get('/marketing/templates/{id}', [MarketingTemplateController::class, 'show']);
            Route::get('/marketing/audiences', [MarketingAudienceController::class, 'index']);
            Route::get('/marketing/audiences/{id}', [MarketingAudienceController::class, 'show']);
            Route::get('/marketing/campaigns', [MarketingCampaignController::class, 'index']);
            Route::get('/marketing/campaigns/{id}', [MarketingCampaignController::class, 'show']);
            Route::get('/marketing/settings', [MarketingAutomationController::class, 'showSettings']);
            Route::get('/marketing/runs', [MarketingRunController::class, 'index']);
            Route::get('/marketing/runs/{id}', [MarketingRunController::class, 'show']);
            Route::get('/marketing/runs/{id}/messages', [MarketingRunController::class, 'messages']);
            Route::get('/marketing/workflows', [MarketingWorkflowController::class, 'index']);
            Route::get('/marketing/workflows/{id}', [MarketingWorkflowController::class, 'show']);
            Route::get('/marketing/workflows/{id}/executions', [MarketingWorkflowController::class, 'executions']);
            Route::get('/marketing/executions', [MarketingWorkflowExecutionController::class, 'index']);
            Route::get('/marketing/executions/{id}', [MarketingWorkflowExecutionController::class, 'show']);
            Route::get('/marketing/messages', [MarketingMessageController::class, 'index']);
            Route::get('/marketing/messages/{id}', [MarketingMessageController::class, 'show']);
            Route::get('/marketing/suppressions', [MarketingSuppressionController::class, 'index']);
        });

        Route::middleware('permission:marketing.manage')->group(function () {
            Route::post('/marketing/templates', [MarketingTemplateController::class, 'store']);
            Route::post('/marketing/templates/install-samples', [MarketingTemplateController::class, 'installSamples']);
            Route::put('/marketing/templates/{id}', [MarketingTemplateController::class, 'update']);
            Route::patch('/marketing/templates/{id}/archive', [MarketingTemplateController::class, 'archive']);
            Route::post('/marketing/templates/{id}/preview', [MarketingTemplateController::class, 'preview']);
            Route::post('/marketing/audiences', [MarketingAudienceController::class, 'store']);
            Route::post('/marketing/audiences/preview', [MarketingAudienceController::class, 'preview']);
            Route::put('/marketing/audiences/{id}', [MarketingAudienceController::class, 'update']);
            Route::patch('/marketing/audiences/{id}/archive', [MarketingAudienceController::class, 'archive']);
            Route::post('/marketing/campaigns', [MarketingCampaignController::class, 'store']);
            Route::put('/marketing/campaigns/{id}', [MarketingCampaignController::class, 'update']);
            Route::patch('/marketing/campaigns/{id}/status', [MarketingCampaignController::class, 'updateStatus']);
            Route::put('/marketing/settings', [MarketingAutomationController::class, 'updateSettings']);
            Route::post('/marketing/workflows', [MarketingWorkflowController::class, 'store']);
            Route::put('/marketing/workflows/{id}', [MarketingWorkflowController::class, 'update']);
            Route::patch('/marketing/workflows/{id}/status', [MarketingWorkflowController::class, 'updateStatus']);
            Route::put('/marketing/workflows/{id}/steps', [MarketingWorkflowController::class, 'updateSteps']);
            Route::post('/marketing/workflows/{id}/steps', [MarketingWorkflowController::class, 'addStep']);
            Route::put('/marketing/workflows/{id}/steps/reorder', [MarketingWorkflowController::class, 'reorderSteps']);
            Route::put('/marketing/workflows/{id}/steps/{stepId}', [MarketingWorkflowController::class, 'updateStep']);
            Route::delete('/marketing/workflows/{id}/steps/{stepId}', [MarketingWorkflowController::class, 'deleteStep']);
            Route::patch('/marketing/workflows/{id}/steps/{stepId}/archive', [MarketingWorkflowController::class, 'deleteStep']);
            Route::post('/marketing/suppressions', [MarketingSuppressionController::class, 'store']);
            Route::patch('/marketing/suppressions/{id}/lift', [MarketingSuppressionController::class, 'lift']);
            Route::patch('/marketing/suppressions/{id}/deactivate', [MarketingSuppressionController::class, 'deactivate']);
            Route::patch('/marketing/suppressions/{id}/reactivate', [MarketingSuppressionController::class, 'reactivate']);
        });

        Route::middleware('permission:marketing.dispatch')->group(function () {
            Route::post('/marketing/runs/broadcast-preview', [MarketingRunController::class, 'broadcastPreview']);
            Route::post('/marketing/runs/broadcast-dispatch', [MarketingRunController::class, 'broadcastDispatch']);
            Route::post('/marketing/runs/booking-reminders/generate', [MarketingRunController::class, 'generateBookingReminders']);
            Route::post('/marketing/runs/rebooking/generate', [MarketingRunController::class, 'generateRebooking']);
            Route::post('/marketing/runs/review-requests/generate', [MarketingRunController::class, 'generateReviewRequests']);
            Route::post('/marketing/runs/win-back/generate', [MarketingRunController::class, 'generateWinBack']);
            Route::post('/marketing/runs/{id}/dispatch', [MarketingRunController::class, 'dispatch']);
            Route::post('/marketing/workflows/{id}/run-test', [MarketingWorkflowController::class, 'runTest']);
            Route::patch('/marketing/executions/{id}/cancel', [MarketingWorkflowExecutionController::class, 'cancel']);
            Route::post('/marketing/executions/process', [MarketingWorkflowExecutionController::class, 'process']);
            Route::post('/marketing/automations/run-birthday', [MarketingWorkflowExecutionController::class, 'runBirthday']);
            Route::post('/marketing/messages/{id}/mark-delivered', [MarketingMessageController::class, 'markDelivered']);
            Route::post('/marketing/messages/{id}/mark-opened', [MarketingMessageController::class, 'markOpened']);
            Route::post('/marketing/messages/{id}/mark-clicked', [MarketingMessageController::class, 'markClicked']);
            Route::post('/marketing/messages/{id}/mark-failed', [MarketingMessageController::class, 'markFailed']);
            Route::post('/marketing/messages/{id}/unsubscribe', [MarketingMessageController::class, 'unsubscribe']);
        });

        Route::middleware('permission:marketing.reporting.view')->group(function () {
            Route::get('/marketing/reporting/summary', [MarketingReportingController::class, 'summary']);
            Route::get('/marketing/reporting/campaigns', [MarketingReportingController::class, 'campaigns']);
            Route::get('/marketing/reporting/runs', [MarketingReportingController::class, 'runs']);
            Route::get('/marketing/reporting/automations/summary', [MarketingReportingController::class, 'automationSummary']);
            Route::get('/marketing/reporting/automations/workflows', [MarketingReportingController::class, 'automationWorkflows']);
            Route::get('/marketing/reporting/automations/executions', [MarketingReportingController::class, 'automationExecutions']);
            Route::get('/marketing/reporting/automations/messages', [MarketingReportingController::class, 'automationMessages']);
            Route::get('/marketing/reporting/automations/suppressions', [MarketingReportingController::class, 'automationSuppressions']);
            // Spec-aligned aliases (/reports/automation/*)
            Route::get('/marketing/reports/automation/summary', [MarketingReportingController::class, 'automationSummary']);
            Route::get('/marketing/reports/automation/workflows', [MarketingReportingController::class, 'automationWorkflows']);
            Route::get('/marketing/reports/automation/messages', [MarketingReportingController::class, 'automationMessages']);
            Route::get('/marketing/reports/automation/suppressions', [MarketingReportingController::class, 'automationSuppressions']);
        });

        // Module 11A — Notifications & Communications Foundation
        Route::middleware('permission:notifications.view')->group(function () {
            Route::get('/notifications/templates', [NotificationTemplateController::class, 'index']);
            Route::get('/notifications/templates/{id}', [NotificationTemplateController::class, 'show']);
            Route::get('/notifications/messages', [NotificationMessageController::class, 'index']);
            Route::get('/notifications/messages/{id}', [NotificationMessageController::class, 'show']);
            Route::get('/notifications/preferences/{clientId}', [NotificationPreferenceController::class, 'show']);
            Route::get('/notifications/settings', [NotificationSettingController::class, 'show']);
            Route::get('/notifications/timeline/clients/{clientId}', [NotificationTimelineController::class, 'show']);
        });

        Route::middleware('permission:notifications.manage')->group(function () {
            Route::post('/notifications/templates', [NotificationTemplateController::class, 'store']);
            Route::post('/notifications/templates/install-samples', [NotificationTemplateController::class, 'installSamples']);
            Route::put('/notifications/templates/{id}', [NotificationTemplateController::class, 'update']);
            Route::patch('/notifications/templates/{id}/archive', [NotificationTemplateController::class, 'archive']);
            Route::post('/notifications/messages/manual', [NotificationMessageController::class, 'storeManual']);
            Route::post('/notifications/messages/desk', [NotificationMessageController::class, 'storeDesk']);
            Route::post('/notifications/messages/{id}/cancel', [NotificationMessageController::class, 'cancel']);
            Route::post('/notifications/messages/{id}/mark-delivered', [NotificationMessageController::class, 'markDelivered']);
            Route::put('/notifications/preferences/{clientId}', [NotificationPreferenceController::class, 'update']);
            Route::post('/notifications/preferences/{clientId}/sync-from-consent', [NotificationPreferenceController::class, 'syncFromConsent']);
            Route::put('/notifications/settings', [NotificationSettingController::class, 'update']);
        });

        Route::middleware('permission:notifications.reporting.view')->group(function () {
            Route::get('/notifications/reporting/summary', [NotificationReportingController::class, 'summary']);
            Route::get('/notifications/reporting/failures', [NotificationReportingController::class, 'failures']);
            Route::get('/notifications/reporting/by-purpose', [NotificationReportingController::class, 'byPurpose']);
        });

        Route::middleware('permission:analytics.view')->group(function () {
            Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
            Route::get('/analytics/bookings', [AnalyticsController::class, 'bookings']);
            Route::get('/analytics/revenue', [AnalyticsController::class, 'revenue']);
            Route::get('/analytics/clients', [AnalyticsController::class, 'clients']);
            Route::get('/analytics/inventory', [AnalyticsController::class, 'inventory']);
            Route::get('/analytics/communications', [AnalyticsController::class, 'communications']);
            Route::get('/analytics/intelligence', [AnalyticsController::class, 'intelligence']);
        });

        // Module 12B — Saved reports + export jobs (read + manage).
        Route::middleware('permission:analytics.exports.manage')->group(function () {
            Route::get('/analytics/saved-reports', [AnalyticsSavedReportController::class, 'index']);
            Route::post('/analytics/saved-reports', [AnalyticsSavedReportController::class, 'store']);
            Route::get('/analytics/saved-reports/{id}', [AnalyticsSavedReportController::class, 'show']);
            Route::put('/analytics/saved-reports/{id}', [AnalyticsSavedReportController::class, 'update']);
            Route::patch('/analytics/saved-reports/{id}/archive', [AnalyticsSavedReportController::class, 'archive']);
            Route::post('/analytics/saved-reports/{id}/run', [AnalyticsSavedReportController::class, 'run']);

            Route::get('/analytics/exports', [AnalyticsExportController::class, 'index']);
            Route::post('/analytics/exports', [AnalyticsExportController::class, 'store']);
            Route::get('/analytics/exports/{id}', [AnalyticsExportController::class, 'show']);
            Route::get('/analytics/exports/{id}/download', [AnalyticsExportController::class, 'download']);
        });

        // Module 13A — Provider integrations foundation.
        Route::middleware('permission:integrations.view')->group(function () {
            Route::get('/integrations/provider-accounts', [ProviderAccountController::class, 'index']);
            Route::get('/integrations/provider-accounts/{id}', [ProviderAccountController::class, 'show']);
            Route::get('/integrations/provider-attempts', [ProviderDeliveryAttemptController::class, 'index']);
            Route::get('/integrations/provider-attempts/{id}', [ProviderDeliveryAttemptController::class, 'show']);
            Route::get('/integrations/provider-events', [ProviderWebhookEventController::class, 'index']);
            Route::get('/integrations/provider-events/{id}', [ProviderWebhookEventController::class, 'show']);
            Route::get('/integrations/whatsapp', [TenantWhatsAppSettingsController::class, 'show']);
        });

        Route::middleware('permission:integrations.manage')->group(function () {
            Route::post('/integrations/provider-accounts', [ProviderAccountController::class, 'store']);
            Route::put('/integrations/provider-accounts/{id}', [ProviderAccountController::class, 'update']);
            Route::patch('/integrations/provider-accounts/{id}/activate', [ProviderAccountController::class, 'activate']);
            Route::patch('/integrations/provider-accounts/{id}/deactivate', [ProviderAccountController::class, 'deactivate']);
            Route::patch('/integrations/provider-accounts/{id}/archive', [ProviderAccountController::class, 'archive']);
            Route::patch('/integrations/provider-accounts/{id}/set-default', [ProviderAccountController::class, 'setDefault']);
            Route::post('/integrations/provider-accounts/{id}/test', [ProviderAccountController::class, 'test']);
            Route::post('/integrations/provider-attempts/{id}/retry', [ProviderDeliveryAttemptController::class, 'retry']);
            Route::post('/integrations/whatsapp/session/init', [TenantWhatsAppSettingsController::class, 'initSession']);
            Route::post('/integrations/whatsapp/session/activate', [TenantWhatsAppSettingsController::class, 'activateSession']);
            Route::post('/integrations/whatsapp/session/refresh', [TenantWhatsAppSettingsController::class, 'refreshSession']);
            Route::post('/integrations/whatsapp/session/disconnect', [TenantWhatsAppSettingsController::class, 'disconnectSession']);
        });
    });
});

