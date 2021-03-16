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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/quiz/override_form.php');

/**
 * This file defines the options for the quiz attemptstate report.
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extend_attempt_form extends \quiz_override_form {

    /** @var array, if provided. */
    protected $attemptids;
    
    /** @var string, if provided. */
    protected $action;

    
    /**
     * Constructor.
     * @param moodle_url $submiturl the form action URL.
     * @param object course module object.
     * @param object the quiz settings object.
     * @param context the quiz context.
     */
    public function __construct($submiturl, $cm, $quiz, $context, $attemptids, $action) {
        
        $this->attemptids = $attemptids; 
        $this->action = $action;
        $override = new \stdClass();
        $override->groupid = 0; 
        $override->userid = -1;

        parent::__construct($submiturl, $cm, $quiz, $context, false, $override);
    }    

    public function definition_after_data() {
        $mform =& $this->_form;
        
        $mform->addElement('hidden', 'display', 0);
        $mform->setType('display', PARAM_BOOL);
        
        $mform->addElement('hidden', $this->action, 1);
        $mform->setType($this->action, PARAM_ALPHANUMEXT);        

        $mform->addElement('hidden', 'action', $this->action);
        $mform->setType('action', PARAM_ALPHANUMEXT);        
        
        if ($mform->elementExists('attempts')) { 
            $mform->hideIf('attempts', 'action', 'eq', 'extend');
            if($this->action == 'extend') {
                $mform->setDefault('attempts', 0);  
            }
            if($this->action == 'new') {
                $mform->setDefault('attempts', 1);  
            }
            $mform->addHelpButton('attempts', 'additionalattempts', 'quiz_attemptstate');
        }

        if($this->attemptids) {
            foreach($this->attemptids as $key => $aid) {
                $mform->addElement('hidden', "attemptid[$key]", $aid);
                $mform->setType("attemptid[$key]", PARAM_INT);
            }
        }
        if ($mform->elementExists('userid')) {
            $mform->removeElement('userid');
        }
        if ($mform->elementExists('buttonbar')) { 
            $mform->removeElement('buttonbar');
        }
        if ($mform->elementExists('resetbutton')) { 
            $mform->removeElement('resetbutton');
        }
        
        $element = $mform->getElement('override');
        $element->setText(get_string($this->action.'attempt', 'quiz_attemptstate'));
        
        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'submitbutton',
                get_string('save', 'quiz'));
        $buttonarray[] = $mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonbar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonbar');        
        
    }
}
