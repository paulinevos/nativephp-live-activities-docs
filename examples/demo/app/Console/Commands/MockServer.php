<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class MockServer extends Command
{
    protected $signature = 'demo:mock {--port=8787 : Loopback port}';

    protected $description = 'Serve the deterministic delivery fixture without an account';

    public function handle(): int
    {
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
        if ($port === false) {
            $this->error('Port must be between 1024 and 65535.');

            return self::FAILURE;
        }
        $router = dirname(base_path()).'/mock-server/router.php';
        $this->info('Mock server: http://127.0.0.1:'.$port);
        $process = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, $router], timeout: null);

        return $process->run(fn (string $type, string $text) => $this->output->write($text));
    }
}
