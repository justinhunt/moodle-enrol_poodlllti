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

namespace enrol_poodlllti\local;

use core\persistent;
use enrol_poodlllti\util;

/**
 * Class platform
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class platform extends persistent {

    public const TABLE = 'enrol_poodlllti_clients';

    protected static function define_properties() {
        return [
            'userid' => [
                'type' => PARAM_INT,
            ],
            'schoolname' => [
                'type' => PARAM_TEXT,
            ],
            'categoryid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'ltiappregid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'ltideploymentid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'platformtype' => [
                'type' => PARAM_TEXT,
                'options' => array_values(util::PLATFORMTYPES)
            ],
            'platformurl' => [
                'type' => PARAM_URL,
            ],
        ];
    }

    public function upsert(): self {
        if ($this->get('id')) {
            $this->update();
        } else {
            $this->create();
        }
        return $this;
    }

}
