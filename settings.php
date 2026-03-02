<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * General plugin functions.
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_lti\local\ltiadvantage\admin\admin_setting_registeredplatforms;

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('enrolments', new admin_category(
    'enrolpoodllltifolder',
    new lang_string('pluginname', 'enrol_poodlllti'),
    $this->is_enabled() === false
));

$settings = new admin_settingpage(
    $section,
    get_string('generalsettings', 'enrol_poodlllti'),
    'moodle/site:config',
    $this->is_enabled() === false
);

if ($ADMIN->fulltree) {
    global $CFG;

    // We reuse the registration logic from enrol_lti.
    require_once($CFG->dirroot . '/enrol/lti/lib.php');

    $settings->add(new admin_setting_heading(
        'enrol_poodlllti_settings',
        get_string('pluginname', 'enrol_poodlllti'),
        get_string('pluginname_desc', 'enrol_poodlllti')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_poodlllti/defaultenrol',
        get_string('defaultenrol', 'enrol'),
        get_string('defaultenrol_desc', 'enrol'),
        1
    ));
}

$ADMIN->add('enrolpoodllltifolder', $settings);

$settings = new admin_settingpage(
    'enrolpoodllltisettings_registrations',
    get_string('toolregistration', 'enrol_poodlllti'),
    'moodle/site:config',
    $this->is_enabled() === false
);

// Re-use enrol_lti's registered platforms setting.
$settings->add(new admin_setting_registeredplatforms());
// Link to the registration and deployment pages.
// We can't easily add external pages directly to this $settings object as nodes,
// but they are already added by enrol_lti.
// If the user wants specific links here, we can add them as HTML or just point them to the enrol_lti ones.

$ADMIN->add('enrolpoodllltifolder', $settings);

$settings = null;
