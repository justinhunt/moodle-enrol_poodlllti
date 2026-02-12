<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    global $CFG;

    // We reuse the registration logic from enrol_lti.
    require_once($CFG->dirroot . '/enrol/lti/lib.php');

    $settings->add(new admin_setting_heading('enrol_poodlllti_settings', get_string('pluginname', 'enrol_poodlllti'),
        get_string('pluginname_desc', 'enrol_poodlllti')));

    // Re-use enrol_lti's registered platforms setting.
    $settings->add(new \enrol_lti\local\ltiadvantage\admin\admin_setting_registeredplatforms());

    // Link to the registration and deployment pages.
    // We can't easily add external pages directly to this $settings object as nodes, 
    // but they are already added by enrol_lti. 
    // If the user wants specific links here, we can add them as HTML or just point them to the enrol_lti ones.
}
