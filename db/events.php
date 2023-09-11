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
 * Define event observers.
 *
 * @package   mod_pulse
 * @copyright 2021, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Need to define list of events that plugin will go to observe.
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    array(
        'eventname' => 'core\event\course_module_deleted',
        'callback' => '\mod_pulse\eventobserver::course_module_deleted',
    ),

    array(
        'eventname' => 'core\event\user_enrolment_deleted',
        'callback' => '\mod_pulse\eventobserver::user_enrolment_deleted',
    ),

    /**
     * To create a automation instance schedule for new user.
     */
    array(
        'eventname' => 'core\event\user_enrolment_created',
        'callback' => '\mod_pulse\eventobserver::user_enrolment_created',
    ),

];
