<?php

// A plugin holding its own container. Mixes both closure cases on purpose:
// 'title'    — a deferred translation, meant to be resolved on read
// 'callback' — a real callable, meant to be passed on and NOT invoked
return [
    'title'    => static fn() => 'Settings',
    'callback' => static fn() => 'rendered',
    'position' => 25,
];
