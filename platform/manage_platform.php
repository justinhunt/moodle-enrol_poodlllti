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
 * TODO describe file manage_platform
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_poodlllti\table\manage_platform;
use enrol_poodlllti\table\manage_platform_filterset;
use enrol_poodlllti\task\delete_platform;
use enrol_poodlllti\util;
use core\task\manager;
use enrol_poodlllti\form\platform\filter;
use core_table\local\filter\filter as corefilter;

require_once(dirname(__DIR__, 3) . '/config.php');

$deleteplatformid = optional_param('deleteplatformid', 0, PARAM_INT);
$schoolname = optional_param('schoolname', '', PARAM_TEXT);

$url = new moodle_url('/enrol/poodlllti/platform/manage_platform.php');
$context = context_system::instance();
$perpage = optional_param('perpage', util::PERPAGE, PARAM_INT);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageplatform', util::COMPONENT));
$PAGE->set_heading(get_string('manageplatform', util::COMPONENT));

require_capability('enrol/poodlllti:manageallplatforms', $context);

if (!empty($deleteplatformid)) {
    require_sesskey();
    if (util::delete_platform($deleteplatformid)) {
        redirect(
            $url,
            get_string('platform:deletesuccessfully', util::COMPONENT)
        );
    }
}

$filter = new filter();
$filter->set_data_for_dynamic_submission();

$manageplatforms = new manage_platform('manage_platforms');
$filterset = (new manage_platform_filterset())
    ->add_filter_from_params('schoolname', corefilter::JOINTYPE_DEFAULT, [$schoolname]);
$manageplatforms->set_filterset($filterset);

$PAGE->requires->js_call_amd(
    'enrol_poodlllti/platform/filterform',
    'initTable',
    [$manageplatforms->uniqueid]
);

echo $OUTPUT->header();

echo html_writer::start_div('schoolform_container', [
    'id' => 'schoolform_container',
    'data-form-class' => $filter::class,
]);
echo $filter->render();
echo html_writer::end_div();

$manageplatforms->out($perpage, false);

echo $OUTPUT->footer();


