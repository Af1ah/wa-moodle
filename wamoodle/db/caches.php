<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    'evolutiongroups' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 600, // Cache for 10 minutes
    ],
];
