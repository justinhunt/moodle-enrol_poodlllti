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

namespace enrol_poodlllti\task;

use core\task\adhoc_task;
use core_course_category;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\application_registration_service;
use enrol_poodlllti\util;
use Exception;

/**
 * Class delete_platform
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_platform extends adhoc_task {

    public function get_name() {
        return get_string('deleteplatformtask', util::COMPONENT);
    }

    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->platformid)) {
            return;
        }

        $platformdata = $DB->get_record(
            'enrol_poodlllti_clients',
            [
                'id' => $data->platformid,
                'deleted' => 1
            ]
        );
        if (empty($platformdata)) {
            return;
        }

        $regservice = new application_registration_service(
            new application_registration_repository(),
            new deployment_repository(),
            new resource_link_repository(),
            new context_repository(),
            new user_repository()
        );

        $regservice->delete_application_registration($platformdata->ltiappregid);
        mtrace('Platform App register and deployment deleted successfully');

        if (!empty($platformdata->categoryid)) {

            $courses = $DB->get_records('course', ['category' => $platformdata->categoryid]);
            foreach ($courses as $course) {
                delete_course($course);
            }

            try {
                $category = core_course_category::get($platformdata->categoryid);
                $category->delete_full(false);
                mtrace('Platform Course and Categoty deleted successfully');
            } catch (Exception $e) {
                mtrace('Error deleting categoryid --> ' . $platformdata->categoryid);
                $exinfo = get_exception_info($e);
                mtrace('error --> ' . $exinfo->message);
            }
        }

        $DB->delete_records('enrol_poodlllti_clients', ['id' => $platformdata->id]);
        mtrace('platform delete successfully');
    }
}
