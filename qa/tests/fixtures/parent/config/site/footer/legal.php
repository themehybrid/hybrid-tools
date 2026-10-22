<?php

// Closures deferring translation: config files execute at load time, which can
// be before text domains are registered.
return [
    'notice' => static fn() => 'Parent notice',
    'credit' => static fn() => 'Parent credit',
    'year'   => 2026,
];
