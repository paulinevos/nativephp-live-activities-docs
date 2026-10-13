<?php

declare(strict_types=1);

return [
    // Reserved configuration: these mirror the native defaults. The local MVP
    // currently uses a fixed Android channel; changing these values does not
    // reconfigure the native notification channel.
    'android' => [
        'channel_id' => 'live_activities',
        'channel_name' => 'Live activities',
    ],
];
