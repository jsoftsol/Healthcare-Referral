<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Staff\NotificationResource;
use App\Models\StaffNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends BaseController
{
    /**
     * PATCH /api/v1/staff/notifications/{notification}/acknowledge
     */
    public function acknowledge(Request $request, StaffNotification $notification): JsonResponse
    {
        // Ensure the notification belongs to the authenticated staff
        if ($notification->staff_id !== $request->user()->id) {
            return $this->error('Not found.', 404);
        }

        $notification->markAsRead();

        return $this->success(
            new NotificationResource($notification),
            'Notification acknowledged.'
        );
    }
}
