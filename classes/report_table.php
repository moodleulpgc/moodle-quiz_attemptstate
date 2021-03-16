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
 * This file defines the quiz attemptstate table for showing last try at question
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_attemptstate;

use html_writer;
use moodle_url;
use quiz_attempt;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_table.php');

/**
 * This file defines the quiz attemptstate table for showing last try at question
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table extends \quiz_attempts_report_table {

    /** @var report_display_options Option */
    protected $options;

    /** @var array User details */
    protected $userdetails = [];
    
    /** @var bool can reopen closed attempts */
    protected $reopenenabled = 0;
    

    /** @var string Dash value for table cell */
    const DASH_VALUE = '-';

    public function __construct($quiz, $context, $qmsubselect, report_display_options $options,
            \core\dml\sql_join $groupstudentsjoins, \core\dml\sql_join $studentsjoins, $questions, $reporturl) {
        parent::__construct('mod-quiz-report-attemptstate-report', $quiz, $context,
                $qmsubselect, $options, $groupstudentsjoins, $studentsjoins, $questions, $reporturl);
        $this->options = $options;
        $this->reopenenabled = get_config('quiz_attemptstate', 'reopenenabled');
    }

    public function build_table() {
        if (!$this->rawdata) {
            return;
        }
        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        parent::build_table();
    }

    
    /**
     * Generate the display of the checkbox column.
     * @param object $row the table row being output.
     * @return string HTML content to go inside the td.
     */
    public function col_checkbox($row) {
        global $OUTPUT;

        $text = parent::col_checkbox($row);
        
        if(isset($row->attemptno)) {
            $text .= " {$row->attemptno}/{$row->numattempts}";
        }
        return $text;
    }    
    
    /**
     * Generate the display of the user's full name column.
     *
     * @param object $row the table row being output.
     * @return string HTML content to go inside the td.
     */
    public function col_fullname($row) {
        global $COURSE;

        $name = fullname($row);
        if ($this->download) {
            return $name;
        }

        $userid = $row->{$this->useridfield};
        if ($COURSE->id == SITEID) {
            $profileurl = new moodle_url('/user/profile.php', ['id' => $userid]);
        } else {
            $profileurl = new moodle_url('/user/view.php', ['id' => $userid, 'course' => $COURSE->id]);
        }

        return html_writer::link($profileurl, $name);
    }

    /**
    * Generate the display of the attempt state column.
    * @param object $attempt the table row being output.
    * @return string HTML content to go inside the td.
    */
    public function col_state($attempt) {
        if (!is_null($attempt->attempt)) {
            $state =  quiz_attempt::state_name($attempt->state);
            $state .= html_writer::empty_tag('br') . 
                        html_writer::link(new moodle_url('/mod/quiz/review.php', array('attempt' => $attempt->attempt)),
                                            get_string('reviewattempt', 'quiz'), array('class' => 'reviewlink'));            
            return $state;
        } else {
            return  '-';
        }
    }    
    
    /**
     * Display the exam code (OU-specific).
     *
     * @param \stdClass $row the table row being output.
     * @return string HTML content to go inside the td.
     */
    public function col_examcode(\stdClass $row) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/report/gradingstudents/examconfirmationcode.php');

        return \quiz_gradingstudents_report_exam_confirmation_code::get_confirmation_code(
                $this->options->cm->idnumber, $row->idnumber);
    }

    /**
     * Generate the display of the attempt sheet column.
     *
     * @param object $row The raw data for this row.
     * @return string The value for this cell of the table.
     */
    public function col_attempt_actions($row) {
        global $DB, $OUTPUT, $PAGE;
        
        $actions = [];
        $url = clone($this->reporturl);
        $url->param('attemptid[]', $row->attempt);
        
        $params = ['quiz' => $this->quiz->id, 'userid' =>$row->userid ,  'state' => 'inprogress' ];
        $hasinprogress = $DB->record_exists('quiz_attempts', $params);
    
        if(!$hasinprogress && $this->reopenenabled && ($row->attemptno == $row->numattempts)         && has_capability('quiz/attemptstate:reopen', $this->context)) {
            $url->param('extend', $row->attempt);
            $titlestr = get_string('extendattempt', 'quiz_attemptstate');
            $icon = $OUTPUT->pix_icon('i/reload', $titlestr, 'moodle', array('class'=>'iconsmall', 'title'=>$titlestr));
            $actions[] = html_writer::link($url, $icon.' '.$titlestr, ['class' => 'reviewlink']);
        }
    
        if((!$hasinprogress && has_capability('quiz/attemptstate:newattempt', $this->context)) && ($row->state != quiz_attempt::IN_PROGRESS)) {
            $url->param('new', $row->attempt);
            $url->remove_params('extend');
            $titlestr = get_string('newattempt', 'quiz_attemptstate');
            $icon = $OUTPUT->pix_icon('i/addblock', $titlestr, 'moodle', array('class'=>'iconsmall', 'title'=>$titlestr));
            $actions[] = html_writer::link($url, $icon.' '.$titlestr, ['class' => 'reviewlink']);        }
        
        if((has_capability('mod/quiz:regrade', $this->context)) && ($row->state == quiz_attempt::ABANDONED)) {
            $buttontext = get_string('closeattempt', 'quiz_attemptstate');
            $attributes = [
                    'class' => 'btn btn-secondary mr-1 close-attempt-btn',
                    'name' => 'close',
                    'id' => 'closeattemptbutton'.$row->attempt,
                    'value' => $row->attempt,
            ];
            $actions[] = html_writer::tag('button', $buttontext, $attributes);        
            $PAGE->requires->event_handler('#closeattemptbutton'.$row->attempt, 'click', 'M.util.show_confirm_dialog',
                    array('message' => get_string('closeattemptcheck', 'quiz_attemptstate', fullname($row) )));
            
        }
        
        if(!empty($actions)) {
            return html_writer::alist($actions, ['class' => 'actionlinks']);
        } else {
            return self::DASH_VALUE;
        }
    }

    /**
     * Generate the display of the create attempt column.
     *
     * @param object $row The raw data for this row.
     * @return string The value for this cell of the table.
     */
    public function col_attempt_answers($row): string {    
    
        $attemptobj = quiz_create_attempt_handling_errors($row->attempt, $this->options->cm->id);
        $slots = $attemptobj->get_slots();
        $states = [];
        $notanswered = 0;
        $invalid = 0;
        $lastslot = new \stdclass();
        $lastslot->time = 0;
        $lastslot->num = 0;
        foreach($slots as $slot) {
            $state = $attemptobj->get_question_state($slot)->get_state_class(false); 
            if(strpos($state, 'answered')) {
                $notanswered++;
            }
            if((strpos($state, 'unexpected') === 0) || (strpos($state, 'invalid') === 0)) {
                $invalid++;
            }
            $time = $attemptobj->get_question_action_time($slot) ;
            if($time > $lastslot->time) {
                $lastslot->time = $time;
                $lastslot->num = $slot;
            }
        }
        $output = '';
        if($notanswered) {
            $output .= get_string('question_notanswered', 'quiz_attemptstate', $notanswered);
        }
        if($invalid) {
            $output .= '<br />'.get_string('question_invalid', 'quiz_attemptstate', $invalid);
        }
        if($output && $lastslot->time) {
            $lastslot->time = userdate($lastslot->time, '%H:%M:%S');
            $output .= '<br />'.get_string('question_lastslot', 'quiz_attemptstate', $lastslot);
            return $output;
        } else  {
            return self::DASH_VALUE;
        }
    }
    
    protected function submit_buttons() {
        global $PAGE;
        
        if (has_capability('mod/quiz:regrade', $this->context)) {
            $closebuttonparams = [
                'type'  => 'submit',
                'class' => 'btn btn-secondary mr-1',
                'id'    => 'closeattemptsbutton',
                'name'  => 'close',
                'value' => get_string('closeselected', 'quiz_attemptstate'),
                'data-action' => 'toggle',
                'data-togglegroup' => $this->togglegroup,
                'data-toggle' => 'action',
                'disabled' => true
            ];
            echo html_writer::empty_tag('input', $closebuttonparams);
            $PAGE->requires->event_handler('#closeattemptsbutton', 'click', 'M.util.show_confirm_dialog',
                    array('message' => get_string('closeattemptscheck', 'quiz_attemptstate')));
            
        }
        
        if ($this->reopenenabled && has_capability('quiz/attemptstate:reopen', $this->context)) {
            $reopenbuttonparams = [
                'type'  => 'submit',
                'class' => 'btn btn-secondary mr-1  ',
                'id'    => 'reopenattemptsbutton',
                'name'  => 'extend',
                'value' => get_string('extendselected', 'quiz_attemptstate'),
                'data-action' => 'toggle',
                'data-togglegroup' => $this->togglegroup,
                'data-toggle' => 'action',
                'disabled' => true
            ];
            echo html_writer::empty_tag('input', $reopenbuttonparams);
            $PAGE->requires->event_handler('#reopenattemptsbutton', 'click', 'M.util.show_confirm_dialog',
                    array('message' => get_string('extendattemptcheck', 'quiz_attemptstate')));
        }        
        
        if (has_capability('quiz/attemptstate:newattempt', $this->context)) {
            $newattemptbuttonparams = [
                'type'  => 'submit',
                'class' => 'btn btn-secondary mr-1',
                'id'    => 'newattemptsbutton',
                'name'  => 'new',
                'value' => get_string('newattemptselected', 'quiz_attemptstate'),
                'data-action' => 'toggle',
                'data-togglegroup' => $this->togglegroup,
                'data-toggle' => 'action',
                'disabled' => true
            ];
            echo html_writer::empty_tag('input', $newattemptbuttonparams);
            $PAGE->requires->event_handler('#newattemptsbutton', 'click', 'M.util.show_confirm_dialog',
                    array('message' => get_string('newattemptcheck', 'quiz_attemptstate')));
        }        
        
        
        //parent::submit_buttons();
    }    
    
    /**
     * Add highlight class to last changed row
     *
     * @param \stdClass $attempt
     * @return string
     */
    public function get_row_class($attempt): string {
        $options = $this->options;
        $class = parent::get_row_class($attempt);
        if (!is_null($options->lastchanged)) {
            if ($options->lastchanged > 0 && $options->lastchanged == $attempt->attempt) {
                $class .= ' lastchanged';
            }
        }
        return $class;
    }

    /**
     * A chance for subclasses to modify the SQL after the count query has been generated,
     * and before the full query is constructed.
     *
     * @param string $fields SELECT list.
     * @param string $from JOINs part of the SQL.
     * @param string $where WHERE clauses.
     * @param array $params Query params.
     * @return array with 4 elements ($fields, $from, $where, $params) as from base_sql.
     */
    protected function update_sql_after_count($fields, $from, $where, $params) {
        [$fields, $from, $where, $params] = parent::update_sql_after_count($fields, $from, $where, $params);
        $fields .= ", quiza.attempt AS attemptno, (SELECT COUNT(qan.id) FROM {quiz_attempts} qan 
                                                    WHERE qan.quiz = quiza.quiz AND qan.userid = quiza.userid) as numattempts ";  
        return [$fields, $from, $where, $params];
    }

}
