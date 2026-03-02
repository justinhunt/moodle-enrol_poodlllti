<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/lti/lib.php');

/**
 * Poodll LTI enrolment plugin class.
 *
 * @package enrol_poodlllti
 * @copyright 2023 Poodll
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_poodlllti_plugin extends enrol_lti_plugin {

    /**
     * Returns true if we can add a new instance to this course.
     *
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        return has_capability('moodle/course:enrolconfig', $context) && has_capability('enrol/poodlllti:config', $context);
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/poodlllti:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/poodlllti:config', $context);
    }

    public function add_default_instance($course) {
        $context = context_course::instance($course->id);
        $instructorroleid = key(get_archetype_roles('editingteacher'));
        $learnerroleid = key(get_archetype_roles('student'));

        $field = [
            'contextid' => $context->id,
            'roleinstructor' => $instructorroleid,
            'rolelearner' => $learnerroleid,
        ];
        return $this->add_instance($course, $field);
    }

    // Inherit other methods from enrol_lti_plugin.
    // add_instance will use enrol_lti_tools table which is what we want for reusing LTI infrastructure.
}
