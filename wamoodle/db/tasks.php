<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => \message_wamoodle\task\scan_group_reminders_task::class,
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];
