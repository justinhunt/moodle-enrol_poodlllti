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


namespace enrol_poodlllti\table;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/tablelib.php');

use confirm_action;
use context_system;
use core\context;
use core_table\dynamic;
use core_table\local\filter\filterset;
use enrol_poodlllti\util;
use html_writer;
use moodle_url;
use single_button;
use table_sql;

/**
 * Class manage
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage extends table_sql implements dynamic {

    public function has_capability(): bool {
        return has_capability('enrol/poodlllti:cancreateplatform', $this->get_context());
    }

    public function get_context(): context {
        return context_system::instance();
    }

    public function guess_base_url(): void {
        $this->define_baseurl(new moodle_url('/enrol/poodlllti/platform/manage.php'));
    }

    public function set_filterset(filterset $filterset): void {
        global $USER;
        parent::set_filterset($filterset);

        $cols = [
            'schoolname' => get_string('platformname', util::COMPONENT),
            'actions' => get_string('actions'),
        ];
        $this->set_sql('*', '{enrol_poodlllti_clients}', 'userid = :userid AND (deleted = 0 OR deleted IS NULL)', ['userid' => $USER->id]);
        $this->define_columns(array_keys($cols));
        $this->define_headers(array_values($cols));
        $this->sortable(false);
        $this->collapsible(false);
    }

    public function col_actions($record) {
        global $OUTPUT;
        if (!empty($record->ltideploymentid)) {
            $html = html_writer::tag('a', get_string('view'), [
                'class' => 'btn btn-primary',
                'href' => new moodle_url('/enrol/poodlllti/platform/add.php', [
                    'action' => 'edit',
                    'id' => $record->id,
                    'step' => 1
                ]),
            ]);

            $deleteurl = new moodle_url('/enrol/poodlllti/platform/manage.php', [
                'deleteplatformid' => $record->id,
                'sesskey' => sesskey()
            ]);
            $deletebutton = new single_button(
                $deleteurl,
                get_string('delete'),
                'post',
                single_button::BUTTON_DANGER
            );
            $deletebutton->class .= ' ml-1';
            $action = new confirm_action(get_string('platform:confirmmsg', util::COMPONENT));
            $html .= $OUTPUT->action_link($deleteurl, $deletebutton, $action);
        } else {
            $html = html_writer::tag('a', get_string('continuesetup', util::COMPONENT), [
                'class' => 'btn btn-primary',
                'href' => new moodle_url('/enrol/poodlllti/platform/add.php', [
                    'action' => 'edit',
                    'id' => $record->id,
                    'step' => 2
                ]),
            ]);
        }

        return $html;
    }

    public function print_nothing_to_display(): void {
        global $OUTPUT;

        echo $this->get_dynamic_table_html_start();
        echo $this->render_reset_button();
        $this->print_initials_bar();

        echo $OUTPUT->notification(
            get_string('noschoolfound', util::COMPONENT),
            'info',
            false
        );

        echo $this->get_dynamic_table_html_end();
    }

}
