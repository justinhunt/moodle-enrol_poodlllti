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

namespace enrol_poodlllti\form\platform;

use context;
use context_system;
use core_form\dynamic_form;
use core_table\local\filter\filterset;
use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;
use enrol_poodlllti\table\manage_platform;
use enrol_poodlllti\util;
use moodle_url;

/**
 * Class filter
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter extends dynamic_form {

    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        require_capability('enrol/poodlllti:manageallplatforms', $context);
    }

    public function process_dynamic_submission() {
        $filterset = self::filterset_from_formdata(
            manage_platform::get_filterset_class(),
            $this->get_data()
        );
        return [
            'filterset' => $filterset,
        ];
    }

    public function set_data_for_dynamic_submission(): void {

    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $url = new moodle_url('/enrol/poodlllti/platform/manage_platform.php');
        return $url;
    }

    public function definition() {
        $mform = $this->_form;
        $mform->updateAttributes(['class' => 'schoolfilter']);

        $mform->addElement('text', 'schoolname', get_string('schoolname', util::COMPONENT));
        $mform->setType('schoolname', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('submit'));
    }

    /**
     * @param string|filterset $filterset
     * @param object $formdata
     * @return filterset
     */
    public static function filterset_from_formdata($filterset, object $formdata): filterset {
        if (is_string($filterset)) {
            $filterset = new $filterset();
        }
        foreach ($filterset->get_all_filtertypes() as $filtername => $filtertype) {
            if (property_exists($formdata, $filtername)) {
                $filteredvalue = array_map(function ($value) use ($filtertype) {
                    if ($filtertype === integer_filter::class) {
                        return (int) $value;
                    } else if ($filtertype === string_filter::class) {
                        return (string) $value;
                    }
                    return $value;
                }, (array) $formdata->{$filtername});
                $filterset->add_filter_from_params($filtername, $filterset::JOINTYPE_DEFAULT, $filteredvalue);
            }
        }
        return $filterset;
    }
}

