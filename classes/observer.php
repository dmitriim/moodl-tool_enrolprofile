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
use core\event\course_category_deleted;
use core\event\course_category_updated;
use core\event\course_created;
use core\event\course_deleted;
use core\event\course_updated;
use core\event\tag_added;
use core\event\tag_removed;
use core\event\tag_deleted;
use core\event\tag_updated;
use core\task\manager;
use tool_enrolprofile\event\preset_created;
use tool_enrolprofile\event\preset_deleted;
use tool_enrolprofile\event\preset_updated;

/**
 * Event observer class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * A helper method to queue adhoc task.
     *
     * @param string $taskname Task name.
     * @param array $customdata Task custom data.
     */
    private static function queue_adhoc_task(string $taskname, array $customdata): void {
        $taskclass = '\\tool_enrolprofile\\task\\' . $taskname;
        $task = new $taskclass();
        $task->set_custom_data($customdata);
        manager::queue_adhoc_task($task, true);
    }

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

        self::queue_adhoc_task('add_item', [
            'itemid' => $event->other['tagid'],
            'itemtype' => helper::ITEM_TYPE_TAG,
            'itemname' => $event->other['tagrawname'],
            'courseids' => [$event->other['itemid']],
        ]);
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

        self::queue_adhoc_task('remove_enrolment_method', [
            'itemid' => $event->other['tagid'],
            'itemtype' => helper::ITEM_TYPE_TAG,
            'courseid' => $event->other['itemid'],
        ]);
    }

    /**
     * Process tag_deleted event.
     *
     * @param tag_deleted $event The event.
     */
    public static function tag_deleted(tag_deleted $event): void {
        $tagid = $event->objectid;
        $tagname = $event->other['rawname'];

        self::queue_adhoc_task('remove_item', [
            'itemid' => $tagid,
            'itemtype' => helper::ITEM_TYPE_TAG,
            'itemname' => $tagname,
        ]);
    }

    /**
     * Process tag_updated event.
     *
     * @param tag_updated $event The event.
     */
    public static function tag_updated(tag_updated $event): void {
        $tagid = $event->objectid;
        $tagnewname = $event->other['rawname'];
        self::queue_adhoc_task('rename_item', [
            'itemid' => $tagid,
            'itemtype' => helper::ITEM_TYPE_TAG,
            'itemname' => $tagnewname,
        ]);
    }

    /**
     * Process course_created event.
     *
     * @param course_created $event The event.
     */
    public static function course_created(course_created $event): void {
        global $DB;

        $course = get_course($event->courseid);
        self::queue_adhoc_task('add_item', [
            'itemid' => $course->id,
            'itemtype' => helper::ITEM_TYPE_COURSE,
            'itemname' => $course->{helper::COURSE_NAME},
            'courseids' => [$course->id],
        ]);

        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        self::queue_adhoc_task('add_item', [
            'itemid' => $category->id,
            'itemtype' => helper::ITEM_TYPE_CATEGORY,
            'itemname' => $category->name,
            'courseids' => [$course->id],
        ]);
    }

    /**
     * Process course_updated event.
     *
     * @param course_updated $event The event.
     */
    public static function course_updated(course_updated $event): void {
        if (key_exists(helper::COURSE_NAME, $event->other['updatedfields'])) {
            $newcoursename = $event->other['updatedfields'][helper::COURSE_NAME];
            self::queue_adhoc_task('rename_item', [
                'itemid' => $event->courseid,
                'itemtype' => helper::ITEM_TYPE_COURSE,
                'itemname' => $newcoursename,
            ]);
        }

        if (key_exists('category', $event->other['updatedfields'])) {
            self::queue_adhoc_task('update_course_category', [
                'courseid' => $event->courseid,
                'categoryid' => $event->other['updatedfields']['category'],
            ]);
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

        self::queue_adhoc_task('remove_item', [
            'itemid' => $courseid,
            'itemtype' => helper::ITEM_TYPE_COURSE,
            'itemname' => $coursename,
        ]);
    }

    /**
     * Process course_category_created event.
     *
     * @param course_category_created $event The event.
     */
    public static function course_category_created(course_category_created $event): void {
        global $DB;

        $category = $DB->get_record('course_categories', ['id' => $event->objectid]);
        self::queue_adhoc_task('add_item', [
            'itemid' => $category->id,
            'itemtype' => helper::ITEM_TYPE_CATEGORY,
            'itemname' => $category->name,
        ]);
    }

    /**
     * Process course_category_updated event.
     *
     * @param course_category_updated $event The event.
     */
    public static function course_category_updated(course_category_updated $event): void {
        global $DB;

        $category = $DB->get_record('course_categories', ['id' => $event->objectid]);
        self::queue_adhoc_task('rename_item', [
            'itemid' => $category->id,
            'itemtype' => helper::ITEM_TYPE_CATEGORY,
            'itemname' => $category->name,
        ]);
    }

    /**
     * Process course_category_deleted event.
     *
     * @param course_category_deleted $event The event.
     */
    public static function course_category_deleted(course_category_deleted $event): void {
        $categoryid = $event->objectid;
        $categoryname = $event->other['name'];

        self::queue_adhoc_task('remove_item', [
            'itemid' => $categoryid,
            'itemtype' => helper::ITEM_TYPE_CATEGORY,
            'itemname' => $categoryname,
        ]);
    }

    /**
     * Process preset_created event.
     *
     * @param preset_created $event The event.
     */
    public static function preset_created(preset_created $event): void {
        $preset = preset::get_record(['id' => $event->other['presetid']]);

        self::queue_adhoc_task('add_item', [
            'itemid' => $preset->get('id'),
            'itemtype' => helper::ITEM_TYPE_PRESET,
            'itemname' => $preset->get('name'),
            'courseids' => helper::get_course_ids_from_preset($preset),
        ]);
    }

    /**
     * Process preset_deleted event.
     *
     * @param preset_deleted $event The event.
     */
    public static function preset_deleted(preset_deleted $event): void {

        $presetid = $event->other['presetid'];
        $presetname = $event->other['presetname'];

        self::queue_adhoc_task('remove_item', [
            'itemid' => $presetid,
            'itemtype' => helper::ITEM_TYPE_PRESET,
            'itemname' => $presetname,
        ]);
    }

    /**
     * Process preset_updated event.
     *
     * @param preset_updated $event The event.
     */
    public static function preset_updated(preset_updated $event): void {
        $presetid = $event->other['presetid'];
        $presetname = $event->other['presetname'];

        self::queue_adhoc_task('rename_item', [
            'itemid' => $presetid,
            'itemtype' => helper::ITEM_TYPE_PRESET,
            'itemname' => $presetname,
        ]);

        self::queue_adhoc_task('update_preset_data', [
            'itemid' => $presetid,
            'itemtype' => helper::ITEM_TYPE_PRESET,
            'itemname' => $presetname,
            'categories' => $event->other['categories'],
            'oldcategories' => $event->other['oldcategories'],
            'courses' => $event->other['courses'],
            'oldcourses' => $event->other['oldcourses'],
            'tags' => $event->other['tags'],
            'oldtags' => $event->other['oldtags'],
        ]);
    }
}
