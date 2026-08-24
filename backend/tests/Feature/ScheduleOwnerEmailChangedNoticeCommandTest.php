<?php

namespace Tests\Feature;

use App\Jobs\SendOwnerEmailChangedNoticeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleOwnerEmailChangedNoticeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_delayed_notices_for_default_recipients(): void
    {
        Queue::fake();

        $this->artisan('platform:schedule-email-changed-notice', ['--delay' => 10])
            ->assertSuccessful();

        Queue::assertPushed(SendOwnerEmailChangedNoticeJob::class, 2);
        Queue::assertPushed(SendOwnerEmailChangedNoticeJob::class, function (SendOwnerEmailChangedNoticeJob $job) {
            return in_array($job->recipientEmail, ['bcindy87@yahoo.com', 'beacadmedia@gmail.com'], true)
                && $job->loginEmail === 'bcindy87@yahoo.com'
                && $job->applicationName === 'NeatMeet Saloon'
                && $job->delay !== null;
        });
    }
}
