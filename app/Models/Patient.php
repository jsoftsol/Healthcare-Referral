<?php
namespace App\Models;

use App\Traits\EncryptsPii;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use EncryptsPii, HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'date_of_birth',
        'national_id',
        'national_id_hash',
        'insurance_number',
    ];

    /**
     * These fields are encrypted at rest via EncryptsPii trait.
     *
     * @var array<string>
     */
    protected array $piiFields = [
        'first_name',
        'last_name',
        'date_of_birth',
        'national_id',
        'insurance_number',
    ];

    /**
     * Prevent PII from appearing in serialized output for logs.
     *
     * @var array<string>
     */
    protected $hidden = [
        'first_name',
        'last_name',
        'date_of_birth',
        'national_id',
        'national_id_hash',
        'insurance_number',
    ];

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /**
     * Find a patient by their national ID using the deterministic hash.
     */
    public static function findByNationalId(string $nationalId): ?static
    {
        $hash = hash_hmac('sha256', $nationalId, config('referral.patient_id_hmac_key'));

        return static::where('national_id_hash', $hash)->first();
    }
}
