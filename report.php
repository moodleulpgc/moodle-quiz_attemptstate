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
 * This file defines the Manage quiz attempts state class.
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use quiz_attemptstate\report_display_options;
use quiz_attemptstate\report_table;
use quiz_attemptstate\extend_attempt_form;

defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');

/**
 * This file defines the Manage quiz attempts state report class.
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_attemptstate_report extends quiz_attempts_report {

    public function display($quiz, $cm, $course) {
        global $DB, $PAGE;

        // Hack so we can get this in the form initialisation code.
        $quiz->cmobject = $cm;
        list($currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoins) =
                $this->init('attemptstate', '\quiz_attemptstate\report_settings_form', $quiz, $cm, $course);

        $options = new report_display_options('attemptstate', $quiz, $cm, $course);

        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);
        } else {
            $options->process_settings_from_params();
        }

        $this->form->set_data($options->get_initial_form_data());

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true, ['context' => context_course::instance($course->id)]);
        $table = new report_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudentsjoins, $studentsjoins, $questions, $options->get_url());
        $filename = quiz_report_download_filename(get_string('attemptstatefilename', 'quiz_attemptstate'),
                $courseshortname, $quiz->name);
        $table->is_downloading($options->download, $filename, $courseshortname . ' ' . format_string($quiz->name, true));
        if ($table->is_downloading()) {
            raise_memory_limit(MEMORY_EXTRA);
        }

        $this->hasgroupstudents = false;
        if (!empty($groupstudentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                      FROM {user} u
                           $groupstudentsjoins->joins
                     WHERE $groupstudentsjoins->wheres";
            $this->hasgroupstudents = $DB->record_exists_sql($sql, $groupstudentsjoins->params);
        }
        $hasstudents = false;
        if (!empty($studentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                      FROM {user} u
                           $studentsjoins->joins
                     WHERE $studentsjoins->wheres";
            $hasstudents = $DB->record_exists_sql($sql, $studentsjoins->params);
        }
        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all quiz attempts
            // are accessible, is not a security problem.
            $allowedjoins = new \core\dml\sql_join();
        }

        $this->course = $course; // Hack to make this available in process_actions.
        $this->process_actions($quiz, $cm, $currentgroup, $groupstudentsjoins, $allowedjoins, $options->get_url());

        $hasquestions = quiz_has_questions($quiz->id);

        // Start output.
        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data.
            $this->print_standard_header_and_messages($cm, $course, $quiz,
                    $options, $currentgroup, $hasquestions, $hasstudents);
        }

        $hasstudents = $hasstudents && (!$currentgroup || $this->hasgroupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {

            $table->setup_sql_queries($allowedjoins);

            // Define table columns.
            $columns = [];
            $headers = [];

            if (!$table->is_downloading() && $options->checkboxcolumn) {
                $columns[] = 'checkbox';
                if (method_exists($table, 'checkbox_col_header')) {
                    // Checkbox header only available since Moodle 3.8.
                    $headers[] = $table->checkbox_col_header('checkbox');
                } else {
                    $headers[] = null;
                }
            }

            $this->add_user_columns_from_options($table, $columns, $headers, $options);
            $this->add_state_column($columns, $headers);
            $this->add_time_columns($columns, $headers);
            
            $this->add_attempt_answers_column($table, $columns, $headers);
            $this->add_attempt_actions_column($table, $columns, $headers);

            $table->define_columns($columns);
            $table->define_headers($headers);
            $table->sortable(true, 'uniqueid');
            $table->no_sorting('checkbox');
            $table->no_sorting('attempt_actions');
            $table->no_sorting('attempt_answers');

            // Set up the table.
            $table->define_baseurl($options->get_url());
            $this->configure_user_columns($table);
            $table->set_attribute('id', 'attemptstate');
            $table->collapsible(true);

            if (!$table->is_downloading()) {
                $this->form->display();
            }
            $table->out($options->pagesize, true);

        }

        return true;
    }

    /**
     * Initialise some parts of $PAGE and start output.
     *
     * @param object $cm the course_module information.
     * @param object $course the course object.
     * @param object $quiz the quiz settings.
     * @param string $reportmode the report name.
     */
    public function print_header_and_tabs($cm, $course, $quiz, $reportmode = 'overview') {
        parent::print_header_and_tabs($cm, $course, $quiz, $reportmode);
        $instruction = get_config('quiz_attemptstate', 'instruction_message');
        if (trim(html_to_text($instruction)) !== '') {
            echo html_writer::div($instruction, 'instruction');
        }
    }

    /**
     * Add user columns, taking note of our option for which ones to show.
     *
     * @param report_table $table the table we are building.
     * @param array $columns the columns array to update.
     * @param array $headers the column headers array to update.
     * @param report_display_options $options report display options.
     */
    protected function add_user_columns_from_options(report_table $table,
            array &$columns, array &$headers, report_display_options $options): void {
        global $CFG;

        if (!$table->is_downloading() && $CFG->grade_report_showuserimage) {
            $columns[] = 'picture';
            $headers[] = '';
        }

        if ($options->userinfovisibility['fullname']) {
            if (!$table->is_downloading()) {
                $columns[] = 'fullname';
                $headers[] = get_string('name');
            } else {
                $columns[] = 'lastname';
                $headers[] = get_string('lastname');
                $columns[] = 'firstname';
                $headers[] = get_string('firstname');
            }
        }

        foreach ($options->userinfovisibility as $field => $show) {
            if ($field === 'fullname') {
                continue;
            }
            if (!$show) {
                continue;
            }
            $columns[] = $field;
            $headers[] = report_display_options::user_info_visibility_settings_name($field);
            if ($field === 'examcode') {
                $table->no_sorting('examcode');
            }
        }
    }

    /**
     * Add attempt actions column to the $columns and $headers arrays.
     *
     * @param table_sql $table the table being constructed.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     */
    protected function add_attempt_answers_column(table_sql $table, array &$columns, array &$headers) {
        $capabilities = ['quiz/attemptstate:view', 'quiz/attemptstate:reopen', 'quiz/attemptstate:newattempt'];
        if (!$table->is_downloading() && has_any_capability($capabilities, $this->context)) {
            $columns[] = 'attempt_answers';
            $headers[] = get_string('column_attempt_answers', 'quiz_attemptstate');
        }
    }    
    
    /**
     * Add attempt actions column to the $columns and $headers arrays.
     *
     * @param table_sql $table the table being constructed.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     */
    protected function add_attempt_actions_column(table_sql $table, array &$columns, array &$headers) {
        $capabilities = ['quiz/attemptstate:view', 'quiz/attemptstate:reopen', 'quiz/attemptstate:newattempt'];
        if (!$table->is_downloading() && has_any_capability($capabilities, $this->context)) {
            $columns[] = 'attempt_actions';
            $headers[] = get_string('column_attempt_actions', 'quiz_attemptstate');
        }
    }

    /**
     * Extends parent function processing any submitted actions.
     *
     * @param object $quiz
     * @param object $cm
     * @param int $currentgroup
     * @param \core\dml\sql_join $groupstudentsjoins (joins, wheres, params)
     * @param \core\dml\sql_join $allowedjoins (joins, wheres, params)
     * @param moodle_url $redirecturl
     */
    protected function process_actions($quiz, $cm, $currentgroup, \core\dml\sql_join $groupstudentsjoins,
            \core\dml\sql_join $allowedjoins, $redirecturl) {

        if(optional_param('cancel', '', PARAM_ALPHANUMEXT)) {
            return;
        }
        $quiz = quiz_access_manager::load_quiz_and_settings($quiz->id); 

        parent::process_actions($quiz, $cm, $currentgroup, $groupstudentsjoins, $allowedjoins, $redirecturl);
        $this->cm = $cm;
        
        $action = '';
        if($extend = optional_param('extend', '', PARAM_ALPHANUMEXT)) {
            $action = 'extend';
        }
        if($newattempt = optional_param('new', '', PARAM_ALPHANUMEXT)) {
            $action = 'new';        
        }
        
        $display = optional_param('display', 1, PARAM_BOOL);
        $attemptids = optional_param_array('attemptid', array(), PARAM_INT);
        
        if($action && $display) {
            $this->display_extend_form($quiz, $cm, $redirecturl, $action, $attemptids);
        }
        if (empty($currentgroup) || $this->hasgroupstudents) {
            if (($close = optional_param('close', 0, PARAM_ALPHANUMEXT)) && confirm_sesskey()) {
                if((int)$close > 0) {
                    $attemptids = [$close];
                }
                if($attemptids) {
                    $this->close_attempts($quiz, $groupstudentsjoins, $attemptids);
                }
            }
            if(($action == 'new') && confirm_sesskey()) {
                if($attemptids) {
                    $this->new_attempts($quiz, $groupstudentsjoins, $attemptids);
                }
            }
            if(($action == 'extend') && confirm_sesskey()) {
                if($attemptids) {
                    $this->extend_attempts($quiz, $groupstudentsjoins, $attemptids);
                }
            }
            
        }
    }

    
    /**
     * Displays am override form for extendinf time for attempt
     *
     * @param object $quiz
     * @param object $cm
     * @param moodle_url $url
     * @param string $action
     */
    protected function display_extend_form($quiz, $cm, $url, $action, $attemptids) {
        global $PAGE, $OUTPUT; 

        $data = new stdClass();
        // Merge quiz defaults with data.
        $keys = array('timeopen', 'timeclose', 'timelimit', 'attempts', 'password');
        foreach ($keys as $key) {
            if (!isset($data->{$key}) || $reset) {
                $data->{$key} = $quiz->{$key};
            }
        }
        
        // Setup the form.
        $mform = new extend_attempt_form($url, $cm, $quiz, $this->context, $attemptids, $action);
        $mform->set_data($data);

        // Print the form.
        $pagetitle = get_string('editoverride', 'quiz_attemptstate');
        $PAGE->navbar->add($pagetitle);
        $PAGE->set_pagelayout('admin');
        $PAGE->set_title($pagetitle);
        $PAGE->set_heading($this->course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($quiz->name, true, array('context' => $this->context)));

        $mform->display();

        echo $OUTPUT->footer();        
        die;    
    }
    
    /**
     * Unlock the session and allow the regrading process to run in the background.
     */
    protected function unlock_session() {
        \core\session\manager::write_close();
        ignore_user_abort(true);
    }    
    
    /**
     * Collect selected attempts for this quiz, exactly which attempts are regraded is
     * controlled by the parameters.
     * @param object $quiz the quiz settings.
     * @param string $statecondition the quiz attempt state sql condition
     * @param \core\dml\sql_join|array $groupstudentsjoins empty for all attempts, otherwise regrade attempts
     * for these users.
     * @param array $attemptids blank for all attempts, otherwise only regrade
     * attempts whose id is in this list.
     * @return array of quiz attempts records
     */
    protected function get_selected_attempts($quiz, $statecondition, 
            \core\dml\sql_join $groupstudentsjoins = null, $attemptids = array()) {
        global $DB;
    
        $sql = "SELECT quiza.*, 
                    (SELECT COUNT(qan.id) FROM {quiz_attempts} qan 
                    WHERE qan.quiz = quiza.quiz AND qan.userid = quiza.userid) as numattempts, 
                        " . get_all_user_name_fields(true, 'u') . "
                  FROM {quiz_attempts} quiza
                  JOIN {user} u ON u.id = quiza.userid";
        $where = "quiz = :qid AND preview = 0 AND $statecondition";
        $params = array('qid' => $quiz->id);

        if ($this->hasgroupstudents && !empty($groupstudentsjoins->joins)) {
            $sql .= "\n{$groupstudentsjoins->joins}";
            $where .= " AND {$groupstudentsjoins->wheres}";
            $params += $groupstudentsjoins->params;
        }

        if ($attemptids) {
            list($attemptidcondition, $attemptidparams) = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
            $where .= " AND quiza.id $attemptidcondition";
            $params += $attemptidparams;
        }

        $sql .= "\nWHERE {$where}";
        
        return  $DB->get_records_sql($sql, $params);
    }

    
    /**
     * Finalize attempts for this quiz, exactly which attempts are finalized is
     * controlled by the parameters.
     * @param object $quiz the quiz settings.
     * @param \core\dml\sql_join|array $groupstudentsjoins empty for all attempts, otherwise regrade attempts
     * for these users.
     * @param array $attemptids blank for all attempts, otherwise only regrade
     * attempts whose id is in this list.
     */
    protected function close_attempts($quiz, \core\dml\sql_join $groupstudentsjoins = null, $attemptids = array()) {
        global $DB;
        $this->unlock_session();
        
        $condition = "quiza.state = '".quiz_attempt::ABANDONED."'";
        $num = 0;
        if($attempts = $this->get_selected_attempts($quiz, $condition, $groupstudentsjoins, $attemptids)) {
            foreach($attempts as $attempt) {
                $attemptobj = new quiz_attempt($attempt, $quiz, $this->cm, $this->course);
                $attemptobj->process_finish($attempt->timemodified, false);
                self::create_event('attempt_closed', $attempt->id, $attempt->userid, $quiz->course, $this->context, $quiz->id);
                $num++;
                gc_collect_cycles();
            }
        }
        \core\notification::add(get_string('attemptsclosed', 'quiz_attemptstate', $num), \core\output\notification::NOTIFY_SUCCESS);
    }    

    /**
     * Get or create override for the attempt user
     * @param object $attempt quiz_attempt record
     * @param object $data stardard override data from inpot form
     * attempts whose id is in this list.
     * @return object override object
     */
    protected function get_attempt_override($attempt, $quiz) {
        global $DB;
    
        $params = ['quiz' => $attempt->quiz, 'userid' =>$attempt->userid];
        if($overrides = $DB->get_records('quiz_overrides', $params, 'timeclose DESC', '*', 0, 1)) {
            $override = reset($overrides);
        } else {
            $override = new \stdClass();
            $override->quiz = $attempt->quiz;
            $override->userid = $attempt->userid;
        }

        $mform = new extend_attempt_form($this->get_base_url(), $this->cm, $quiz, $this->context, [], 'extend');
        if($fromform = $mform->get_data()) {
            
            $fromform->attempts = $attempt->numattempts + $fromform->attempts;
            $keys = array('timeopen', 'timeclose', 'timelimit', 'attempts', 'password');
            foreach($keys as $key) {
                if(isset($fromform->{$key}) && (!isset($override->{$key}) || ($fromform->{$key} > $override->{$key}))) {
                    $override->{$key} = $fromform->{$key};
                }
            }
        }    
        
        return $override;
    }
    
    /**
     * Reopen and Extend attempts for this quiz, exactly which attempts are reopened is
     * controlled by the parameters.
     * @param object $quiz the quiz settings.
     * @param \core\dml\sql_join|array $groupstudentsjoins empty for all attempts, otherwise regrade attempts
     * for these users.
     * @param array $attemptids blank for all attempts, otherwise only regrade
     * attempts whose id is in this list.
     */
    protected function extend_attempts($quiz, \core\dml\sql_join $groupstudentsjoins = null, $attemptids = array()) {
        global $DB;
        $this->unlock_session();
        
        $condition = "quiza.state <> '".quiz_attempt::IN_PROGRESS."'";
        if($attempts = $this->get_selected_attempts($quiz, $condition, $groupstudentsjoins, $attemptids)) {
            $num = 0;
            foreach($attempts as $attempt) {
                $extend = new \stdClass();
                $extend->id = $attempt->id;
                $extend->state = quiz_attempt::IN_PROGRESS;
                $extend->timefinish = 0;
                
                $override = $this->get_attempt_override($attempt, $quiz);
                $params = ['quiz' => $attempt->quiz, 'userid' =>$attempt->userid, 'state' => quiz_attempt::IN_PROGRESS];
                ;
                if(($attempt->attempt < $attempt->numattempts) || 
                    $hasinprogress = $DB->record_exists('quiz_attempts', $params)) {
                    // do not extend if not very last attempt or exists other attempt in progress for this user
                    continue;
                }
                $transaction = $DB->start_delegated_transaction();
                if(isset($override->id)) {
                    $DB->update_record('quiz_overrides', $override);
                } else {
                    $override->id = $DB->insert_record('quiz_overrides', $override);
                }

                $DB->update_record('quiz_attempts', $extend);
                $transaction->allow_commit();
                $transaction = null;
                self::create_event('attempt_extended', $attempt->id, $attempt->userid, $quiz->course, $this->context, $quiz->id);
                $num++;
                gc_collect_cycles();
            }
            \core\notification::add(get_string('attemptsextended', 'quiz_attemptstate', $num), \core\output\notification::NOTIFY_SUCCESS);
        }
    }    
    
    
    /**
     * Created new attemper based on last and Extend attempt for this quiz, exactly which attempts are newed is
     * controlled by the parameters.
     * @param object $quiz the quiz settings.
     * @param \core\dml\sql_join|array $groupstudentsjoins empty for all attempts, otherwise regrade attempts
     * for these users.
     * @param array $attemptids blank for all attempts, otherwise only regrade
     * attempts whose id is in this list.
     */
    protected function new_attempts($quiz, \core\dml\sql_join $groupstudentsjoins = null, $attemptids = array()) {
        global $DB;
        $this->unlock_session();
        
        $condition = "quiza.state <> '".quiz_attempt::IN_PROGRESS."'";
        if($attempts = $this->get_selected_attempts($quiz, $condition, $groupstudentsjoins, $attemptids)) {
            $num = 0;
            foreach($attempts as $attempt) {
                $override = $this->get_attempt_override($attempt, $quiz);
                $params = ['quiz' => $attempt->quiz, 'userid' =>$attempt->userid, 'state' => quiz_attempt::IN_PROGRESS];
                if($hasinprogress = $DB->record_exists('quiz_attempts', $params)) {
                    // do not extend if exists other attempt in progress for this user
                    continue;
                }
                
                $quiz->attemptonlast = 1;
                $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $this->context);
                $quba->set_preferred_behaviour($quiz->preferredbehaviour);
                // Create the new attempt and initialize the question sessions
                $timenow = time(); // Update time now, in case the server is running really slowly.
                $quizobj = quiz::create($attempt->quiz, $attempt->userid);
                $newattempt = quiz_create_attempt($quizobj, $attempt->numattempts+1, $attempt, $timenow, false, $attempt->userid);
                $newattempt = quiz_start_attempt_built_on_last($quba, $newattempt, $attempt);
                
                $transaction = $DB->start_delegated_transaction();
                
                if(isset($override->id)) {
                    $DB->update_record('quiz_overrides', $override);
                } else {
                    $override->id = $DB->insert_record('quiz_overrides', $override);
                }
                // Init the timemodifiedoffline for offline attempts.
                if ($quiz->allowofflineattempts && $attempt->timemodifiedoffline) {
                    $attempt->timemodifiedoffline = $attempt->timemodified;
                }
                $newattempt = quiz_attempt_save_started($quizobj, $quba, $newattempt);
                
                $transaction->allow_commit();
                $transaction = null;
                $num++;
                self::create_event('attempt_newed', $attempt->id, $attempt->userid, $quiz->course, $this->context, $quiz->id);
                \core\notification::add(get_string('attemptsnewed', 'quiz_attemptstate', $num), \core\output\notification::NOTIFY_SUCCESS);
                gc_collect_cycles();
            }
        }
    }    

    /**
     * Check if can create attempt
     *
     * @param \quiz $quizobj Quiz object
     * @param array $attempts Array of attempts
     * @return bool
     */
    public static function can_create_attempt($quizobj, $attempts): bool {
        // Check if quiz is unlimited.
        if (!$quizobj->get_quiz()->attempts) {
            return true;
        }
        $numprevattempts = count($attempts);
        if ($numprevattempts == 0) {
            return true;
        }
        $lastattempt = end($attempts);
        $state = $lastattempt->state;
        if ($state && $state == quiz_attempt::FINISHED) {
            // Check max attempts.
            $rule = new \quizaccess_numattempts($quizobj, time());
            if (!$rule->prevent_new_attempt($numprevattempts, $lastattempt)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Prepare event data.
     *
     * @param int $attemptid Attempt id
     * @param int $userid User id
     * @param int $courseid Course id
     * @param context_module $context Module context
     * @param int $quizid Quiz id
     * @return array Event data
     */
    private static function prepare_event_data(int $attemptid, int $userid, int $courseid, context_module $context,
            int $quizid): array {
        $params = [
                'relateduserid' => $userid,
                'courseid' => $courseid,
                'context' => $context,
                'other' => [
                        'quizid' => $quizid,
                        'attemptid' => $attemptid
                ]
        ];

        return $params;
    }

    /**
     * Fire events.
     *
     * @param string $eventtype Event type name
     * @param int $attemptid Attempt id
     * @param int $userid User id
     * @param int $courseid Course id
     * @param context_module $context Module context
     * @param int $quizid Quiz id
     */
    public static function create_event(string $eventtype, int $attemptid, int $userid, int $courseid, context_module $context,
            int $quizid): void {
        $params = self::prepare_event_data($attemptid, $userid, $courseid, $context, $quizid);
        $classname = '\quiz_attemptstate\event\\' . $eventtype;
        $event = $classname::create($params);
        $event->trigger();
    }    
    
    
}
