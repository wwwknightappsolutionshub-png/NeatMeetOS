<?php

namespace App\Jobs;

use App\Domains\GrowthAssessment\Models\SalonGrowthAssessment;
use App\Domains\GrowthAssessment\Services\SalonGrowthAssessmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deliver assessment email/WhatsApp after the HTTP response so submit stays fast.
 */
class DeliverSalonGrowthAssessmentResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 120];

    public int $timeout = 60;

    public function __construct(
        public string $assessmentId,
        public bool $sendWhatsApp = false,
    ) {}

    public static function dispatchAfterResponse(string $assessmentId, bool $sendWhatsApp): void
    {
        $job = new self($assessmentId, $sendWhatsApp);

        if (app()->runningUnitTests()) {
            $job->handle(app(SalonGrowthAssessmentService::class));

            return;
        }

        dispatch(function () use ($job) {
            $job->handle(app(SalonGrowthAssessmentService::class));
        })->afterResponse();
    }

    public function handle(SalonGrowthAssessmentService $assessments): void
    {
        $assessment = SalonGrowthAssessment::query()->find($this->assessmentId);
        if ($assessment === null) {
            return;
        }

        $assessments->deliverOutbound($assessment, $this->sendWhatsApp);
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('growth_assessment.delivery_job_failed', [
            'assessment_id' => $this->assessmentId,
            'error' => $e?->getMessage(),
        ]);
    }
}
