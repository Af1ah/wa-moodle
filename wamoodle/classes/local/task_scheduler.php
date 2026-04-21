<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class task_scheduler {
    public static function queue_processing_task(int $queueid, int $nextruntime = 0): void {
        $task = new \message_wamoodle\task\process_notification_task();
        $task->set_custom_data(['queueid' => $queueid]);

        if ($nextruntime > 0) {
            $task->set_next_run_time($nextruntime);
        }

        \core\task\manager::queue_adhoc_task($task);
    }

    public static function queue_group_course_notification(array $customdata): void {
        $task = new \message_wamoodle\task\course_module_notification_task();
        $task->set_custom_data($customdata);
        \core\task\manager::queue_adhoc_task($task);
    }
}
