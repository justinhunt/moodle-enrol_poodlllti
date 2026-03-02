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

namespace enrol_poodlllti;

use context_system;
use core\task\manager;
use enrol_poodlllti\task\delete_platform;
use lang_string;

/**
 * Class util
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {

    /** @var string */
    const COMPONENT = 'enrol_poodlllti';

    const PLATFORMTYPES = [
        'moodle' => 'moodle',
        'canvas' => 'canvas',
        'brightspace' => 'brightspace',
        'other' => 'other',
    ];

    const PERPAGE = 30;

    public static function get_prefilled_data_by_platform(string $platformtype, string $platformurl): array {
        $platformurl = rtrim($platformurl, '/');
        $data = [];
        switch($platformtype) {
            case self::PLATFORMTYPES['moodle']:
                $data['platformid'] = $platformurl;
                $data['jwksurl'] = $platformurl . '/mod/lti/certs.php';
                $data['accesstokenurl'] = $platformurl . '/mod/lti/token.php';
                $data['authenticationrequesturl'] = $platformurl . '/mod/lti/auth.php';
                break;
        }
        return $data;
    }

    public static function get_platform_types_options(): array {
        return array_map(fn($type) => new lang_string("platform:{$type}", self::COMPONENT), self::PLATFORMTYPES);
    }

    public static function get_page_title($step) {
        $titlename = '';
        if ($step === 1) {
            $titlename = get_string('schooldetails', self::COMPONENT);
        } else if ($step === 2) {
            $titlename = get_string('tooldetails', self::COMPONENT);
        } else if ($step === 3) {
            $titlename = get_string('platform:details', self::COMPONENT);
        } else if ($step === 4) {
            $titlename = get_string('successfullyregistered', self::COMPONENT);
        }
        return $titlename;
    }

    public static function delete_platform(int $platformid) {
        global $DB, $USER;
        $plaformrecord = $DB->get_record('enrol_poodlllti_clients', ['id' => $platformid]);
        if (!empty($plaformrecord) &&
            ($plaformrecord->userid == $USER->id ||
            has_capability('enrol/poodlllti:manageallplatforms', context_system::instance()))
        ) {
            $plaformrecord->deleted = 1;
            $DB->update_record('enrol_poodlllti_clients', $plaformrecord);

            $task = new delete_platform();
            $task->set_custom_data(['platformid' => $platformid]);
            manager::queue_adhoc_task($task, true);
            return true;
        }
        return false;
    }
}
