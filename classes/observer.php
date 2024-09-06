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

namespace tool_enrolprofile;

use core\event\course_category_created;
use core\event\course_created;
use core\event\tag_added;
use core\event\tag_removed;
use core\event\tag_deleted;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\rule_manager;

/**
 * Event observer class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.comt>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Process tag_added event.
     *
     * @param tag_added $event The event.
     */
    public static function tag_added(tag_added $event): void {
        // Check context is course context.
        $context = $event->get_context();
        if ($context->contextlevel != CONTEXT_COURSE && $event->other['itemtype'] != 'course') {
            return;
        }

        $tagid = $event->other['tagid'];
        $tagname = $event->other['tagrawname'];
        $course = get_course($event->other['itemid']);

        helper::add_item($tagid, helper::ITEM_TYPE_TAG, $tagname, $course);
    }

    /**
     * Process tag_removed event.
     *
     * @param tag_removed $event The event.
     */
    public static function tag_removed(tag_removed $event): void {
        // Check context is course context.
        $context = $event->get_context();
        if ($context->contextlevel != CONTEXT_COURSE && $event->other['itemtype'] != 'course') {
            return;
        }

        $tagid = $event->other['tagid'];
        $course = get_course($event->other['itemid']);
        helper::remove_enrolment_method($tagid, helper::ITEM_TYPE_TAG, $course->id);
    }

    /**
     * Process tag_deleted event.
     *
     * @param tag_deleted $event The event.
     */
    public static function tag_deleted(tag_deleted $event): void {
        $tagid = $event->objectid;
        $tagname = $event->other['rawname'];
        helper::remove_item($tagid, helper::ITEM_TYPE_TAG, $tagname);
    }

    /**
     * Process course_created event.
     *
     * @param course_created $event The event.
     */
    public static function course_created(course_created $event): void {
        global $DB;

        $course = get_course($event->courseid);
        helper::add_item($course->id, helper::ITEM_TYPE_COURSE, $course->{helper::COURSE_NAME}, $course);

        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        helper::add_item($category->id, helper::ITEM_TYPE_CATEGORY, $category->name, $course);
    }

    /**
     * Process course_category_created event.
     *
     * @param course_category_created $event The event.
     */
    public static function course_category_created(course_category_created $event): void {
        global $DB;

        $category = $DB->get_record('course_categories', ['id' => $event->objectid]);
        helper::add_item($category->id, helper::ITEM_TYPE_CATEGORY, $category->name);
    }
}
