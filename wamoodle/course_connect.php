<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use message_wamoodle\local\config;
use message_wamoodle\local\broker_client;
use message_wamoodle\task\send_group_message_task;

$courseid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$invitelink = optional_param('invitelink', '', PARAM_RAW);
$messagetext = optional_param('messagetext', '', PARAM_TEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/message/output/wamoodle/course_connect.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('whatsappconnect', 'message_wamoodle'));
$PAGE->set_heading($course->fullname);

$senderstatus = (new \message_wamoodle\local\sender_session_service())->refresh();
if (empty($senderstatus->connectionstatus) || $senderstatus->connectionstatus !== 'open') {
    throw new \moodle_exception('error:sessionnotopen', 'message_wamoodle');
}

$client = new broker_client();

// Handle form submissions.
if ($action === 'connect' && !empty($invitelink) && confirm_sesskey()) {
    $invitecode = trim($invitelink);
    if (preg_match('/chat\.whatsapp\.com\/([a-zA-Z0-9]+)/', $invitecode, $matches)) {
        $invitecode = $matches[1];
    }

    try {
        $result = $client->join_group($invitecode);
        $group = $result['group'] ?? [];
        $groupjid = $group['jid'] ?? '';
        $groupname = $group['subject'] ?? 'Unknown Group';

        if (!$groupjid) {
            throw new \moodle_exception('error:invalidgroup', 'message_wamoodle');
        }

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->groupjid = $groupjid;
        $record->groupname = $groupname;
        $record->timecreated = time();
        $record->timemodified = time();

        if ($existing = $DB->get_record('message_wamoodle_course_groups', ['courseid' => $courseid])) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record('message_wamoodle_course_groups', $record);
        } else {
            $DB->insert_record('message_wamoodle_course_groups', $record);
        }

        // Queue acknowledgment message.
        $ack_msg = get_string('acknowledgmentmessage', 'message_wamoodle', $course->fullname);
        $task = new \message_wamoodle\task\send_group_message_task();
        $task->set_custom_data(['groupjid' => $groupjid, 'text' => $ack_msg]);
        \core\task\manager::queue_adhoc_task($task);

        \core\notification::success(get_string('successconnect', 'message_wamoodle'));
    } catch (\Exception $e) {
        \core\notification::error(get_string('error:invalidgroup', 'message_wamoodle'));
    }
    
    redirect($PAGE->url);
} elseif ($action === 'disconnect' && confirm_sesskey()) {
    $DB->delete_records('message_wamoodle_course_groups', ['courseid' => $courseid]);
    \core\notification::success(get_string('successdisconnect', 'message_wamoodle'));
    redirect($PAGE->url);
} elseif ($action === 'sendmessage' && !empty($messagetext) && confirm_sesskey()) {
    $existing = $DB->get_record('message_wamoodle_course_groups', ['courseid' => $courseid]);
    if ($existing) {
        $task = new send_group_message_task();
        $task->set_custom_data(['groupjid' => $existing->groupjid, 'text' => $messagetext]);
        \core\task\manager::queue_adhoc_task($task);
        \core\notification::success(get_string('successmessage', 'message_wamoodle'));
    }
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('whatsappconnect', 'message_wamoodle'));

$connected_group = $DB->get_record('message_wamoodle_course_groups', ['courseid' => $courseid]);

if ($connected_group) {
    echo \html_writer::tag('div', get_string('connectedto', 'message_wamoodle', s($connected_group->groupname)), ['class' => 'alert alert-info mt-3']);

    // Disconnect Form
    echo \html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mt-2']);
    echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'disconnect']);
    echo \html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('disconnectgroup', 'message_wamoodle'), 'class' => 'btn btn-danger']);
    echo \html_writer::end_tag('form');

    // Send Message Form
    echo \html_writer::start_tag('div', ['class' => 'card mt-4']);
    echo \html_writer::start_tag('div', ['class' => 'card-body']);
    echo \html_writer::tag('h5', get_string('sendmessage', 'message_wamoodle'), ['class' => 'card-title']);
    echo \html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
    echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'sendmessage']);
    
    echo \html_writer::start_tag('div', ['class' => 'form-group']);
    echo \html_writer::tag('label', get_string('messagebody', 'message_wamoodle'), ['for' => 'messagetext']);
    echo \html_writer::tag('textarea', '', ['name' => 'messagetext', 'id' => 'messagetext', 'class' => 'form-control', 'rows' => 4, 'required' => 'required']);
    echo \html_writer::end_tag('div');
    
    echo \html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('send', 'message_wamoodle'), 'class' => 'btn btn-primary mt-2']);
    echo \html_writer::end_tag('form');
    echo \html_writer::end_tag('div');
    echo \html_writer::end_tag('div');
} else {
    echo \html_writer::tag('div', get_string('notconnected', 'message_wamoodle'), ['class' => 'alert alert-warning mt-3']);

    echo \html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mt-4']);
    echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'connect']);
    
    echo \html_writer::start_tag('div', ['class' => 'form-group']);
    echo \html_writer::tag('label', get_string('invitelink', 'message_wamoodle'), ['for' => 'invitelink']);
    echo \html_writer::empty_tag('input', ['type' => 'text', 'name' => 'invitelink', 'id' => 'invitelink', 'class' => 'form-control', 'required' => 'required', 'placeholder' => 'https://chat.whatsapp.com/INVITECODE']);
    echo \html_writer::end_tag('div');

    echo \html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('connectgroup', 'message_wamoodle'), 'class' => 'btn btn-primary mt-3']);
    echo \html_writer::end_tag('form');
}

echo $OUTPUT->footer();
