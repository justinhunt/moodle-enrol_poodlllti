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
use core\di;
use core_course_category;
use core_form\dynamic_form;
use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\entity\deployment;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\tool_deployment_service;
use enrol_poodlllti\local\platform;
use enrol_poodlllti\util;
use HTML_QuickForm_hidden;
use html_writer;
use moodle_url;
use stdClass;

/**
 * Class edit
 *
 * @package    enrol_poodlllti
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit extends dynamic_form {

    protected ?platform $client = null;

    protected ?bool $freezall = null;

    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        require_capability('enrol/poodlllti:cancreateplatform', $context);
    }

    public function process_dynamic_submission() {
        global $USER;
        $formdata = $this->get_data();
        if (!$formdata) {
            return null;
        }
        $redirecturl = $this->get_page_url_for_dynamic_submission();

        if ($formdata->step == 1) {
            if (!$this->freezall) {
                if (!$this->client->get('userid')) {
                    $this->client->set('userid', $USER->id);
                }
                $this->client->set('schoolname', $formdata->schoolname);
                $this->client->set('platformtype', $formdata->platformtype);
                $this->client->set('platformurl', $formdata->platformurl);

                $this->assign_app($formdata);

                $this->client->upsert();
            }

            $redirecturl->param('step', 2);
        }

        if ($formdata->step == 2) {
            $redirecturl->param('step', 3);
        }

        if ($formdata->step == 3) {
            if (!$this->freezall) {
                $this->update_app($formdata);
                $this->assign_deployment($formdata);
                $this->assign_category($formdata);

                $this->client->upsert();
            }

            $redirecturl->param('step', 4);
        }
        if (!empty($formdata->admindashboard)) {
            $redirecturl->param('admindashboard', 1);
        }

        $redirecturl->param('id', $this->client->get('id'));

        return $redirecturl;
    }

    public function assign_app(object $formdata): self {
        $apprepo = di::get(application_registration_repository::class);

        $appreg = $this->get_app();
        if (!$appreg) {
            $appregid = $this->find_unused_app_registration_id($formdata->id);
            $appreg = $apprepo->find($appregid);
        }
        $appreg->set_name($formdata->schoolname);
        $apprepo->save($appreg);

        $this->client->set('ltiappregid', $appreg->get_id());

        return $this;
    }

    public function get_app(): ?application_registration {
        $apprepo = di::get(application_registration_repository::class);
        $appregid = $this->client->get('ltiappregid');
        return $apprepo->find($appregid);
    }

    public function get_app_deployment(): ?deployment {
        $deploymentreg = di::get(deployment_repository::class);
        $appdeploymentid = $this->client->get('ltideploymentid');
        return $deploymentreg->find($appdeploymentid);
    }

    public function update_app(object $formdata): self {
        $apprepo = di::get(application_registration_repository::class);
        $appreg = $this->get_app();
        if ($appreg) {
            $appreg->set_name($this->client->get('schoolname'));
            $appreg->set_platformid(new moodle_url($formdata->platformid));
            $appreg->set_clientid($formdata->clientid);
            $appreg->set_jwksurl(new moodle_url($formdata->jwksurl));
            $appreg->set_authenticationrequesturl(new moodle_url($formdata->authenticationrequesturl));
            $appreg->set_accesstokenurl(new moodle_url($formdata->accesstokenurl));
            $apprepo->save($appreg);
        }
        return $this;
    }

    public function assign_deployment(object $formdata): self {
        $deploymentservice = new tool_deployment_service(
            new application_registration_repository(),
            new deployment_repository(),
            new resource_link_repository(),
            new context_repository(),
            new user_repository()
        );

        $deploymentdata = new stdClass();
        $deploymentdata->deployment_name = $this->client->get('schoolname');
        $deploymentdata->registration_id = $this->client->get('ltiappregid');
        $deploymentdata->deployment_id = $formdata->deploymentid;
        $deployment = $deploymentservice->add_tool_deployment($deploymentdata);

        $this->client->set('ltideploymentid', $deployment->get_id());

        return $this;
    }

    public function assign_category(object $formdata): self {
        global $DB;

        $category = $DB->get_record('course_categories', ['idnumber' => $formdata->deploymentid]);
        if (!empty($category)) {
            $category->name = $this->client->get('schoolname');
            $coursecat = core_course_category::get($category->id, MUST_EXIST);
            $coursecat->update($category);
            $this->client->set('categoryid', $category->id);
        } else {
            $categoryid = $this->find_unused_category($formdata->id);
            if ($categoryid) {
                $data = new stdClass();
                $data->name = $this->client->get('schoolname');
                $data->idnumber = $formdata->deploymentid;
                $coursecat = core_course_category::get($categoryid, MUST_EXIST);
                $coursecat->update($data);
                $this->client->set('categoryid', $categoryid);
            }
        }
        return $this;
    }

    public function set_data_for_dynamic_submission(): void {

        $formdata = [
            'id' => $this->client->get('id'),
            'step' => $this->param('step'),
            'admindashboard' => $this->param('admindashboard') ?? 0,
        ];
        if ($this->client->get('schoolname')) {
            $formdata['step'] = $formdata['step'] ?: 2;
            $formdata['schoolname'] = $this->client->get('schoolname');
            $formdata['platformtype'] = $this->client->get('platformtype');
            $formdata['platformurl'] = $this->client->get('platformurl');
        }
        if ($formdata['step'] == 3) {
            $formdata += util::get_prefilled_data_by_platform(
                $this->client->get('platformtype'),
                $this->client->get('platformurl'),
            );
            if ($this->client->get('ltideploymentid') > 0 && $appreg = $this->get_app()) {
                $formdata['platformid'] = $appreg->get_platformid();
                $formdata['clientid'] = $appreg->get_clientid();
                $formdata['jwksurl'] = $appreg->get_jwksurl();
                $formdata['accesstokenurl'] = $appreg->get_accesstokenurl();
                $formdata['authenticationrequesturl'] = $appreg->get_authenticationrequesturl();
                if ($deployment = $this->get_app_deployment()) {
                    $formdata['deploymentid'] = $deployment->get_deploymentid();
                }
            }
        }
        $this->set_data($formdata);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $urlparams['id'] = $this->param('id');
        $url = new moodle_url('/enrol/poodlllti/platform/add.php', $urlparams);
        return $url;
    }

    public function definition() {
        $mform = $this->_form;
        $mform->registerNoSubmitButton('backstep');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('hidden', 'admindashboard');
        $mform->setType('admindashboard', PARAM_INT);
        $mform->setDefault('admindashboard', 0);

        $mform->addElement('hidden', 'step');
        $mform->setType('step', PARAM_INT);
        $mform->setDefault('step', 1);

        $clientid = $this->param('id');
        $this->client ??= $this->_customdata['client'] ?? new platform($clientid);
        $this->freezall = !empty($this->get_app_deployment());

        $step = $this->get_step();
        $mform->setConstant('step', $step);
    }

    public function get_step() {
        $step = $this->param('step');
        if ($this->param('backstep')) {
            $step = max(1, $step - 1);
        }
        return $step;
    }

    public function definition_after_data() {
        global $CFG, $OUTPUT;
        $mform = $this->_form;
        $step = $mform->getElement('step')->getValue();
        $admindashboard = $mform->getElement('admindashboard')->getValue();
        $strrequired = get_string('required');
        $size = ['size' => 60];

        $titlename = util::get_page_title($step);
        $dashboardurl = new moodle_url(
            !empty($admindashboard) ?
                '/enrol/poodlllti/platform/manage_platform.php' :
                '/enrol/poodlllti/platform/manage.php'
        );

        $headerhtml = html_writer::start_div('d-flex justify-content-between mb-4');
        $headerhtml .= html_writer::tag('h3', $titlename);
        $headerhtml .= html_writer::link(
            $dashboardurl,
            get_string('backtodashboard', util::COMPONENT),
            ['class' => 'btn btn-primary']
        );
        $headerhtml .= html_writer::end_div();
        $mform->addElement('html', $headerhtml);

        if ($step === 1 || !$this->client->get('schoolname')) {
            $mform->addElement('text', 'schoolname', get_string('schoolname', util::COMPONENT), $size);
            $mform->setType('schoolname', PARAM_TEXT);

            $lmsoptions = ['' => get_string('choose')] + util::get_platform_types_options();
            $mform->addElement('select', 'platformtype', get_string('platformtype', util::COMPONENT), $lmsoptions);
            $mform->setType('platformtype', PARAM_TEXT);

            $mform->addElement('text', 'platformurl', get_string('platformurl', util::COMPONENT), $size);
            $mform->setType('platformurl', PARAM_URL);

            if (!$this->param('backtodash', null, PARAM_BOOL)) {
                if ($this->freezall) {
                    $mform->freeze(['schoolname', 'platformtype', 'platformurl']);
                } else if ($this->get_app()) {
                    $mform->freeze(['platformtype', 'platformurl']);
                    $mform->addRule('schoolname', $strrequired, 'required', null, 'client');
                } else {
                    $mform->addRule('schoolname', $strrequired, 'required', null, 'client');
                    $mform->addRule('platformtype', $strrequired, 'required', null, 'client');
                    $mform->addRule('platformurl', $strrequired, 'required', null, 'client');
                }
            }
        }

        if ($step === 2) {

            $platformname = html_writer::tag(
                'h5',
                get_string('schoolname:title', util::COMPONENT, $this->client->get('schoolname'))
            );
            $mform->addElement('html', $platformname);

            $registration = $this->get_app();
            $templatecontext['manual_registration_urls'] = [
                [
                    'name' => get_string('registrationurl', 'enrol_lti'),
                    'url' => $CFG->wwwroot . '/enrol/poodlllti/register.php?token=' . $registration->get_uniqueid(),
                    'id' => uniqid()
                ],
                [
                    'name' => get_string('toolurl', 'enrol_lti'),
                    'url' => $CFG->wwwroot . '/enrol/poodlllti/launch.php',
                    'id' => uniqid()
                ],
                [
                    'name' => get_string('jwksurl', 'enrol_poodlllti'),
                    'url' => $CFG->wwwroot . '/enrol/lti/jwks.php',
                    'id' => uniqid()
                ],
                [
                    'name' => get_string('loginurl', 'enrol_lti'),
                    'url' => $CFG->wwwroot . '/enrol/poodlllti/login.php?id=' . $registration->get_uniqueid(),
                    'id' => uniqid()
                ],
                [
                    'name' => get_string('deeplinkingurl', 'enrol_lti'),
                    'url' => $CFG->wwwroot . '/enrol/poodlllti/launch_deeplink.php',
                    'id' => uniqid()
                ],
                [
                    'name' => get_string('redirectionuris', 'mod_lti'),
                    'url' => join(PHP_EOL, [
                        $CFG->wwwroot . '/enrol/poodlllti/launch.php',
                        $CFG->wwwroot . '/enrol/poodlllti/launch_deeplink.php',
                    ]),
                    'id' => uniqid(),
                    'islongtext' => true,
                ],
            ];
            $mform->addElement('html', $OUTPUT->render_from_template(
                'enrol_poodlllti/tool_details',
                $templatecontext
            ));
        }

        if ($step === 3) {

            $platformname = html_writer::tag(
                'h5',
                get_string('schoolname:title', util::COMPONENT, $this->client->get('schoolname'))
            );
            $mform->addElement('html', $platformname);

            $mform->addElement('text', 'platformid', get_string('platformid', util::COMPONENT), $size);
            $mform->setType('platformid', PARAM_TEXT);

            $mform->addElement('text', 'clientid', get_string('clientid', util::COMPONENT), $size);
            $mform->setType('clientid', PARAM_TEXT);

            $mform->addElement('text', 'deploymentid', get_string('deploymentid', util::COMPONENT), $size);
            $mform->setType('deploymentid', PARAM_TEXT);

            $mform->addElement('text', 'jwksurl', get_string('jwksurl', util::COMPONENT), $size);
            $mform->setType('jwksurl', PARAM_URL);

            $mform->addElement('text', 'accesstokenurl', get_string('accesstokenurl', util::COMPONENT), $size);
            $mform->setType('accesstokenurl', PARAM_URL);

            $mform->addElement('text', 'authenticationrequesturl', get_string('authrequesturl', util::COMPONENT), $size);
            $mform->setType('authenticationrequesturl', PARAM_URL);

            if (!$this->param('backtodash', null, PARAM_BOOL)) {
                if ($this->freezall) {
                    $mform->freeze(['platformid', 'clientid', 'deploymentid', 'jwksurl', 'accesstokenurl', 'authenticationrequesturl']);
                } else {
                    $mform->addRule('platformid', $strrequired, 'required', null, 'client');
                    $mform->addRule('clientid', $strrequired, 'required', null, 'client');
                    $mform->addRule('deploymentid', $strrequired, 'required', null, 'client');
                    $mform->addRule('jwksurl', $strrequired, 'required', null, 'client');
                    $mform->addRule('accesstokenurl', $strrequired, 'required', null, 'client');
                    $mform->addRule('authenticationrequesturl', $strrequired, 'required', null, 'client');
                }
            }
        }

        if ($step == 4) {
            $mform->addElement('static', 'success', '', get_string('platform:success', util::COMPONENT));
        }

        $buttonarray = [];
        if ($step > 1 && $step < 4) {
            $buttonarray[1] = $mform->createElement('submit', 'backstep', get_string('back'));
        }

        if ($step < 3) {
            $submitbutton = get_string('next');
        } else {
            $submitbutton = get_string('finish', util::COMPONENT);
        }

        if (!$this->freezall || $step < 3) {
            $buttonarray[0] = $mform->createElement('submit', 'submitbutton', $submitbutton ?? get_string('savechanges'));
        }

        $mform->addGroup($buttonarray, 'buttongroup', '', [' '], false);
    }

    public function param(string $name, $default = null, $type = null) {
        $mform = $this->_form;
        $element = new HTML_QuickForm_hidden($name);
        $type ??= $this->_form->_types[$name] ?? null;
        if (in_array($name, $mform->_noSubmitButtons)) {
            $type ??= PARAM_BOOL;
        }
        $default ??= $element->_findValue($mform->_defaultValues);
        return $this->optional_param($name, $default, $type);
    }

    public function find_unused_app_registration_id(int $id): int {
        global $DB;
        return $DB->get_field_sql(
            "SELECT MIN(app.id) FROM {enrol_lti_app_registration} app
            LEFT JOIN {".platform::TABLE."} p ON p.ltiappregid = app.id
            WHERE COALESCE(p.id, 0) IN (0, :id) AND app.status = :appstatus",
            [
                'id' => $id,
                'appstatus' => application_registration::REGISTRATION_STATUS_INCOMPLETE
            ]
        );
    }

    public function find_unused_category(int $id): int {
        global $DB;
        return $DB->get_field_sql(
            "SELECT MIN(cat.id) FROM {course_categories} cat
            LEFT JOIN {".platform::TABLE."} p ON p.categoryid = cat.id
            WHERE COALESCE(p.id, 0) IN (0, :id) AND cat.visible = 1 AND cat.parent = 0 AND COALESCE(cat.idnumber, '') = ''",
            [
                'id' => $id,
            ]
        );
    }

    public function check_access(): void {
        $this->check_access_for_dynamic_submission();
    }
}
