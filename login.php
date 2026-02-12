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
 * LTI 1.3 login endpoint for Poodll LTI.
 *
 * @package    enrol_poodlllti
 * @copyright  2023 Poodll
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use auth_lti\local\ltiadvantage\utility\cookie_helper;
use enrol_lti\local\ltiadvantage\lib\lti_cookie;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use Packback\Lti1p3\LtiOidcLogin;
use Packback\Lti1p3\LtiServiceConnector;
use core\http_client;

require_once(__DIR__."/../../config.php");

// Required fields for OIDC 3rd party initiated login.
$iss = required_param('iss', PARAM_URL);
$loginhint = required_param('login_hint', PARAM_RAW);
$targetlinkuri = required_param('target_link_uri', PARAM_URL);
$ltimessagehint = optional_param('lti_message_hint', null, PARAM_RAW);

// Whitelist the Poodll LTI endpoints.
$validuris = [
    (new moodle_url('/enrol/poodlllti/launch.php'))->out(false),
    (new moodle_url('/enrol/poodlllti/launch_deeplink.php'))->out(false)
];

if (!in_array($targetlinkuri, $validuris)) {
    // If not in our list, check if it's in the core list as a fallback.
    $corevaliduris = [
        (new moodle_url('/enrol/lti/launch.php'))->out(false),
        (new moodle_url('/enrol/lti/launch_deeplink.php'))->out(false)
    ];
    if (!in_array($targetlinkuri, $corevaliduris)) {
        throw new coding_exception('The target_link_uri param must match one of the redirect URIs set during tool registration.');
    }
}

// Client ID handling.
global $_REQUEST;
if (empty($_REQUEST['client_id']) && !empty($_REQUEST['id'])) {
    $_REQUEST['client_id'] = $_REQUEST['id'];
}

// Cookie check.
if (!isloggedin()) {
    // We use a custom return URL pointing back to this login.php.
    cookie_helper::do_cookie_check(new moodle_url('/enrol/poodlllti/login.php', [
        'iss' => $iss,
        'login_hint' => $loginhint,
        'target_link_uri' => $targetlinkuri,
        'lti_message_hint' => $ltimessagehint,
        'client_id' => $_REQUEST['client_id'],
    ]));
    if (!cookie_helper::cookies_supported()) {
        global $OUTPUT, $PAGE;
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/enrol/poodlllti/login.php'));
        $PAGE->set_pagelayout('popup');
        echo $OUTPUT->header();
        // Since we are in enrol_poodlllti, we might not have a renderer with render_cookies_required_notice.
        // But we can use the one from enrol_lti.
        $renderer = $PAGE->get_renderer('enrol_lti');
        echo $renderer->render_cookies_required_notice();
        echo $OUTPUT->footer();
        die();
    }
}

// Do the OIDC login.
// Note: issuer_database and repositories from enrol_lti are used as we share the same registration tables.
$issdb = new issuer_database(new application_registration_repository(), new deployment_repository());
$sesscache = new launch_cache_session();
$cookie = new lti_cookie();

$redirecturl = LtiOidcLogin::new($issdb, $sesscache, $cookie)
    ->getRedirectUrl($targetlinkuri, $_REQUEST);

redirect($redirecturl);
