<?php

namespace App\Models;

use App\Models\Concerns\LogsToyotaServiceActivity;
use App\Support\Enums\BenefitVerificationSource;
use App\Support\Enums\VehicleBenefitStatus;
use App\Support\Enums\VehicleBenefitType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $vehicle_id
 * @property string|null $service_booking_id
 * @property VehicleBenefitType $benefit_type
 * @property VehicleBenefitStatus $status
 * @property Carbon|null $valid_until
 * @property BenefitVerificationSource|null $verification_source
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $notes
 */
class VehicleBenefitCheck extends Model
{
    use HasUuids, LogsToyotaServiceActivity;

    protected $fillable = [
        'benefit_type',
        'status',
        'valid_until',
        'verification_source',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'benefit_type' => VehicleBenefitType::class,
            'status' => VehicleBenefitStatus::class,
            'valid_until' => 'datetime',
            'verification_source' => BenefitVerificationSource::class,
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<ToyotaServiceBooking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(ToyotaServiceBooking::class, 'service_booking_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
