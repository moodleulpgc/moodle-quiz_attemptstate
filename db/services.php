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
 * Quiz answer sheet services.
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
        'quiz_attemptstate_create_attempt' => [
                'classname' => 'quiz_attemptstate_external',
                'methodname' => 'create_attempt',
                'classpath' => '',
                'description' => 'Create attempt for users',
                'type' => 'write',
                'ajax' => true,
                'capabilities' => 'quiz/attemptstate:newattempt'
        ],
        'quiz_attemptstate_create_event' => [
                'classname' => 'quiz_attemptstate_external',
                'methodname' => 'create_event',
                'classpath' => '',
                'description' => 'Record log events from answer sheet',
                'type' => 'write',
                'ajax' => true
        ]
];
