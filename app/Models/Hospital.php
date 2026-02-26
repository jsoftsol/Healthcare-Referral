<?php
namespace App\Models;

use App\Enums\HospitalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Hospital extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'status',
        'api_key_hash',
    ];

    protected $hidden = [
        'api_key_hash',
    ];

    protected $casts = [
        'status' => HospitalStatus::class,
    ];

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function isActive(): bool
    {
        return $this->status === HospitalStatus::Active;
    }

    public static function generateApiKey(): array
    {
        $plaintext = 'hsp_' . Str::random(40);
        $hash      = hash('sha256', $plaintext);

        return ['plaintext' => $plaintext, 'hash' => $hash];
    }

    public function verifyApiKey(string $plaintext): bool
    {
        return hash_equals($this->api_key_hash, hash('sha256', $plaintext));
    }
}
