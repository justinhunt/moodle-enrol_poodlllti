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
 * Add a new platform
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_poodlllti\form\platform\edit;
use enrol_poodlllti\util;

require_once(dirname(__DIR__, 3) . '/config.php');

$id = optional_param('id', 0 , PARAM_INT);
$step = optional_param('step', 0, PARAM_INT);
$url = new moodle_url('/enrol/poodlllti/platform/add.php');
$context = context_system::instance();
$PAGE->set_url($url);
$PAGE->set_context($context);

require_capability('enrol/poodlllti:cancreateplatform', $context);

$form = new edit();

$form->check_access();
$form->set_data_for_dynamic_submission();

$step = $form->get_step();
$title = util::get_page_title($step);
$PAGE->set_title($title);

if ($redirecturl = $form->process_dynamic_submission()) {
    redirect($redirecturl);
}

echo $OUTPUT->header();
echo $form->render();
echo $OUTPUT->footer();
