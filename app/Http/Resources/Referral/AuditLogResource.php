<?php
namespace App\Http\Resources\Referral;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'action'       => $this->action,
            'field_name'   => $this->field_name,
            'old_value'    => $this->old_value,
            'new_value'    => $this->new_value,
            'metadata'     => $this->metadata,
            'performed_by' => $this->whenLoaded('performer', fn() => [
                'id'   => $this->performer->id,
                'name' => $this->performer->name,
                'role' => $this->performer->role->value,
            ], null),
            'created_at'   => $this->created_at->toIso8601String(),
        ];
    }
}
