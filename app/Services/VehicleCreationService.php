<?php

namespace App\Services;

use App\Exceptions\VehicleConflictException;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VehicleCreationService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{vehicle: Vehicle, replayed: bool}
     */
    public function create(User $user, array $data, ?string $idempotencyKey): array
    {
        if ($idempotencyKey === null) {
            return [
                'vehicle' => $this->persist($user, $data),
                'replayed' => false,
            ];
        }

        $fingerprint = $this->fingerprint($data);
        $existing = $this->findByKey($user, $idempotencyKey);
        if ($existing !== null) {
            return $this->replay($existing, $fingerprint);
        }

        try {
            return DB::transaction(function () use (
                $user,
                $data,
                $idempotencyKey,
                $fingerprint,
            ): array {
                $existing = $this->findByKey($user, $idempotencyKey, true);
                if ($existing !== null) {
                    return $this->replay($existing, $fingerprint);
                }

                return [
                    'vehicle' => $this->persist($user, [
                        ...$data,
                        'creation_idempotency_key' => $idempotencyKey,
                        'creation_idempotency_fingerprint' => $fingerprint,
                    ]),
                    'replayed' => false,
                ];
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = $this->findByKey($user, $idempotencyKey);
            if ($existing !== null) {
                return $this->replay($existing, $fingerprint);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function persist(User $user, array $data): Vehicle
    {
        $vehicle = new Vehicle($data);
        $vehicle->user()->associate($user);
        $vehicle->save();

        return $vehicle;
    }

    private function findByKey(
        User $user,
        string $idempotencyKey,
        bool $lock = false,
    ): ?Vehicle {
        $query = Vehicle::query()
            ->where('user_id', $user->getKey())
            ->where('creation_idempotency_key', $idempotencyKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @return array{vehicle: Vehicle, replayed: true} */
    private function replay(Vehicle $vehicle, string $fingerprint): array
    {
        if (! hash_equals(
            (string) $vehicle->creation_idempotency_fingerprint,
            $fingerprint,
        )) {
            throw new VehicleConflictException(
                'Idempotency-Key sudah digunakan untuk payload kendaraan yang berbeda.',
                'VEHICLE_IDEMPOTENCY_CONFLICT',
            );
        }

        return ['vehicle' => $vehicle, 'replayed' => true];
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(array $data): string
    {
        ksort($data);

        return hash('sha256', (string) json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505';
    }
}
