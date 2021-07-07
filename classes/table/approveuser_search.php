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
 * Class used to fetch participants based on a filterset.
 *
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pulse\table;

use context;
use context_helper;
use core_table\local\filter\filterset;
use core_user;
use moodle_recordset;
use stdClass;
use user_picture;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/user/lib.php');

/**
 * Class used to fetch participants based on a filterset.
 *
 * @package    core_user
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class approveuser_search extends \core_user\table\participants_search {

    /**
     * @var filterset $filterset The filterset describing which participants to include in the search.
     */
    protected $filterset;

    /**
     * @var stdClass $course The course being searched.
     */
    protected $course;

    /**
     * @var context_course $context The course context being searched.
     */
    protected $context;

    /**
     * @var string[] $userfields Names of any extra user fields to be shown when listing users.
     */
    protected $userfields;

    /**
     * Class constructor.
     *
     * @param stdClass $course The course being searched.
     * @param context $context The context of the search.
     * @param filterset $filterset The filterset used to filter the participants in a course.
     */
    public function __construct(stdClass $course, context $context, filterset $filterset) {
        global $PAGE, $DB;
        parent::__construct($course, $context, $filterset);
        $this->pulse = $DB->get_record('pulse', ['id' => $PAGE->cm->instance]);
        $this->cm = $PAGE->cm;
    }

    /**
     * Prepare SQL and associated parameters for users enrolled in the course.
     *
     * @return array SQL query data in the format ['sql' => '', 'forcedsql' => '', 'params' => []].
     */
    protected function get_enrolled_sql(): array {
        global $USER;

        $isfrontpage = ($this->context->instanceid == SITEID);
        $prefix = 'eu_';
        $filteruid = "{$prefix}u.id";
        $sql = '';
        $joins = [];
        $wheres = [];
        $params = [];
        // It is possible some statements must always be included (in addition to any filtering).
        $forcedprefix = "f{$prefix}";
        $forceduid = "{$forcedprefix}u.id";
        $forcedsql = '';
        $forcedjoins = [];
        $forcedwhere = "{$forcedprefix}u.deleted = 0";

        if (!$isfrontpage) {
            // Prepare any enrolment method filtering.
            [
                'joins' => $methodjoins,
                'where' => $wheres[],
                'params' => $methodparams,
            ] = $this->get_enrol_method_sql($filteruid);

            // Prepare any status filtering.
            [
                'joins' => $statusjoins,
                'where' => $statuswhere,
                'params' => $statusparams,
                'forcestatus' => $forcestatus,
            ] = $this->get_status_sql($filteruid, $forceduid, $forcedprefix);

            if ($forcestatus) {
                // Force filtering by active participants if user does not have capability to view suspended.
                $forcedjoins = array_merge($forcedjoins, $statusjoins);
                $statusjoins = [];
                $forcedwhere .= " AND ({$statuswhere})";
            } else {
                $wheres[] = $statuswhere;
            }

            $joins = array_merge($joins, $methodjoins, $statusjoins);
            $params = array_merge($params, $methodparams, $statusparams);
        }

        $groupids = [];

        if ($this->filterset->has_filter('groups')) {
            $groupids = $this->filterset->get_filter('groups')->get_filter_values();
        }

        // Force additional groups filtering if required due to lack of capabilities.
        // Note: This means results will always be limited to allowed groups, even if the user applies their own groups filtering.

        $canaccessallgroups = (has_capability('moodle/site:accessallgroups', $this->context)
                                    || pulse_isusercontext($this->pulse, $this->cm->id));
        $forcegroups = ($this->course->groupmode == SEPARATEGROUPS && !$canaccessallgroups);

        if ($forcegroups) {
            $allowedgroupids = array_keys(groups_get_all_groups($this->course->id, $USER->id));

            // Users not in any group in a course with separate groups mode should not be able to access the participants filter.
            if (empty($allowedgroupids)) {
                // The UI does not support this, so it should not be reachable unless someone is trying to bypass the restriction.
                throw new \coding_exception('User must be part of a group to filter by participants.');
            }

            $forceduid = "{$forcedprefix}u.id";
            $forcedjointype = $this->get_groups_jointype(\core_table\local\filter\filter::JOINTYPE_ANY);
            $forcedgroupjoin = groups_get_members_join($allowedgroupids, $forceduid, $this->context, $forcedjointype);

            $forcedjoins[] = $forcedgroupjoin->joins;
            $forcedwhere .= " AND ({$forcedgroupjoin->wheres})";

            $params = array_merge($params, $forcedgroupjoin->params);

            // Remove any filtered groups the user does not have access to.
            $groupids = array_intersect($allowedgroupids, $groupids);
        }

        // Prepare any user defined groups filtering.
        if ($groupids) {
            $groupjoin = groups_get_members_join($groupids, $filteruid, $this->context, $this->get_groups_jointype());

            $joins[] = $groupjoin->joins;
            $params = array_merge($params, $groupjoin->params);
            if (!empty($groupjoin->wheres)) {
                $wheres[] = $groupjoin->wheres;
            }
        }

        // Combine the relevant filters and prepare the query.
        $joins = array_filter($joins);
        if (!empty($joins)) {
            $joinsql = implode("\n", $joins);

            $sql = "SELECT DISTINCT {$prefix}u.id
                               FROM {user} {$prefix}u
                                    {$joinsql}
                              WHERE {$prefix}u.deleted = 0";
        }

        $wheres = array_filter($wheres);
        if (!empty($wheres)) {
            if ($this->filterset->get_join_type() === $this->filterset::JOINTYPE_ALL) {
                $wheresql = '(' . implode(') AND (', $wheres) . ')';
            } else {
                $wheresql = '(' . implode(') OR (', $wheres) . ')';
            }

            $sql .= " AND ({$wheresql})";
        }

        // Prepare any SQL that must be applied.
        if (!empty($forcedjoins)) {
            $forcedjoinsql = implode("\n", $forcedjoins);
            $forcedsql = "SELECT DISTINCT {$forcedprefix}u.id
                                     FROM {user} {$forcedprefix}u
                                          {$forcedjoinsql}
                                    WHERE {$forcedwhere}";
        }

        return [
            'sql' => $sql,
            'forcedsql' => $forcedsql,
            'params' => $params,
        ];
    }


}
