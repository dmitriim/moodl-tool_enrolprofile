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

namespace tool_enrolprofile\event;

use core\event\base;
use moodle_url;

/**
 * Event triggered when a preset updated.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset_updated extends base {

    /**
     * Initialise the rule data.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['crud'] = 'u';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:preset_updated', 'tool_enrolprofile');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "User with id '{$this->userid}' updated preset with id '{$this->other['presetid']}'";
    }

    /**
     * Get URL related to the action.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url("/admin/tool/enrolprofile/index.php", ['id' => $this->other['presetid']]);
    }

    /**
     * Validates the custom data.
     *
     * @throws \coding_exception if missing required data.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['presetid'])) {
            throw new \coding_exception('The \'presetid\' value must be set in other.');
        }

        if (!isset($this->other['presetname'])) {
            throw new \coding_exception('The \'presetname\' value must be set in other.');
        }
    }
}
