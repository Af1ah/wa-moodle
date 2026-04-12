<?php
namespace message_wamoodle\task;

defined('MOODLE_INTERNAL') || die();

use message_wamoodle\local\sender_session_service;

/**
 * Task to send WhatsApp notifications when a new course module (Assignment, Quiz, etc.) is created.
 */
class course_module_notification_task extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('whatsappconnect', 'message_wamoodle') . ' - Module Notification';
    }

    public function execute(): void {
        global $DB;
        $data = (array)$this->get_custom_data();
        $cmid = $data['cmid'];
        $courseid = $data['courseid'];
        $groupjid = $data['groupjid'];
        $modname = $data['modname'];

        // 1. Fetch Course Module and Course.
        $cm = get_coursemodule_from_id($modname, $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return;
        }

        // 2. Fetch specific module data and determine common fields.
        $title = '';
        $description = '';
        $deadline_ts = 0;
        $view_path = '';
        $icon = '📝';

        if ($modname === 'assign') {
            $record = $DB->get_record('assign', ['id' => $cm->instance]);
            if ($record) {
                $title = $record->name;
                $description = $record->intro;
                $deadline_ts = $record->duedate;
                $view_path = '/mod/assign/view.php';
                $icon = '📝';
            }
        } else if ($modname === 'quiz') {
            $record = $DB->get_record('quiz', ['id' => $cm->instance]);
            if ($record) {
                $title = $record->name;
                $description = $record->intro;
                $deadline_ts = $record->timeclose;
                $view_path = '/mod/quiz/view.php';
                $icon = '🧠';
            }
        }

        if (empty($title)) {
            return;
        }

        // 3. Format the message.
        $url = new \moodle_url($view_path, ['id' => $cmid]);
        $urlstring = $url->out(false);

        $clean_desc = html_entity_decode(strip_tags($description), ENT_QUOTES, 'UTF-8');
        $clean_desc = trim(preg_replace('/\s+/', ' ', $clean_desc));
        if (\core_text::strlen($clean_desc) > 300) {
            $clean_desc = \core_text::substr($clean_desc, 0, 297) . '...';
        }

        $deadline = $deadline_ts > 0 ? userdate($deadline_ts) : 'No deadline';
        $modlabel = ($modname === 'assign') ? 'Assignment' : 'Quiz';

        $text = "*{$icon} New {$modlabel} Created!* \n\n";
        $text .= "*Course:* {$course->shortname}\n";
        $text .= "*Title:* {$title}\n";
        
        if (!empty($clean_desc)) {
            $text .= "*Description:* {$clean_desc}\n";
        }
        
        $text .= "*Deadline:* {$deadline}\n\n";
        $text .= "🔗 *Link:* {$urlstring}";

        $sender = new sender_session_service();
        $idempotency = 'module-' . $cmid . '-' . $groupjid;

        // 4. Try sending interactive button message first.
        try {
            $btn_desc = "*Course:* {$course->shortname}\n*Title:* {$title}";
            if (!empty($clean_desc)) {
                $btn_desc .= "\n*Description:* {$clean_desc}";
            }
            $btn_desc .= "\n*Deadline:* {$deadline}";

            $sender->send_buttons($groupjid, [
                'title' => "{$icon} New {$modlabel} Created!",
                'description' => $btn_desc . "\n\n🔗 *Link:* {$urlstring}",
                'footer' => 'Moodle Update',
                'buttons' => [
                    [
                        'id' => "ack-{$cmid}",
                        'displayText' => "Acknowledge"
                    ]
                ]
            ], $idempotency . '-btn');
        } catch (\Throwable $exception) {
            // Fallback to text message
            try {
                $sender->send_text($groupjid, $text, $idempotency . '-txt');
            } catch (\Throwable $fallback_exception) {
                debugging("Failed to send {$modname} notification: " . $fallback_exception->getMessage(), DEBUG_DEVELOPER);
                throw $fallback_exception;
            }
        }
    }
}
