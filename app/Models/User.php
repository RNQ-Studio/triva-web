<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $name
 * @property string $email
 * @property string|null $google_sub
 * @property string|null $phone
 * @property string|null $city
 * @property string|null $avatar
 * @property Carbon|null $service_consent_at
 * @property bool $marketing_consent
 * @property Carbon|null $marketing_consent_updated_at
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'google_sub',
    'password',
    'is_active',
    'avatar',
    'phone',
    'city',
    'service_consent_at',
    'marketing_consent',
    'marketing_consent_updated_at',
    'email_verified_at',
    'phone_verified_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    protected string $guard_name = 'web';

    /** Roles allowed to access the back-office panel. */
    public const PANEL_ROLES = ['super-admin', 'admin', 'staff'];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole(self::PANEL_ROLES);
    }

    /** @return HasMany<UserDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** @return HasMany<Appraisal, $this> */
    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class);
    }

    /** @return HasMany<ToyotaServiceBooking, $this> */
    public function toyotaServiceBookings(): HasMany
    {
        return $this->hasMany(ToyotaServiceBooking::class);
    }

    /** @return HasMany<ToyotaServiceBooking, $this> */
    public function assignedToyotaServiceBookings(): HasMany
    {
        return $this->hasMany(ToyotaServiceBooking::class, 'assigned_service_advisor_id');
    }

    /** @return HasMany<OtoxpertBooking, $this> */
    public function otoxpertBookings(): HasMany
    {
        return $this->hasMany(OtoxpertBooking::class);
    }

    /** @return HasMany<OtoxpertBooking, $this> */
    public function assignedOtoxpertBookings(): HasMany
    {
        return $this->hasMany(OtoxpertBooking::class, 'assigned_operator_id');
    }

    /** @return BelongsToMany<OtoxpertWorkshop, $this> */
    public function otoxpertWorkshops(): BelongsToMany
    {
        return $this->belongsToMany(
            OtoxpertWorkshop::class,
            'otoxpert_workshop_operators',
            'user_id',
            'workshop_id',
        )->withPivot('is_active')->withTimestamps();
    }

    /** @return HasMany<CustomerConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function avatarAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'avatar');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'service_consent_at' => 'datetime',
            'marketing_consent' => 'boolean',
            'marketing_consent_updated_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Find the user instance for the given username.
     */
    public function findForPassport(string $username): ?User
    {
        return $this->where('email', $username)
            ->orWhere('phone', $username)
            ->first();
    }

    /**
     * Validate the password for the Passport password grant.
     */
    public function validateForPassportPasswordGrant(#[\SensitiveParameter] string $password): bool
    {
        $cacheKey = 'otp_login_token_'.$this->getKey();
        $cachedToken = cache($cacheKey);

        if ($cachedToken !== null && hash_equals($cachedToken, $password)) {
            cache()->forget($cacheKey);

            return true;
        }

        return Hash::check($password, $this->password);
    }
}
