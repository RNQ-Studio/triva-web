<?php

namespace App\Support\Enums;

enum StorageType: string
{
    case Local = 'local';
    case PrivateLocal = 'private_local';
    case Gcs = 'gcs';

    /** Nama disk Laravel (config/filesystems.php) untuk tiap storage type. */
    public function disk(): string
    {
        return match ($this) {
            self::Local => 'public',
            self::PrivateLocal => 'local',
            self::Gcs => 'gcs',
        };
    }
}
