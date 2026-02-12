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
 * Handles LTI 1.3 deep linking launches for Poodll LTI.
 *
 * @package    enrol_poodlllti
 * @copyright  2023 Poodll
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\http_client;
use enrol_lti\local\ltiadvantage\lib\lti_cookie;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;
use enrol_lti\local\ltiadvantage\repository\published_resource_repository;

require_once(__DIR__ . '/../../config.php');
global $OUTPUT, $PAGE, $CFG;
require_once($CFG->libdir . '/filelib.php');

$idtoken = optional_param('id_token', null, PARAM_RAW);
$launchid = optional_param('launchid', null, PARAM_RAW);

// Enable checks
if (!enrol_is_enabled('poodlllti')) {
    throw new moodle_exception('enrolisdisabled', 'enrol_poodlllti');
}
// Dependent on enrol_lti being capable of processing checks
if (!is_enabled_auth('lti')) { 
    // We reuse auth_lti for the heavy lifting of authentication
   throw new moodle_exception('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
}

if (empty($idtoken) && empty($launchid)) {
    throw new coding_exception('Error: launch requires id_token');
}

// Reuse enrol_lti classes for launch handling
$sesscache = new launch_cache_session();
$issdb = new issuer_database(new application_registration_repository(), new deployment_repository());
$cookie = new lti_cookie();
$serviceconnector = new LtiServiceConnector($sesscache, new http_client());

if ($idtoken) {
    // Initial launch
    $messagelaunch = LtiMessageLaunch::new($issdb, $sesscache, $cookie, $serviceconnector)
        ->initialize($_POST);
}

if ($launchid) {
    // Returning from auth
    $messagelaunch = LtiMessageLaunch::fromCache($launchid, $issdb, $sesscache, $cookie, $serviceconnector);
}

if (empty($messagelaunch)) {
    throw new moodle_exception('Bad launch. Deep linking launch data could not be found');
}

// Authenticate the instructor using standard LTI auth
// We point the return URL to THIS script
$url = new moodle_url('/enrol/poodlllti/launch_deeplink.php', ['launchid' => $messagelaunch->getLaunchId()]);
$auth = get_auth_plugin('lti');
$auth->complete_login(
    $messagelaunch->getLaunchData(),
    $url,
    auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY // Or AUTO if we want to auto-create users
);

// If we are here, authentication passed
require_login(null, false);
global $USER;

// NOW: Redirect to mod/minilesson/ltistart.php
// We pass the launchid so ltistart.php can hydrate the launch object
$redirecturl = new moodle_url('/mod/minilesson/ltistart.php', ['launchid' => $messagelaunch->getLaunchId()]);
redirect($redirecturl);
