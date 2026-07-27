<?php

namespace Tests\Unit\Services;

use App\Services\Push\FcmDriver;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Mockery;
use Tests\TestCase;

class FcmDriverTest extends TestCase
{
    public function test_not_found_token_is_classified_as_definitively_invalid(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')
            ->once()
            ->andThrow(NotFound::becauseTokenNotFound('invalid-token'));

        $this->assertFalse(
            (new FcmDriver($messaging))->send('invalid-token', 'Title', 'Body')
        );
    }

    public function test_transient_messaging_failure_propagates_for_queue_retry(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')
            ->once()
            ->andThrow(new ApiConnectionFailed('Temporary connection failure.'));

        $this->expectException(ApiConnectionFailed::class);

        (new FcmDriver($messaging))->send('valid-token', 'Title', 'Body');
    }
}
