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

use context;
use core\context\system;
use core_form\dynamic_form;
use moodle_url;
use tool_enrolprofile\event\preset_created;
use tool_enrolprofile\event\preset_updated;

/**
 * Preset form class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset_form extends dynamic_form {

    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $this->_form->addElement('hidden', 'id');
        $this->_form->setType('id', PARAM_INT);

        $this->_form->addElement('text', 'name', get_string('presetname', 'tool_enrolprofile'));
        $this->_form->addRule('name', get_string('required'), 'required');
        $this->_form->setType('name', PARAM_TEXT);

        $this->_form->addElement('static', 'error', '');

        foreach (helper::get_field_types() as $type) {
            $methodname = 'get_'  . $type . '_options';
            $this->_form->addElement(
                'autocomplete',
                $type,
                get_string('fieldtype:' . $type, 'tool_enrolprofile'),
                $this->$methodname(),
                ['noselectionstring' => get_string('choosedots'), 'multiple' => true]
            );
        }
    }

    /**
     * Gets categories options.
     *
     * @return array
     */
    protected function get_category_options(): array {
        $categories = [];

        foreach (helper::get_categories() as $category) {
            $categories[$category->id] = $category->name;
        }

        return $categories;
    }

    /**
     * Gets courses options.
     *
     * @return array
     */
    protected function get_course_options(): array {
        global $COURSE;

        $options = [];

        foreach (helper::get_courses() as $option) {
            // Skip front page course.
            if ($option->id == $COURSE->id) {
                continue;
            }

            $options[$option->id] = $option->fullname;
        }

        return $options;
    }

    /**
     * Gets courses options.
     *
     * @return array
     */
    protected function get_tag_options(): array {
        $options = [];

        foreach (helper::get_course_tags() as $option) {
            $options[$option->id] = $option->rawname;
        }

        return $options;
    }

    /**
     * Form validation.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     *
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $empty = 0;
        foreach (helper::get_field_types() as $type) {
            if (empty($data[$type])) {
                $empty++;
            }
        }

        if ($empty == count(helper::get_field_types())) {
            $errors['error'] = get_string('mustselectentities', 'tool_enrolprofile');
        }

        return $errors;
    }

    /**
     * Get context the submission is happening in.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): context {
        return system::instance();
    }

    /**
     * Check permissions.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_admin();
    }

    /**
     * Process dynamic form submission.
     *
     * @return \stdClass
     */
    public function process_dynamic_submission(): \stdClass {
        $data = $this->get_submitted_data();
        $olddata = new \stdClass();

        if (!empty($data->id)) {
            $preset = preset::get_record(['id' => $data->id]);
        } else {
            $preset = new preset();
        }

        foreach (helper::get_field_types() as $type) {
            if (!empty($data->$type)) {
                $data->$type = implode(',', $data->$type);
            } else {
                $data->$type = null;
            }

            $olddata->$type = $preset->get($type);
            $preset->set($type, $data->$type);
        }

        $preset->set('name', $data->name);
        $preset->save();

        $other = [
            'presetid' => $preset->get('id'),
            'presetname' => $preset->get('name'),
        ];

        foreach (helper::get_field_types() as $type) {
            $other[$type] = $data->$type;
            $other['old' . $type] = $olddata->$type;
        }

        if (empty($data->id)) {
            preset_created::create([
                'context' => $this->get_context_for_dynamic_submission(),
                'other' => $other,
            ])->trigger();
        } else {
            foreach (helper::get_field_types() as $type) {
                $other['old' . $type] = $olddata->$type;
            }
            preset_updated::create([
                'context' => $this->get_context_for_dynamic_submission(),
                'other' => $other,
            ])->trigger();
        }

        return $preset->to_record();
    }

    /**
     * Set data for submission.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        if (!empty($this->_ajaxformdata['id'])) {
            $preset = preset::get_record(['id' => $this->_ajaxformdata['id']]);
            $data = $preset->to_record();

        } else {
            $data = (object)[
                'id' => $this->optional_param('id', 0, PARAM_INT),
            ];
        }
        $this->set_data($data);
    }

    /**
     * URL the submission is happening on.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/admin/tool/enrolprofile/index.php');
    }
}
