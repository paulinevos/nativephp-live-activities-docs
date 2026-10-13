<?php

declare(strict_types=1);

namespace App\Native;

use Illuminate\Support\Facades\Http;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\Elements\Column;
use Native\Mobile\Edge\Elements\Pressable;
use Native\Mobile\Edge\Elements\Text;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\System;
use RuntimeException;
use Throwable;
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\ActivityIcon;
use Vos\LiveActivities\Bridge\Transport;
use Vos\LiveActivities\Facades\LiveActivities;

final class ActivityDemo extends NativeComponent
{
    public string $status = 'Run a delivery without an account';

    public string $runId = '';

    public string $activityId = '';

    public int $step = 0;

    public bool $playing = false;

    public bool $previewing = false;

    public string $notificationStatus = 'Allow notifications before starting. Live Updates also need system support and permission.';

    public function mount(?string $run = null): void
    {
        if ($run === null) {
            return;
        }
        if (! preg_match('/^[a-f0-9]{32}$/', $run)) {
            $this->status = 'Invalid mock run';

            return;
        }
        try {
            $preview = Http::timeout(3)->get($this->url('/runs/'.$run))->throw()->json('preview') === true;
            $this->runId = $run;
            $this->playing = true;
            if ($preview) {
                $this->startPreview();
            }
        } catch (Throwable $exception) {
            $this->status = 'Cannot load mock run: '.$exception->getMessage();
        }
    }

    public function begin(): void
    {
        if ($this->playing || $this->previewing) {
            return;
        }
        $this->cleanup();
        try {
            $this->refreshNotificationStatus();
            $this->runId = Http::timeout(3)->post($this->url('/runs'))->throw()->json('id');
            $this->step = 0;
            $this->playing = true;
        } catch (Throwable $exception) {
            $this->status = 'Start the mock server first: '.$exception->getMessage();
        }
    }

    public function preview(): void
    {
        if ($this->playing || $this->previewing) {
            return;
        }
        $this->begin();
        if ($this->playing) {
            $this->startPreview();
        }
    }

    private function startPreview(): void
    {
        $this->previewing = true;
        $this->playing = false;
        $this->performStep();
    }

    public function nextState(): void
    {
        if ($this->previewing && $this->step < 3) {
            $this->performStep();
        }
    }

    public function endPreview(): void
    {
        if (! $this->previewing) {
            return;
        }
        $this->step = 3;
        $this->performStep();
    }

    #[Poll(2000)]
    public function advance(): void
    {
        if ($this->previewing) {
            return;
        }
        if (! $this->playing) {
            $this->checkPendingRun();

            return;
        }
        $this->performStep();
    }

    private function performStep(): void
    {
        try {
            $steps = Http::timeout(3)->get($this->url('/scenario'))->throw()->json('steps');
            $step = $steps[$this->step];
            $this->applyStep($step);
            $this->report(['operation' => $step['operation'], 'activities' => LiveActivities::all()->toArray()]);
            $this->step++;
            $this->playing = ! $this->previewing && $this->step < count($steps);
        } catch (Throwable $exception) {
            $this->playing = false;
            $this->previewing = false;
            $this->status = $exception->getMessage();
            $this->cleanup();
            try {
                $this->report(['operation' => 'error', 'message' => $this->status]);
            } catch (Throwable $reportError) {
                logger()->error('Mock server report failed', ['message' => $reportError->getMessage()]);
            }
        }
    }

    private function applyStep(array $step): void
    {
        if ($step['operation'] === 'end') {
            LiveActivities::end($this->activityId);
            $this->activityId = '';
            $this->previewing = false;
            $this->status = 'Delivery complete — activity ended';

            return;
        }
        $content = ActivityContent::make($step['content']['title'])
            ->subtitle($step['content']['subtitle'])
            ->progress($step['content']['progress']);
        $content = match (true) {
            $content->progress >= 1 => $content->icon(ActivityIcon::CircleCheck)->color('#22C55E'),
            $content->progress > 0 => $content->icon(ActivityIcon::Truck)->color('#06B6D4'),
            default => $content->icon(ActivityIcon::Utensils)->color('#F97316'),
        };
        $activity = $step['operation'] === 'start'
            ? LiveActivities::start('mock-'.$this->runId, $content)
            : LiveActivities::update($this->activityId, $content);
        $this->activityId = $activity->id;
        $this->status = $step['content']['title'];
    }

