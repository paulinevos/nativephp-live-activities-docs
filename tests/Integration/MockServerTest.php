<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class MockServerTest extends TestCase
{
    private Process $server;

    private Client $http;

    private string $state;

    protected function setUp(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->state = sys_get_temp_dir().'/live-activities-mock-'.bin2hex(random_bytes(8));
        $this->server = new Process([PHP_BINARY, '-S', $address, dirname(__DIR__, 2).'/examples/mock-server/router.php'], env: ['MOCK_STATE_DIR' => $this->state]);
        $this->server->start();
        $this->http = new Client(['base_uri' => 'http://'.$address, 'http_errors' => false, 'timeout' => 1]);
        for ($attempt = 0; $attempt < 40; $attempt++) {
            try {
                if ($this->http->get('/health')->getStatusCode() === 200) {
                    return;
                }
            } catch (ConnectException) {
                usleep(25000);
            }
        }
        $this->fail('Mock server failed to start: '.$this->server->getErrorOutput());
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        (new Filesystem)->deleteDirectory($this->state);
    }

    public function test_scenario_and_observations_are_deterministic_and_isolated(): void
    {
        $scenario = $this->json('GET', '/scenario');
        $this->assertSame(['start', 'update', 'update', 'end'], array_column($scenario['steps'], 'operation'));
        $first = $this->json('POST', '/runs')['id'];
        $second = $this->json('POST', '/runs')['id'];
        $event = ['operation' => 'start', 'activities' => [['id' => 'native-123']]];
        $this->http->post('/runs/'.$first.'/events', ['json' => $event]);
        $this->assertSame([$event], $this->json('GET', '/runs/'.$first)['events']);
        $this->assertSame([], $this->json('GET', '/runs/'.$second)['events']);
        $this->assertSame($scenario, $this->json('GET', '/scenario'));
    }

    public function test_invalid_events_and_unknown_runs_do_not_mutate_state(): void
    {
        $run = $this->json('POST', '/runs')['id'];
        $this->assertSame(422, $this->http->post('/runs/'.$run.'/events', ['body' => '{'])->getStatusCode());
        $this->assertSame(422, $this->http->post('/runs/'.$run.'/events', ['json' => ['operation' => 'delete']])->getStatusCode());
        $this->assertSame(413, $this->http->post('/runs/'.$run.'/events', ['body' => str_repeat('x', 32769)])->getStatusCode());
        $this->assertSame(404, $this->http->get('/runs/'.str_repeat('0', 32))->getStatusCode());
        $this->assertSame([], $this->json('GET', '/runs/'.$run)['events']);
    }

    public function test_only_the_pending_run_can_clear_the_automated_trigger(): void
    {
        $this->assertNull($this->json('GET', '/pending')['id']);
        $manual = $this->json('POST', '/runs')['id'];
        $this->assertNull($this->json('GET', '/pending')['id']);
        $automated = $this->json('POST', '/runs?automated=1')['id'];
        $this->http->post('/runs/'.$manual.'/events', ['json' => ['operation' => 'end']]);
        $this->assertSame($automated, $this->json('GET', '/pending')['id']);
        $this->http->post('/runs/'.$automated.'/events', ['json' => ['operation' => 'error']]);
        $this->assertNull($this->json('GET', '/pending')['id']);
    }

    private function json(string $method, string $path): array
    {
        return json_decode((string) $this->http->request($method, $path)->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
