<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/lti/lib.php');

use core\http_client;
use enrol_lti\local\ltiadvantage\lib\lti_cookie;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;

// Check if enabled
if (!enrol_is_enabled('poodlllti')) {
    throw new moodle_exception('enrolisdisabled', 'enrol_poodlllti');
}

$idtoken = optional_param('id_token', null, PARAM_RAW);
$launchid = optional_param('launchid', null, PARAM_RAW);
$modid = optional_param('modid', 0, PARAM_INT); // Passed via custom params usually, assuming query param for now or extracted from launch custom params

if (empty($idtoken) && empty($launchid)) {
     // If we rely on custom params, they come IN the JWT.
     // So we need to parse JWT first.
     throw new moodle_exception('Missing LTI Launch Data');
}

$sesscache = new launch_cache_session();
$issdb = new issuer_database(new application_registration_repository(), new deployment_repository());
$cookie = new lti_cookie();
$serviceconnector = new LtiServiceConnector($sesscache, new http_client());

if ($idtoken) {
    $messagelaunch = LtiMessageLaunch::new($issdb, $sesscache, $cookie, $serviceconnector)
        ->initialize($_POST);
} else {
    $messagelaunch = LtiMessageLaunch::fromCache($launchid, $issdb, $sesscache, $cookie, $serviceconnector);
}

if (empty($messagelaunch)) {
    throw new moodle_exception('Bad Launch');
}

// 1. Authenticate (created user sessions)
// We use enrol_lti auth logic but we need to handle the redirection loop ourselves if not logged in
$auth = get_auth_plugin('lti');

// Helper to check if we are already logged in as the correct user?
// auth_lti::complete_login handles this.
// But we need to preserve 'modid' if it was passed.
// Wait, 'modid' passed in the Deep Link custom params will be in $messagelaunch->getLaunchData()['https://purl.imsglobal.org/spec/lti/claim/custom']['modid']

$launchdata = $messagelaunch->getLaunchData();
$customparams = $launchdata['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? [];
if (empty($modid)) {
    if (isset($customparams['modid'])) {
        $modid = $customparams['modid'];
    } else if (isset($customparams['id'])) {
        // Fallback: Find modid via Tool UUID (standard for Moodle LTI 1.3 Tools)
        $resourceuuid = $customparams['id'];
        $resource = array_values(\enrol_lti\helper::get_lti_tools(['uuid' => $resourceuuid]));
        $resource = $resource[0] ?? null;
        if ($resource && $resource->contextid) {
            $context = context::instance_by_id($resource->contextid);
            if ($context->contextlevel == CONTEXT_MODULE) {
                $modid = $context->instanceid;
            }
        }
    }
}

if (empty($modid)) {
    throw new moodle_exception('No module ID provided in launch.');
}

// URL to return to after auth (this script)
$returnurl = new moodle_url('/enrol/poodlllti/launch.php', ['launchid' => $messagelaunch->getLaunchId()]);

$auth->complete_login(
    $launchdata,
    $returnurl,
    auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY // Or AUTO if we want to auto-create users
);

require_login(null, false);

// 2. Enrolment Logic
// We need to ensure the user is enrolled in the course containing $modid
$cm = get_coursemodule_from_id('', $modid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Check if enrolled
if (!is_enrolled(context_course::instance($course->id), $USER->id)) {
    // Enrol them using enrol_poodlllti instance
    // We must find the SPECIFIC instance for this module context
    $enrol = enrol_get_plugin('poodlllti');
    $modcontext = context_module::instance($cm->id);
    
    $sql = "SELECT e.*, t.roleinstructor, t.rolelearner
              FROM {enrol} e
              JOIN {enrol_lti_tools} t ON t.enrolid = e.id
             WHERE e.enrol = :enrol 
               AND t.contextid = :contextid";
    $params = [
        'enrol' => 'poodlllti',
        'contextid' => $modcontext->id
    ];
    $instance = $DB->get_record_sql($sql, $params);
    
    if (!$instance) {
         // This shouldn't happen if they linked correctly, but as a fallback:
         $instanceid = $enrol->add_instance($course, ['contextid' => $modcontext->id]);
         $instance = $DB->get_record_sql($sql, $params);
    }
    
    if ($instance) {
        // Determine role based on LTI roles
        $ltiroles = $launchdata['https://purl.imsglobal.org/spec/lti/claim/roles'] ?? [];
        $isinstructor = false;
        foreach ($ltiroles as $role) {
            if (strpos($role, 'Membership#Instructor') !== false || strpos($role, 'system/person#Administrator') !== false) {
                $isinstructor = true;
                break;
            }
        }
        $roleid = $isinstructor ? $instance->roleinstructor : $instance->rolelearner;
        
        // Force enrolment with the correct role
        $enrol->enrol_user($instance, $USER->id, $roleid);
    }
} else {
    // Even if enrolled, confirm they have the correct role (or just re-call enrol_user which is safe)
    $enrol = enrol_get_plugin('poodlllti');
    $modcontext = context_module::instance($cm->id);
    $sql = "SELECT e.*, t.roleinstructor, t.rolelearner
              FROM {enrol} e
              JOIN {enrol_lti_tools} t ON t.enrolid = e.id
             WHERE e.enrol = :enrol 
               AND t.contextid = :contextid";
    $params = ['enrol' => 'poodlllti', 'contextid' => $modcontext->id];
    $instance = $DB->get_record_sql($sql, $params);
    
    if ($instance) {
        $ltiroles = $launchdata['https://purl.imsglobal.org/spec/lti/claim/roles'] ?? [];
        $isinstructor = false;
        foreach ($ltiroles as $role) {
            if (strpos($role, 'Membership#Instructor') !== false || strpos($role, 'system/person#Administrator') !== false) {
                $isinstructor = true;
                break;
            }
        }
        $roleid = $isinstructor ? $instance->roleinstructor : $instance->rolelearner;
        $enrol->enrol_user($instance, $USER->id, $roleid);
    }
}

// 3. Redirect to Activity
redirect(new moodle_url('/mod/minilesson/view.php', ['id' => $cm->id]));
