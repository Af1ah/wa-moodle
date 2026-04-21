<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class group_notification_service {
    private group_schedule_repository $repository;

    public function __construct(?group_schedule_repository $repository = null) {
        $this->repository = $repository ?? new group_schedule_repository();
    }

    public function handle_course_module_created(\core\event\course_module_created $event): void {
        $modname = (string)($event->other['modulename'] ?? '');
        if (!group_notification_policy::supports_module($modname)) {
            return;
        }

        if (!$this->get_connected_group((int)$event->courseid)) {
            return;
        }

        $snapshot = $this->get_module_snapshot((int)$event->objectid, $modname);
        if (!$snapshot) {
            return;
        }

        $this->repository->sync_state($snapshot->courseid, $snapshot->cmid, $snapshot->modname, $snapshot->trackedtime);
        task_scheduler::queue_group_course_notification([
            'courseid' => $snapshot->courseid,
            'cmid' => $snapshot->cmid,
            'modname' => $snapshot->modname,
            'type' => 'created',
            'trackedtime' => $snapshot->trackedtime,
        ]);
    }

    public function handle_course_module_updated(\core\event\course_module_updated $event): void {
        $modname = (string)($event->other['modulename'] ?? '');
        if (!group_notification_policy::supports_module($modname)) {
            return;
        }

        if (!$this->get_connected_group((int)$event->courseid)) {
            return;
        }

        $snapshot = $this->get_module_snapshot((int)$event->objectid, $modname);
        if (!$snapshot) {
            return;
        }

        $state = $this->repository->get_by_cmid($snapshot->cmid);
        if (!$state) {
            $this->repository->sync_state($snapshot->courseid, $snapshot->cmid, $snapshot->modname, $snapshot->trackedtime);
            return;
        }

        $oldtrackedtime = (int)$state->trackedtime;
        if ($oldtrackedtime === (int)$snapshot->trackedtime) {
            return;
        }

        $this->repository->sync_state($snapshot->courseid, $snapshot->cmid, $snapshot->modname, $snapshot->trackedtime);
        task_scheduler::queue_group_course_notification([
            'courseid' => $snapshot->courseid,
            'cmid' => $snapshot->cmid,
            'modname' => $snapshot->modname,
            'type' => 'schedule_updated',
            'oldtrackedtime' => $oldtrackedtime,
            'newtrackedtime' => $snapshot->trackedtime,
        ]);
    }

    public function queue_due_reminders(): void {
        $now = time();

        foreach (['assign', 'quiz'] as $modname) {
            foreach ($this->get_connected_snapshots($modname) as $snapshot) {
                $state = $this->repository->sync_state($snapshot->courseid, $snapshot->cmid, $snapshot->modname, $snapshot->trackedtime);

                foreach (group_notification_policy::get_reminders($snapshot->modname) as $reminder) {
                    if (!group_notification_policy::is_reminder_due($reminder['code'], $snapshot->trackedtime, $now)) {
                        continue;
                    }

                    if ($this->repository->is_reminder_sent($state, $reminder['code'], $snapshot->trackedtime)) {
                        continue;
                    }

                    $this->repository->mark_reminder_sent($snapshot->cmid, $reminder['code'], $snapshot->trackedtime);
                    task_scheduler::queue_group_course_notification([
                        'courseid' => $snapshot->courseid,
                        'cmid' => $snapshot->cmid,
                        'modname' => $snapshot->modname,
                        'type' => 'reminder',
                        'remindercode' => $reminder['code'],
                        'trackedtime' => $snapshot->trackedtime,
                    ]);
                }
            }
        }
    }

    public function get_module_snapshot(int $cmid, string $modname): ?\stdClass {
        global $DB;

        if (!group_notification_policy::supports_module($modname)) {
            return null;
        }

        $cm = get_coursemodule_from_id($modname, $cmid, 0, false, IGNORE_MISSING);
        if (!$cm || !empty($cm->deletioninprogress) || empty($cm->visible)) {
            return null;
        }

        $course = $DB->get_record('course', ['id' => $cm->course], 'id,shortname', MUST_EXIST);
        $record = $DB->get_record($modname, ['id' => $cm->instance], '*', IGNORE_MISSING);
        if (!$record) {
            return null;
        }

        $trackedfield = group_notification_policy::get_schedule_field($modname);
        $trackedtime = $trackedfield !== null ? (int)($record->{$trackedfield} ?? 0) : 0;
        $url = new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cmid]);

        return (object)[
            'courseid' => (int)$cm->course,
            'cmid' => (int)$cmid,
            'modname' => $modname,
            'title' => trim((string)($record->name ?? '')),
            'trackedtime' => $trackedtime,
            'courseshortname' => (string)$course->shortname,
            'url' => $url->out(false),
        ];
    }

    public function suppress_active_reminders(int $cmid, string $modname, int $trackedtime): void {
        $now = time();
        foreach (group_notification_policy::get_reminders($modname) as $reminder) {
            if (group_notification_policy::is_reminder_due($reminder['code'], $trackedtime, $now)) {
                $this->repository->mark_reminder_sent($cmid, $reminder['code'], $trackedtime);
            }
        }
    }

    public function get_connected_group(int $courseid): ?\stdClass {
        global $DB;

        return $DB->get_record('message_wamoodle_course_groups', ['courseid' => $courseid]) ?: null;
    }

    /**
     * @return array<int, \stdClass>
     */
    private function get_connected_snapshots(string $modname): array {
        global $DB;

        if ($modname === 'assign') {
            return $DB->get_records_sql(
                "SELECT cm.id AS cmid, cg.courseid, 'assign' AS modname,
                        c.shortname AS courseshortname, a.name AS title, a.duedate AS trackedtime
                   FROM {message_wamoodle_course_groups} cg
                   JOIN {course} c ON c.id = cg.courseid
                   JOIN {modules} m ON m.name = :assignmodname
                   JOIN {course_modules} cm ON cm.course = cg.courseid
                        AND cm.module = m.id
                        AND cm.visible = 1
                        AND cm.deletioninprogress = 0
                   JOIN {assign} a ON a.id = cm.instance
                  WHERE a.duedate > 0",
                ['assignmodname' => 'assign']
            );
        }

        if ($modname === 'quiz') {
            return $DB->get_records_sql(
                "SELECT cm.id AS cmid, cg.courseid, 'quiz' AS modname,
                        c.shortname AS courseshortname, q.name AS title, q.timeopen AS trackedtime
                   FROM {message_wamoodle_course_groups} cg
                   JOIN {course} c ON c.id = cg.courseid
                   JOIN {modules} m ON m.name = :quizmodname
                   JOIN {course_modules} cm ON cm.course = cg.courseid
                        AND cm.module = m.id
                        AND cm.visible = 1
                        AND cm.deletioninprogress = 0
                   JOIN {quiz} q ON q.id = cm.instance
                  WHERE q.timeopen > 0",
                ['quizmodname' => 'quiz']
            );
        }

        return [];
    }
}
