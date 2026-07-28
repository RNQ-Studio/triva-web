<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $appraisal_id
 * @property int|null $market_data_source_id
 * @property string $phase
 * @property string $status
 * @property string $model
 * @property string $prompt_version
 * @property string $input_hash
 * @property string|null $response_id
 * @property int $candidate_count
 * @property int $accepted_count
 * @property list<array{url: string, title: string|null}>|null $sources
 * @property array<string, mixed>|null $usage
 * @property array<string, mixed>|null $output
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
class AppraisalAiAgentRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'phase',
        'status',
        'model',
        'prompt_version',
        'input_hash',
        'response_id',
        'candidate_count',
        'accepted_count',
        'sources',
        'usage',
        'output',
        'error_code',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'candidate_count' => 'integer',
            'accepted_count' => 'integer',
            'sources' => 'array',
            'usage' => 'array',
            'output' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Appraisal, $this> */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /** @return BelongsTo<MarketDataSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketDataSource::class, 'market_data_source_id');
    }
}
