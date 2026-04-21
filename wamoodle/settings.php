<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $personalnotificationssetting = new admin_setting_configcheckbox(
        'message_wamoodle/enablepersonalnotifications',
        get_string('enablepersonalnotifications', 'message_wamoodle'),
        get_string('enablepersonalnotifications_desc', 'message_wamoodle'),
        1
    );
    $personalnotificationssetting->set_updatedcallback('message_wamoodle_admin_settings_updated');
    $settings->add($personalnotificationssetting);

    $settings->add(new admin_setting_configpasswordunmask(
        'message_wamoodle/plugbolt_api_key',
        get_string('plugbolt_api_key', 'message_wamoodle'),
        get_string('plugbolt_api_key_desc', 'message_wamoodle'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'message_wamoodle/sendersessionid',
        get_string('sendersessionid', 'message_wamoodle'),
        get_string('sendersessionid_desc', 'message_wamoodle'),
        '',
        PARAM_ALPHANUMEXT
    ));
    $settings->add(new admin_setting_configtext(
        'message_wamoodle/defaultcountrycode',
        get_string('defaultcountrycode', 'message_wamoodle'),
        get_string('defaultcountrycode_desc', 'message_wamoodle'),
        '91',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_configtext(
        'message_wamoodle/mobilefield',
        get_string('mobilefield', 'message_wamoodle'),
        get_string('mobilefield_desc', 'message_wamoodle'),
        'phone2',
        PARAM_ALPHANUMEXT
    ));

    $statusurl = new moodle_url('/message/output/wamoodle/status.php');
    $settings->add(new admin_setting_heading(
        'message_wamoodle/statuspage',
        get_string('statuspage', 'message_wamoodle'),
        html_writer::link($statusurl, get_string('statuspage', 'message_wamoodle'))
    ));
}
