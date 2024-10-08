<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin event observers are registered here.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\tag_added',
        'callback' => '\tool_enrolprofile\observer::tag_added',
    ],
    [
        'eventname' => '\core\event\tag_removed',
        'callback' => '\tool_enrolprofile\observer::tag_removed',
    ],
    [
        'eventname' => '\core\event\tag_deleted',
        'callback' => '\tool_enrolprofile\observer::tag_deleted',
    ],
    [
        'eventname' => '\core\event\tag_updated',
        'callback' => '\tool_enrolprofile\observer::tag_updated',
    ],
    [
        'eventname' => '\core\event\course_created',
        'callback' => '\tool_enrolprofile\observer::course_created',
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback' => '\tool_enrolprofile\observer::course_updated',
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\tool_enrolprofile\observer::course_deleted',
    ],
    [
        'eventname' => '\core\event\course_category_created',
        'callback' => '\tool_enrolprofile\observer::course_category_created',
    ],
    [
        'eventname' => '\core\event\course_category_updated',
        'callback' => '\tool_enrolprofile\observer::course_category_updated',
    ],
    [
        'eventname' => '\core\event\course_category_deleted',
        'callback' => '\tool_enrolprofile\observer::course_category_deleted',
    ],
    [
        'eventname' => '\tool_enrolprofile\event\preset_created',
        'callback' => '\tool_enrolprofile\observer::preset_created',
    ],
    [
        'eventname' => '\tool_enrolprofile\event\preset_deleted',
        'callback' => '\tool_enrolprofile\observer::preset_deleted',
    ],
    [
        'eventname' => '\tool_enrolprofile\event\preset_updated',
        'callback' => '\tool_enrolprofile\observer::preset_updated',
    ],
];
