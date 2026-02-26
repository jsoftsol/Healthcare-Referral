<?php
namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait EncryptsPii
{
    public static function bootEncryptsPii(): void
    {
        static::creating(function (self $model): void {
            $model->setNationalIdHash();
            $model->encryptPiiFields();
        });

        static::updating(function (self $model): void {
            $model->setNationalIdHash();
            $model->encryptPiiFields();
        });
    }

    protected function encryptPiiFields(): void
    {
        foreach ($this->piiFields ?? [] as $field) {
            if ($this->isDirty($field) && $this->attributes[$field] !== null) {
                $this->attributes[$field] = Crypt::encryptString(
                    (string) $this->attributes[$field]
                );
            }
        }
    }

    protected function setNationalIdHash(): void
    {
        if (! array_key_exists('national_id', $this->attributes)) {
            return;
        }

        if (! in_array('national_id_hash', $this->getFillable(), true)) {
            return;
        }

        if (! $this->isDirty('national_id')) {
            return;
        }

        $plainNationalId = $this->getOriginal('national_id') ?? $this->attributes['national_id'];

        if ($plainNationalId === null) {
            $this->attributes['national_id_hash'] = null;
            return;
        }

        $this->attributes['national_id_hash'] = hash_hmac(
            'sha256',
            (string) $plainNationalId,
            config('referral.patient_id_hmac_key', config('app.key'))
        );
    }

    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->piiFields ?? [], strict: true) && $value !== null) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception) {
                return $value;
            }
        }

        return $value;
    }

    public function toSafeArray(): array
    {
        return ['id' => $this->id, 'type' => class_basename($this)];
    }
}
