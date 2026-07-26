<?php

namespace App\Services;

use RuntimeException;

class DeploymentProcessStarter
{
    /**
     * Start the deploy script independently from the PHP-FPM request.
     *
     * The script itself owns the lock, so concurrent GitHub deliveries are
     * serialized and the request can safely return immediately.
     */
    public function start(): void
    {
        $script = base_path('scripts/deploy-on-push.sh');

        if (! is_file($script) || ! is_executable($script)) {
            throw new RuntimeException('Deployment script is unavailable.');
        }

        $command = 'nohup '.escapeshellarg($script).' > /dev/null 2>&1 &';

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Unable to start the deployment process.');
        }
    }
}
