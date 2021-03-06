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
 * Attempt created event class.
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_attemptstate\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Attempt created event class.
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_closed extends base_event {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public static function get_name() {
        return get_string('event_attempt_closed', 'quiz_attemptstate');
    }

    public function get_description() {
        return 'The user with id ' . $this->userid . ' has closed the attempt with id ' . $this->other['attemptid'] .
                ' for the user with id ' . $this->relateduserid . ' for the quiz with course module id ' .
                $this->contextinstanceid . '.';
    }

    public function get_url() {
        return new \moodle_url('/mod/quiz/report/attemptstate/attemptsheet.php', ['attempt' => $this->other['attemptid']]);
    }
}
