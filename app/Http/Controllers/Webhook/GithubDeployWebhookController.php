<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\DeploymentProcessStarter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GithubDeployWebhookController extends Controller
{
    public function __invoke(Request $request, DeploymentProcessStarter $deployment): JsonResponse
    {
        $secret = config('deployment.github_webhook_secret');

        if (! is_string($secret) || $secret === '') {
            abort(503, 'GitHub webhook is not configured.');
        }

        if (! $this->hasValidSignature($request, $secret)) {
            abort(401, 'Invalid GitHub webhook signature.');
        }

        if ($request->header('X-GitHub-Event') !== 'push' || $request->input('ref') !== 'refs/heads/main') {
            return response()->json([
                'message' => 'Webhook received; deployment is not required for this event.',
            ], 202);
        }

        try {
            $deployment->start();
        } catch (RuntimeException $exception) {
            report($exception);

            abort(503, 'Deployment process is unavailable.');
        }

        return response()->json([
            'message' => 'Deployment queued.',
        ], 202);
    }

    private function hasValidSignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! is_string($signature) || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
