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

$action = optional_param('action', '', PARAM_ALPHA);
if (data_submitted()) {
    require_sesskey();

    try {
        if ($action === 'sendtest') {
            $number = required_param('testnumber', PARAM_RAW_TRIMMED);
            $text = required_param('testmessage', PARAM_RAW_TRIMMED);
            $service->send_text($number, $text);
            redirect($PAGE->url, get_string('testsuccess', 'message_wamoodle'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'startqr') {
            $service->start_qr_session();
            redirect($PAGE->url, get_string('qrsuccess', 'message_wamoodle'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'pairing') {
            $number = required_param('pairingnumber', PARAM_RAW_TRIMMED);
            $service->start_pairing_session($number);
            redirect($PAGE->url, get_string('pairingsuccess', 'message_wamoodle'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'refresh') {
            $service->refresh();
            redirect($PAGE->url, get_string('refreshsuccess', 'message_wamoodle'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Throwable $exception) {
        redirect(
            $PAGE->url,
            get_string('testfailure', 'message_wamoodle', $exception->getMessage()),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

$status = null;
$statuserror = null;

try {
    $status = $service->refresh();
} catch (Throwable $exception) {
    $statuserror = $exception->getMessage();
}

echo $OUTPUT->header();

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

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'startqr']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('startqr', 'message_wamoodle'), 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'pairing']);
echo html_writer::tag('label', get_string('pairingnumber', 'message_wamoodle'), ['for' => 'id_pairingnumber']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'pairingnumber', 'id' => 'id_pairingnumber', 'class' => 'form-control mb-2']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('requestpairing', 'message_wamoodle'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if (!empty($status->pairingcode)) {
    echo $OUTPUT->notification(get_string('pairingcodevalue', 'message_wamoodle', s($status->pairingcode)), \core\output\notification::NOTIFY_INFO);
}

if (!empty($status->qrbase64)) {
    echo html_writer::empty_tag('img', [
        'src' => $status->qrbase64,
        'alt' => get_string('qrimagealt', 'message_wamoodle'),
        'style' => 'max-width:320px;height:auto;',
    ]);
}
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'class' => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'sendtest']);
echo html_writer::tag('label', get_string('testnumber', 'message_wamoodle'), ['for' => 'id_testnumber']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'testnumber',
    'id' => 'id_testnumber',
    'class' => 'form-control',
]);
echo html_writer::tag('label', get_string('testmessage', 'message_wamoodle'), ['for' => 'id_testmessage']);
echo html_writer::tag('textarea', '', [
    'name' => 'testmessage',
    'id' => 'id_testmessage',
    'rows' => 4,
    'class' => 'form-control',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('sendtest', 'message_wamoodle'),
    'class' => 'btn btn-primary mt-3',
]);
echo html_writer::end_tag('form');

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
