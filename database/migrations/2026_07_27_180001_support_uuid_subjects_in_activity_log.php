<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection(config('activitylog.database_connection'));
        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(
                'ALTER TABLE activity_log
                 ALTER COLUMN subject_id TYPE VARCHAR(64)
                 USING subject_id::text'
            );
        }
    }

    public function down(): void
    {
        $connection = DB::connection(config('activitylog.database_connection'));
        if ($connection->getDriverName() === 'pgsql') {
            $hasUuidSubjects = $connection->table('activity_log')
                ->whereNotNull('subject_id')
                ->whereRaw("subject_id !~ '^[0-9]+$'")
                ->exists();
            if ($hasUuidSubjects) {
                throw new LogicException(
                    'Cannot roll activity_log.subject_id back to BIGINT while UUID subjects exist.'
                );
            }

            $connection->statement(
                'ALTER TABLE activity_log
                 ALTER COLUMN subject_id TYPE BIGINT
                 USING subject_id::bigint'
            );
        }
    }
};
