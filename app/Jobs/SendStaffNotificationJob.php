<?php
namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Models\Staff;
use App\Models\StaffNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendStaffNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly StaffNotification $notification
    ) {}

    public function handle(): void
    {
        $staff = $this->notification->staff;

        if (! $staff->isAvailable()) {
            $this->release(60); // retry in 60 seconds
            return;
        }

        match ($this->notification->channel) {
            NotificationChannel::Email => $this->sendEmail($staff),
            NotificationChannel::Sms   => $this->sendSms($staff),
            NotificationChannel::InApp => $this->markSent(),
        };

        $this->notification->update(['sent_at' => now()]);
    }

    private function sendEmail(Staff $staff): void
    {
        // In a real system: Mail::to($staff->email)->queue(new ReferralNotificationMail($this->notification));
        // Keeping as a stub to show the pattern without adding mail dependency complexity
        $this->markSent();
    }

    private function sendSms(Staff $staff): void
    {
        // In a real system: SMS facade/service call here
        $this->markSent();
    }

    private function markSent(): void
    {
        $this->notification->update(['sent_at' => now()]);
    }
}
