<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/lti/lib.php');

use core\http_client;
use enrol_lti\local\ltiadvantage\lib\lti_cookie;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\tool_launch_service;
use enrol_lti\local\ltiadvantage\utility\message_helper;
use Packback\Lti1p3\LtiConstants;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;

// Check if enabled
if (!enrol_is_enabled('poodlllti')) {
    throw new moodle_exception('enrolisdisabled', 'enrol_poodlllti');
}

// Dependent on enrol_lti being capable of processing checks
if (!is_enabled_auth('lti')) {
    // We reuse auth_lti for the heavy lifting of authentication
   throw new moodle_exception('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
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

if ($messagelaunch->isDeepLinkLaunch()) {
    // Authenticate the instructor using standard LTI auth
    // We point the return URL to THIS script
    $url = new moodle_url('/enrol/poodlllti/launch.php', ['launchid' => $messagelaunch->getLaunchId()]);
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

    exit;
}

// 1. Extract LTI Identification Data
$launchdata = $messagelaunch->getLaunchData();
$deploymentid = $launchdata[LtiConstants::DEPLOYMENT_ID] ?? null;
$iss = $launchdata['iss'] ?? null;
$aud = $launchdata['aud'] ?? null;
$clientid = is_array($aud) ? reset($aud) : $aud;

$context = $launchdata[LtiConstants::CONTEXT] ?? [];
$contextid = $context['id'] ?? null;
$resourcelink = $launchdata[LtiConstants::RESOURCE_LINK] ?? [];
$resourceid = $resourcelink['id'] ?? null;
$customparams = $launchdata[LtiConstants::CUSTOM] ?? [];
$sectionid = $customparams['section_id'] ?? null;

if (!$deploymentid || !$contextid || !$resourceid || !$iss || !$clientid) {
    throw new moodle_exception('Missing required LTI launch parameters (deployment_id, context_id, resource_id, iss, or aud)');
}

// Compute Unique Tenant Key
$tenantkey = md5(implode(':', [$iss, $clientid, $deploymentid]));

// 2. Find/Provision Category
$category = $DB->get_record('course_categories', ['idnumber' => $tenantkey]);
if (!$category) {
    throw new moodle_exception('Tenant category not found for deployment ID: ' . s($deploymentid) . ' (Expected Hash: ' . $tenantkey . ')');
}

// 3. Find/Provision Course
$coursetargetidnumber = md5($tenantkey . ':' . $contextid);
$course = $DB->get_record('course', ['idnumber' => $coursetargetidnumber]);

if (!$course) {
    // Try to find a course from the "Pool" in this category
    $sql = "SELECT * FROM {course}
             WHERE category = :category
               AND (idnumber IS NULL OR idnumber = '')
             ORDER BY sortorder ASC";
    $poolcourse = $DB->get_record_sql($sql, ['category' => $category->id], IGNORE_MULTIPLE);

    if (!$poolcourse) {
        throw new moodle_exception('No available pool course found in category: ' . s($category->name));
    }

    // Claim the pool course
    $poolcourse->idnumber = $coursetargetidnumber;
    $poolcourse->fullname = $context['title'] ?? ('Course ' . $contextid);
    $poolcourse->shortname = $context['label'] ?? $poolcourse->idnumber;
    $DB->update_record('course', $poolcourse);
    $course = $poolcourse;
}

// 4. Find/Provision Activity
// The activity is identified by its CM idnumber = resourceid
$modid = $customparams['modid'] ?? optional_param('modid', 0, PARAM_INT);
$cm = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => $resourceid]);

if (!$cm && $modid) {
    // Attempt to find by modid if idnumber not yet set (common in first launch after deep link)
    $cm = $DB->get_record('course_modules', ['id' => $modid, 'course' => $course->id]);
    if ($cm && (empty($cm->idnumber) || $cm->idnumber == $resourceid)) {
        // Seal the mapping
        $DB->set_field('course_modules', 'idnumber', $resourceid, ['id' => $cm->id]);
    } else {
        $cm = null; // Don't trust it if it belongs to another resource
    }
}

if (!$cm) {
    // TRIGGER CLONING LOGIC
    require_once(__DIR__ . '/clonelib.php');
    $cmid = poodlllti_find_and_clone_activity($resourceid, $course->id);
    if (!$cmid) {
        throw new moodle_exception('Activity not found and could not be cloned for resource ID: ' . s($resourceid));
    }
    $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
}

// 5. Authenticate
$auth = get_auth_plugin('lti');
$returnurl = new moodle_url('/enrol/poodlllti/launch.php', ['launchid' => $messagelaunch->getLaunchId()]);

$auth->complete_login(
    $launchdata,
    $returnurl,
    auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY
);

require_login(null, false);
global $USER;

// 6. Group Management
if ($sectionid) {
    $groupid = null;
    $group = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => $sectionid]);
    if (!$group) {
        // Create group
        $groupdata = new stdClass();
        $groupdata->courseid = $course->id;
        $groupdata->idnumber = $sectionid;
        $groupdata->name = $customparams['section_title'] ?? ('Section ' . $sectionid);
        $groupid = groups_create_group($groupdata);
    } else {
        $groupid = $group->id;
    }

    if ($groupid && !groups_is_member($groupid, $USER->id)) {
        groups_add_member($groupid, $USER->id);
    }
}

// 7. Enrolment Logic (specific to the CM)
$enrol = enrol_get_plugin('poodlllti');
$modcontext = context_module::instance($cm->id);

$sql = "SELECT e.*, t.roleinstructor, t.rolelearner
          FROM {enrol} e
          JOIN {enrol_lti_tools} t ON t.enrolid = e.id
         WHERE e.enrol = :enrol
           AND t.contextid = :contextid";
$params = ['enrol' => 'poodlllti', 'contextid' => $modcontext->id];
$instance = $DB->get_record_sql($sql, $params);

if (!$instance) {
    // Create new instance for this CM
    $lticonfig = get_config('enrol_lti');
    $instructorroleid = key(get_archetype_roles('editingteacher'));
    $learnerroleid = key(get_archetype_roles('student'));
    $fields = [
        'contextid' => $modcontext->id,
        'institution' => $lticonfig->institution ?? get_site()->fullname,
        'city' => $lticonfig->city ?? $CFG->defaultcity ?? '',
        'country' => $lticonfig->country ?? $CFG->country ?? 'AU',
    ];
    $enrolid = $enrol->add_default_instance($course, $fields);
    $instance = $DB->get_record_sql($sql, $params);
}

if ($instance) {
    $isinstructor = message_helper::is_instructor_launch($launchdata);
    $roleid = $isinstructor ? $instance->roleinstructor : $instance->rolelearner;
    $enrol->enrol_user($instance, $USER->id, $roleid);
}

$toollaunchservice = new tool_launch_service(
    new deployment_repository(),
    new application_registration_repository(),
    new resource_link_repository(),
    new user_repository(),
    new context_repository()
);
[$userid, $resource] = $toollaunchservice->user_launches_tool($USER, $messagelaunch);

// 8. Redirect to Activity
redirect(new moodle_url('/mod/minilesson/view.php', ['id' => $cm->id]));
