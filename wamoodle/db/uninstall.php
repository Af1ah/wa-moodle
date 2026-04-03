<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_message_wamoodle_uninstall(): bool {
    message_processor_uninstall('wamoodle');
    return true;
}
