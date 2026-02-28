<?php
namespace App\Http\Resources\Staff;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'message'     => $this->message,
            'channel'     => $this->channel->value,
            'referral_id' => $this->referral_id,
            'sent_at'     => $this->sent_at?->toIso8601String(),
            'read_at'     => $this->read_at?->toIso8601String(),
            'is_read'     => $this->isRead(),
        ];
    }
}
