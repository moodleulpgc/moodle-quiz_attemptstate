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
 * This file defines the options for the quiz attemptstate report.
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_attemptstate;

use context_module;
use quiz_attempts_report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_options.php');

/**
 * This file defines the options for the quiz attemptstate report.
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_display_options extends \mod_quiz_attempts_report_options {

    /**@var int Last changed row id */
    public $lastchanged;

    /**
     * @var array string user field => bool whether to display.
     */
    public $userinfovisibility;

    public function __construct($mode, $quiz, $cm, $course) {
        parent::__construct($mode, $quiz, $cm, $course);
        $this->attempts = quiz_attempts_report::ENROLLED_WITH;

        $this->userinfovisibility = self::possible_user_info_visibility_settings($cm);
    }

    public function resolve_dependencies() {
        parent::resolve_dependencies();
        // We only want to show the checkbox to delete attempts
        // if the user has permissions and if the report mode is showing attempts.
        $this->checkboxcolumn = has_capability('mod/quiz:deleteattempts', context_module::instance($this->cm->id))
                && ($this->attempts != quiz_attempts_report::ENROLLED_WITHOUT);
    }

    public function setup_from_params() {
        parent::setup_from_params();
        $this->lastchanged = optional_param('lastchanged', 0, PARAM_INT);
        $fields = optional_param('userinfo', null, PARAM_ALPHAEXT);
        if ($fields !== null) {
            $this->parse_user_info_visibility($fields);
        }
    }

    protected function get_url_params() {
        $params = parent::get_url_params();
        $params['userinfo'] = $this->combine_user_info_visibility();
        return $params;
    }

    public function process_settings_from_form($fromform) {
        parent::process_settings_from_form($fromform);
        foreach ($this->userinfovisibility as $name => $notused) {
            $this->userinfovisibility[$name] = (bool) $fromform->{'show' . $name};
        }
    }

    public function get_initial_form_data() {
        $toform = parent::get_initial_form_data();

        foreach ($this->userinfovisibility as $name => $show) {
            $toform->{'show' . $name} = $show;
        }

        return $toform;
    }

    public function setup_from_user_preferences() {
        parent::setup_from_user_preferences();
        $this->parse_user_info_visibility(
                get_user_preferences('quiz_attemptstate_userinfovisibility',
                    $this->combine_user_info_visibility()));
    }

    /**
     * Update the user preferences so they match the settings in this object.
     * (For those settings that are backed by user-preferences).
     */
    public function update_user_preferences() {
        parent::update_user_preferences();
        set_user_preference('quiz_attemptstate_userinfovisibility', $this->combine_user_info_visibility());
    }

    /**
     * Combine the user field visibility settings into one value.
     *
     * @return string value to use as a URL param or user pref.
     */
    public function combine_user_info_visibility(): string {
        $userinfo = [];
        foreach ($this->userinfovisibility as $name => $shown) {
            if ($shown) {
                $userinfo[] = $name;
            }
        }
        return implode('-', $userinfo);
    }

    /**
     * Split a string like one from combine_user_info_visibility to set the settings.
     *
     * @param string $combined param value to parse.
     */
    protected function parse_user_info_visibility(string $combined): void {
        $fields = explode('-', $combined);
        foreach ($this->userinfovisibility as $name => $notused) {
            $this->userinfovisibility[$name] = in_array($name, $fields);
        }
    }

    /**
     * Considering the site settings, work out what user info visibility settings there should be.
     *
     * @param \stdClass $cm the course_module info for this quiz.
     * @return array setting name => true
     */
    public static function possible_user_info_visibility_settings(\stdClass $cm): array {
        global $CFG;

        $settings = ['fullname' => true];

        $userfields = get_extra_user_fields(context_module::instance($cm->id));
        foreach ($userfields as $field) {
            $settings[$field] = true;
        }

        if (isset($settings['idnumber']) &&
                is_readable($CFG->dirroot . '/mod/quiz/report/gradingstudents/examconfirmationcode.php')) {
            require_once($CFG->dirroot . '/mod/quiz/report/gradingstudents/examconfirmationcode.php');
            if (\quiz_gradingstudents_report_exam_confirmation_code::quiz_can_have_confirmation_code($cm->idnumber)) {
                $settings['examcode'] = true;
            }
        }

        return $settings;
    }

    /**
     * Get the human-readable name of one of the user info visibility settings.
     *
     * @param string $setting one of the settings returned by possible_user_info_visibility_settings.
     * @return string the corresponding name to show in the UI.
     */
    public static function user_info_visibility_settings_name(string $setting): string {
        switch ($setting) {
            case 'examcode':
                return get_string('examcode', 'quiz_attemptstate');
            case 'fullname';
                return get_string('fullnameuser');
            default:
                return get_user_field_name($setting);
        }
    }
}
