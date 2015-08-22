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
 * Activity overview table
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   mod_webcast
 * @copyright 2015 MoodleFreak.com
 * @author    Luuk Verhoeven
 **/
namespace mod_webcast\table;

defined('MOODLE_INTERNAL') || die();

/**
 * Simple subclass of {@link table_sql} that provides
 * some custom formatters for various columns, in order
 * to make the main outstations list nicer
 */
class useractivity extends \table_sql {

    /**
     * Webcast object
     *
     * @var bool|object
     */
    public $webcast = false;

    /**
     * Build the table and sql parts
     *
     * @param string $uniqueid
     * @param $webcast
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    function __construct($uniqueid, $webcast) {

        global $DB;
        parent::__construct($uniqueid);

        // Set the webcast
        $this->webcast = $webcast;

        // Get instance active in the course
        list($this->instancessql, $params) = $DB->get_in_or_equal(array_keys(enrol_get_instances($webcast->course, false)), SQL_PARAMS_NAMED);

        // Get extra fields
        $extrafields = get_extra_user_fields(\context_course::instance($webcast->course));
        $extrafields[] = 'lastaccess';
        $dbfields = \user_picture::fields('u', $extrafields);

        // Params
        $now = round(time(), -2); // rounding helps caching in DB
        $params += array(
            'enabled' => ENROL_INSTANCE_ENABLED,
            'active' => ENROL_USER_ACTIVE,
            'now1' => $now,
            'now2' => $now,
            'webcastid' => $webcast->id
        );

        $this->sql = new \stdClass();
        $this->sql->fields = 'DISTINCT ' . $dbfields . ', status.timer_seconds, status.starttime, status.endtime';
        $this->sql->from = '{user} u
                      JOIN {user_enrolments} ue ON (ue.userid = u.id  AND ue.enrolid ' . $this->instancessql . ')
                      JOIN {enrol} e ON (e.id = ue.enrolid)
                 LEFT JOIN {webcast_userstatus} status ON (status.userid = u.id  AND status.webcast_id = :webcastid)
                 LEFT JOIN {groups_members} gm ON u.id = gm.userid';

        $this->sql->where = 'ue.status = :active AND e.status = :enabled AND ue.timestart < :now1
                    AND (ue.timeend = 0 OR ue.timeend > :now2)';

        $this->sql->params = $params;

        // Set count sql
        $this->countsql = 'SELECT COUNT(*) FROM ' . $this->sql->from . ' WHERE ' . $this->sql->where;
        $this->countparams = $params;
    }

    /**
     * Render actions
     *
     * @param $row
     *
     * @return string
     */
    protected function col_action($row) {

        global $PAGE;

        if(empty($row->starttime)){
            return '';
        }

        $chattime = new \moodle_url('/mod/webcast/user_activity.php', array(
            'user_id' => $row->id,
            'id' => $PAGE->cm->id,
            'action' => 'user_chattime',
        ));

        $chatlog = new \moodle_url('/mod/webcast/user_activity.php', array(
            'user_id' => $row->id,
            'id' => $PAGE->cm->id,
            'action' => 'user_chatlog',
        ));

        return \html_writer::link($chattime, get_string('btn:chattime', 'webcast'), array(
            'class' => 'btn',
        )) . ' ' . \html_writer::link($chatlog, get_string('btn:chatlog', 'webcast'), array(
            'class' => 'btn',
        ));
    }

    /**
     * Render user picture
     *
     * @param $row
     *
     * @return string
     */
    protected function col_picture($row) {
        global $OUTPUT;

        return $OUTPUT->user_picture($row, array('link' => true));
    }

    /**
     * Render field
     *
     * @param $row
     *
     * @return string
     */
    protected function col_present($row) {
        return (!empty($row->starttime)) ? get_string('yes', 'webcast') : get_string('no', 'webcast');
    }
}