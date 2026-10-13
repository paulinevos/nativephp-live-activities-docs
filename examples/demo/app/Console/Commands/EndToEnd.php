<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AndroidDevice;
use App\Support\Simulator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class EndToEnd extends Command
{
    protected $signature = 'demo:e2e {--platform=ios : ios or android} {--serial= : Android device serial} {--udid= : iPhone simulator UDID} {--timeout=60 : Maximum wait in seconds}';

    protected $description = 'Drive an installed simulator app through the mock delivery and verify native state';

    public function handle(Simulator $simulator, AndroidDevice $android): int
    {
        $url = rtrim(config('demo.mock_url'), '/');
        $health = Http::timeout(3)->get($url.'/health')->throw()->json();
        if (($health['service'] ?? '') !== 'live-activities-mock') {
            throw new RuntimeException('Start the mock server with php bin/demo mock.');
        }
        if (! in_array($this->option('platform'), ['ios', 'android'], true)) {
            throw new RuntimeException('Choose --platform=ios or --platform=android.');
        }
        if ($this->option('platform') === 'android') {
            $id = $android->select($this->option('serial'));
            $android->connectMock($id);
            $android->launch($id);
        } else {
            $id = $simulator->boot($this->option('udid'));
            $simulator->run(['xcrun', 'simctl', 'get_app_container', $id, config('nativephp.app_id'), 'app']);
            $simulator->run(['xcrun', 'simctl', 'launch', $id, config('nativephp.app_id')]);
        }
        $run = Http::timeout(3)->post($url.'/runs?automated=1')->throw()->json('id');

        return $this->awaitResults($url, $run);
    }

    private function awaitResults(string $url, string $run): int
    {
        $deadline = microtime(true) + max(1, min(300, (int) $this->option('timeout')));
        while (microtime(true) < $deadline) {
            $events = Http::timeout(3)->get($url.'/runs/'.$run)->throw()->json('events');
            foreach ($events as $event) {
                if ($event['operation'] === 'error') {
                    throw new RuntimeException('Simulator reported: '.$event['message']);
                }
            }
            if (count($events) === 4) {
                $this->verify($events, $run);
                $this->info('PASS: native start, two updates, and end verified. Run '.$run);

                return self::SUCCESS;
            }
            usleep(250000);
        }
        $this->error('Timed out waiting for simulator observations. Run '.$run);

        return self::FAILURE;
    }

    private function verify(array $events, string $run): void
    {
        if (array_column($events, 'operation') !== ['start', 'update', 'update', 'end']) {
            throw new RuntimeException('Unexpected lifecycle sequence.');
        }
        $expected = Http::timeout(3)->get(rtrim(config('demo.mock_url'), '/').'/scenario')->throw()->json('steps');
        $nativeId = null;
        foreach (array_slice($events, 0, 3) as $index => $event) {
            $activities = array_values(array_filter($event['activities'], fn (array $activity): bool => $activity['key'] === 'mock-'.$run));
            if (count($activities) !== 1 || $activities[0]['content'] != $expected[$index]['content']) {
                throw new RuntimeException('Native activity content did not match step '.$index);
            }
            $nativeId ??= $activities[0]['id'];
            if ($activities[0]['id'] !== $nativeId || $activities[0]['state'] !== 'active') {
                throw new RuntimeException('Activity identity or state changed unexpectedly.');
            }
        }
        foreach ($events[3]['activities'] as $activity) {
            if ($activity['id'] === $nativeId) {
                throw new RuntimeException('Ended activity is still listed.');
            }
        }
    }
}
