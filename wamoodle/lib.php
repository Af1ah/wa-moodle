<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Extends the course navigation menu.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context $context The context object
 */
function message_wamoodle_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('moodle/course:manageactivities', $context)) {
        $url = new moodle_url('/message/output/wamoodle/course_connect.php', ['id' => $course->id]);
        $node = $navigation->add(
            get_string('whatsappconnect', 'message_wamoodle'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'whatsappconnect',
            new pix_icon('i/settings', '')
        );
        $node->showinflatnavigation = true;
    }
}
