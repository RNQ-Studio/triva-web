<?php

namespace Tests\Feature\Webhook;

use App\Services\DeploymentProcessStarter;
use Mockery;
use Tests\TestCase;

class GithubDeployWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['deployment.github_webhook_secret' => 'webhook-test-secret']);
    }

    public function test_valid_main_push_starts_deployment(): void
    {
        $starter = Mockery::mock(DeploymentProcessStarter::class);
        $starter->shouldReceive('start')->once();
        $this->app->instance(DeploymentProcessStarter::class, $starter);

        $response = $this->postWebhook(['ref' => 'refs/heads/main']);

        $response
            ->assertAccepted()
            ->assertJson(['message' => 'Deployment queued.']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $response = $this->postJson('/api/deploy/github', ['ref' => 'refs/heads/main'], [
            'X-GitHub-Event' => 'push',
            'X-Hub-Signature-256' => 'sha256=invalid',
        ]);

        $response->assertUnauthorized();
    }

    public function test_unconfigured_webhook_is_unavailable(): void
    {
        config(['deployment.github_webhook_secret' => null]);

        $starter = Mockery::mock(DeploymentProcessStarter::class);
        $starter->shouldNotReceive('start');
        $this->app->instance(DeploymentProcessStarter::class, $starter);

        $response = $this->postJson('/api/deploy/github', ['ref' => 'refs/heads/main']);

        $response->assertServiceUnavailable();
    }

    public function test_other_branches_do_not_start_deployment(): void
    {
        $starter = Mockery::mock(DeploymentProcessStarter::class);
        $starter->shouldNotReceive('start');
        $this->app->instance(DeploymentProcessStarter::class, $starter);

        $response = $this->postWebhook(['ref' => 'refs/heads/develop']);

        $response
            ->assertAccepted()
            ->assertJson(['message' => 'Webhook received; deployment is not required for this event.']);
    }

    /** @param array<string, string> $payload */
    private function postWebhook(array $payload)
    {
        $content = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/deploy/github', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $content, 'webhook-test-secret'),
        ], $content);
    }
}
