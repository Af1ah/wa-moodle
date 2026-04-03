<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'message_wamoodle_get_status' => [
        'classname' => \message_wamoodle\external\get_status::class,
        'description' => get_string('external:getstatus', 'message_wamoodle'),
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'moodle/site:config',
    ],
    'message_wamoodle_send_test_message' => [
        'classname' => \message_wamoodle\external\send_test_message::class,
        'description' => get_string('external:sendtest', 'message_wamoodle'),
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/site:config',
    ],
];
