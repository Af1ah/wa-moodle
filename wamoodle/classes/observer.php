<?php

namespace message_wamoodle;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Triggered when a new course module is created.
     * 
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $DB, $CFG;

        $modname = $event->other['modulename'];
        $allowed_mods = ['assign', 'quiz'];

        if (!in_array($modname, $allowed_mods)) {
            return;
        }

        $courseid = $event->courseid;

        // Check if there is a connected WhatsApp group.
        $group = $DB->get_record('message_wamoodle_course_groups', ['courseid' => $courseid]);
        if (!$group) {
            return; // Course is not connected to any WhatsApp group.
        }

        // Pass only the minimum required data to the task.
        // The task will run after this transaction is committed, preventing DB conflicts.
        $task = new \message_wamoodle\task\course_module_notification_task();
        $task->set_custom_data([
            'cmid'      => $event->objectid,
            'courseid'  => $courseid,
            'groupjid'  => $group->groupjid,
            'modname'   => $modname
        ]);
        
        \core\task\manager::queue_adhoc_task($task);
    }
}
