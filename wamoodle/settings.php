<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'message_wamoodle/enable',
        get_string('enable', 'message_wamoodle'),
        get_string('enable_desc', 'message_wamoodle'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'message_wamoodle/evolutionurl',
        get_string('evolutionurl', 'message_wamoodle'),
        get_string('evolutionurl_desc', 'message_wamoodle'),
        '',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'message_wamoodle/evolutionapikey',
        get_string('evolutionapikey', 'message_wamoodle'),
        get_string('evolutionapikey_desc', 'message_wamoodle'),
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
