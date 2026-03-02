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
 * Page to manage platforms
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_poodlllti\table\manage;
use enrol_poodlllti\table\manage_filterset;
use enrol_poodlllti\util;

require_once(dirname(__DIR__, 3) . '/config.php');

$perpage = optional_param('perpage', util::PERPAGE, PARAM_INT);
$deleteplatformid = optional_param('deleteplatformid', 0, PARAM_INT);
$url = new moodle_url('/enrol/poodlllti/platform/manage.php');
$context = context_system::instance();

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageplatform', util::COMPONENT));
$PAGE->set_heading(get_string('manageplatform', util::COMPONENT));

require_capability('enrol/poodlllti:cancreateplatform', $context);

if (!empty($deleteplatformid)) {
    require_sesskey();
    if (util::delete_platform($deleteplatformid)) {
        redirect(
            $url,
            get_string('platform:deletesuccessfully', util::COMPONENT)
        );
    }
}

$manage = new manage('platforms');
$filterset = new manage_filterset();
$manage->set_filterset($filterset);

$addbuttonhtml = html_writer::start_div('text-right');
$addbuttonhtml .= html_writer::link(
    new moodle_url('/enrol/poodlllti/platform/add.php'),
    get_string('addnew', util::COMPONENT),
    [
        'class' => 'btn btn-primary mb-2',
    ]
);
$addbuttonhtml .= html_writer::end_div();

echo $OUTPUT->header();

echo $addbuttonhtml;
$manage->out($perpage, false);

echo $OUTPUT->footer();