    private function report(array $event): void
    {
        Http::timeout(3)->post($this->url('/runs/'.$this->runId.'/events'), $event)->throw();
    }

    private function cleanup(): void
    {
        if ($this->activityId === '') {
            return;
        }
        try {
            LiveActivities::end($this->activityId);
            $this->activityId = '';
        } catch (Throwable $exception) {
            logger()->warning('Could not clean up demo activity', ['message' => $exception->getMessage()]);
        }
    }

    private function checkPendingRun(): void
    {
        try {
            $id = Http::timeout(1)->get($this->url('/pending'))->throw()->json('id');
            if (is_string($id) && $id !== $this->runId) {
                $this->step = 0;
                $this->mount($id);
            }
        } catch (Throwable) {
            // The demo remains usable before its optional local server starts.
        }
    }

    private function url(string $path): string
    {
        return rtrim(config('demo.mock_url'), '/').$path;
    }

    public function requestNotifications(): void
    {
        $this->notificationAction('DemoUI.RequestNotifications');
    }

    public function notificationSettings(): void
    {
        $this->notificationAction('DemoUI.NotificationSettings');
    }

    private function notificationAction(string $method): void
    {
        if (! System::isAndroid()) {
            return;
        }
        try {
            $result = app(Transport::class)->call($method, '{}');
            $response = json_decode($result ?? '{}', true, flags: JSON_THROW_ON_ERROR);
            if (! isset($response['message']) || ($response['status'] ?? '') === 'error') {
                throw new RuntimeException($response['message'] ?? 'Android notification controls are unavailable. Rebuild the demo.');
            }
            $this->notificationStatus = $response['message'];
        } catch (Throwable $exception) {
            $this->notificationStatus = $exception->getMessage();
        }
    }

    private function refreshNotificationStatus(): void
    {
        if (! System::isAndroid()) {
            return;
        }
        $capabilities = LiveActivities::capabilities();
        $this->notificationStatus = match (true) {
            ! $capabilities->enabled => 'Notifications are disabled. Allow them or open notification settings.',
            $capabilities->promoted => 'Promotion can be requested. Android decides whether this activity gets a status-bar chip.',
            default => 'Standard progress notification available. Status-bar promotion is unavailable or disabled.',
        };
    }

    public function render(): Column
    {
        $android = System::isAndroid();
        $controls = $this->previewing
            ? Column::make(
                Text::make(match ($this->step) {
                    1 => '🍳 Preparing',
                    2 => '🚚 On the way',
                    default => '✅ Delivered',
                })->fontSize(22),
                $this->step < 3
                    ? $this->button('Next order state', 'nextState')
                    : Text::make('Delivered — stays visible until you end it.'),
                $this->button('End Live Activity', 'endPreview'),
            )->gap(12)
            : Column::make(
                $this->button($android ? 'Preview Live Update' : 'Preview Dynamic Island', 'preview'),
                $this->button('Run automatic delivery', 'begin'),
            )->gap(12);

        return Column::make(
            Text::make('Live Activities')->fontSize(30)->fontWeight(7),
            Text::make($this->status)->fontSize(18),
            $controls,
            $android ? Column::make(
                $this->button('Allow notifications', 'requestNotifications'),
                $this->button('Notification / Live Update settings', 'notificationSettings'),
                Text::make($this->notificationStatus)->fontSize(14)->color('#64748B'),
            )->gap(8) : Text::make(''),
            Text::make($android
                ? 'Go Home to inspect the status-bar chip, or pull down notifications. Return here for Next or End. Older Android shows a normal progress notification.'
                : 'Preview stays on each order state. Press ⇧⌘H in Simulator to go Home, then click and hold the Dynamic Island to expand it. Return here for Next or End.')
                ->fontSize(14)->color('#64748B'),
            Text::make('Keep the mock server running. Preview holds each state; automatic delivery takes eight seconds.')
                ->fontSize(14)->color('#64748B'),
        )->padding(20)->gap($android ? 12 : 20)->safeArea();
    }

    private function button(string $label, string $action): Pressable
    {
        return Pressable::make(Text::make($label)->fontSize(18))
            ->onPress($action)->padding(System::isAndroid() ? 12 : 18)->bg('#DBEAFE')->borderRadius(12);
    }
}
