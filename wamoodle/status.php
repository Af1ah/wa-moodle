<?php
require_once(__DIR__ . '/../../../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/message/output/wamoodle/status.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('statuspage', 'message_wamoodle'));
$PAGE->set_heading(get_string('statuspage', 'message_wamoodle'));
$PAGE->set_pagelayout('admin');

$queue = new \message_wamoodle\local\queue_repository();
$service = new \message_wamoodle\local\sender_session_service();
$broker = new \message_wamoodle\local\broker_client();

$action = optional_param('action', '', PARAM_ALPHA);
if (data_submitted()) {
    require_sesskey();
    if ($action === 'refresh') {
        try {
            $service->refresh();
            redirect($PAGE->url, get_string('refreshsuccess', 'message_wamoodle'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (Throwable $exception) {
            redirect(
                $PAGE->url,
                get_string('statusfail', 'message_wamoodle', $exception->getMessage()),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }
}

$status = null;
$statuserror = null;
$licensestatus = null;
$licenseerror = null;

try {
    $status = $service->refresh();
} catch (Throwable $exception) {
    $statuserror = $exception->getMessage();
}

try {
    $licensestatus = $broker->get_license_status();
} catch (Throwable $exception) {
    $licenseerror = $exception->getMessage();
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('licenseheading', 'message_wamoodle'), 3);

if ($licenseerror !== null) {
    echo $OUTPUT->notification(get_string('licensefail', 'message_wamoodle', $licenseerror), \core\output\notification::NOTIFY_ERROR);
} else {
    $licensemessage = get_string(
        'licenseok',
        'message_wamoodle',
        format_string(($licensestatus['status'] ?? 'unknown') . ' / ' . ($licensestatus['expiresAt'] ?? 'unknown'))
    );
    echo $OUTPUT->notification($licensemessage, \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->heading(get_string('backendstatus', 'message_wamoodle'), 3);

if ($statuserror !== null) {
    echo $OUTPUT->notification(get_string('statusfail', 'message_wamoodle', $statuserror), \core\output\notification::NOTIFY_ERROR);
} else if (!empty($status) && $status->connectionstatus !== 'missing') {
    echo $OUTPUT->notification(
        get_string('statusok', 'message_wamoodle', $status->connectionstatus ?? 'unknown'),
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    echo $OUTPUT->notification(get_string('nostatus', 'message_wamoodle'), \core\output\notification::NOTIFY_WARNING);
}

echo html_writer::start_div('mb-4');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'refresh']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('refreshstatus', 'message_wamoodle'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo $OUTPUT->heading(get_string('recentfailures', 'message_wamoodle'), 3);

$rows = [];
foreach ($queue->get_recent_problem_items() as $item) {
    $rows[] = new html_table_row([
        userdate($item->timemodified),
        s($item->notificationtype),
        s($item->status),
        s($item->lastresponse ?? ''),
    ]);
}

$table = new html_table();
$table->head = ['Updated', 'Notification', 'Status', 'Last response'];
$table->data = $rows;
echo html_writer::table($table);

echo $OUTPUT->footer();
