<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Simulator;
use Illuminate\Console\Command;

final class RunIos extends Command
{
    protected $signature = 'demo:ios {--udid= : iPhone simulator UDID}';

    protected $description = 'Bundle the demo, build it, and launch an iPhone simulator';

    public function handle(Simulator $simulator): int
    {
        $id = $simulator->boot($this->option('udid'));
        if (! is_dir(base_path('nativephp/ios'))) {
            $status = $this->call('native:install', ['platform' => 'ios', '--no-interaction' => true]);
            if ($status !== self::SUCCESS) {
                return $status;
            }
        }
        $status = $this->call('native:run', ['os' => 'ios', 'udid' => $id, '--no-tty' => true, '--no-interaction' => true, '--start-url' => '/']);
        if ($status !== self::SUCCESS) {
            return $status;
        }
        $simulator->run(['xcrun', 'simctl', 'get_app_container', $id, config('nativephp.app_id'), 'app']);
        $simulator->run(['open', '-a', 'Simulator']);
        $this->info('Demo installed on '.$id);

        return self::SUCCESS;
    }
}
