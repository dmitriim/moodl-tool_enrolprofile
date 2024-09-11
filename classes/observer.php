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

use core\context\course;
use core\event\course_category_created;
use core\event\course_category_deleted;
use core\event\course_category_updated;
use core\event\course_created;
use core\event\course_deleted;
use core\event\course_updated;
use core\event\tag_added;
use core\event\tag_removed;
use core\event\tag_deleted;
use core\event\tag_updated;

/**
 * Event observer class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
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
     * Process tag_updated event.
     *
     * @param tag_updated $event The event.
     */
    public static function tag_updated(tag_updated $event): void {
        $tagid = $event->objectid;
        $tagnewname = $event->other['rawname'];
        helper::rename_item($tagid, helper::ITEM_TYPE_TAG, $tagnewname);
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
     * Process course_updated event.
     *
     * @param course_updated $event The event.
     */
    public static function course_updated(course_updated $event): void {
        if (key_exists(helper::COURSE_NAME, $event->other['updatedfields'])) {
            $newcoursename = $event->other['updatedfields'][helper::COURSE_NAME];
            helper::rename_item($event->courseid, helper::ITEM_TYPE_COURSE, $newcoursename);
        }

        if (key_exists('category', $event->other['updatedfields'])) {
            helper::update_course_category($event->courseid, $event->other['updatedfields']['category']);
        }
    }

    /**
     * Process course_deleted event.
     *
     * @param course_deleted $event The event.
     */
    public static function course_deleted(course_deleted $event): void {
        $courseid = $event->courseid;
        $coursename = $event->other['fullname'];
        helper::remove_item($courseid, helper::ITEM_TYPE_COURSE, $coursename);
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

    /**
     * Process course_category_updated event.
     *
     * @param course_category_updated $event The event.
     */
    public static function course_category_updated(course_category_updated $event): void {
        global $DB;

        $category = $DB->get_record('course_categories', ['id' => $event->objectid]);
        helper::rename_item($category->id, helper::ITEM_TYPE_CATEGORY, $category->name);
    }

    /**
     * Process course_category_deleted event.
     *
     * @param course_category_deleted $event The event.
     */
    public static function course_category_deleted(course_category_deleted $event): void {

        $categoryid = $event->objectid;
        $categoryname = $event->other['name'];

        helper::remove_item($categoryid, helper::ITEM_TYPE_CATEGORY, $categoryname);
    }
}
