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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Presets management.
 * @module      tool_enrolprofile/prestes
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {add as notifyUser} from 'core/toast';
import Ajax from 'core/ajax';
import * as DynamicTable from 'core_table/dynamic';
import DynamicTableSelectors from 'core_table/local/dynamic/selectors';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import Pending from 'core/pending';

/**
 *
 * @type {{ADD_NEW_RECORD_BUTTON: string}}
 */
const SELECTORS = {
    ADD_PRESET_BUTTON: '#addpresetbutton',
    ACTION: '[data-action]',
};


/**
 * Gets dynamic table root container.
 *
 * @returns {*}
 */
const getTableRoot = () => {
    return document.querySelector(DynamicTableSelectors.main.region);
};

/**
 * Edit the log record.
 *
 * @param {string} id record ID.
 */
const editPreset = (id) => {
    const form = new ModalForm({
        formClass: "tool_enrolprofile\\preset_form",
        args: {
            id: id,
        },
        modalConfig: {
            title: getString('preset', 'tool_enrolprofile'),
        },
    });

    form.addEventListener(form.events.FORM_SUBMITTED, () => {
        if (id === 0) {
            sendFeedback('add');
        } else {
            sendFeedback('edit');
        }
    });

    form.show();
};

/**
 * Delete the log record.
 * @param {string} id ruleid.
 */
const deletePreset = (id) => {
    const pendingPromise = new Pending('tool_enrolprofile/form:confirmdelete');

    getStrings([
        {'key': 'confirm'},
        {'key': 'confirm:delete', component: 'tool_enrolprofile'},
        {'key': 'yes'},
        {'key': 'no'},
    ])
        .then(strings => {
            return Notification.confirm(strings[0], strings[1], strings[2], strings[3], function() {
                Ajax.call([{
                    methodname: 'tool_enrolprofile_delete_preset',
                    args: {
                        id: id,
                    }
                }])[0]
                    .then(function () {
                        sendFeedback('delete');
                    })
                    .catch(Notification.exception);
            });
        })
        .then(pendingPromise.resolve)
        .catch(Notification.exception);
};


/**
 * Send feedback to a user.
 *
 * @param {string} action Action to send feedback about.
 */
const sendFeedback = (action) => {
    getString('completed:' + action, 'tool_enrolprofile')
        .then(message => {
            notifyUser(message);
            const tableRoot = getTableRoot();
            if (tableRoot) {
                DynamicTable.refreshTableContent(tableRoot).catch(Notification.exception);
            }
        }).catch(Notification.exception);
};

/**
 * Attach events to add new record button.
 */
const attachAddNewButtonEvents = () => {
    const addNewRecordButton = document.querySelector(SELECTORS.ADD_PRESET_BUTTON);
    if (addNewRecordButton) {
        addNewRecordButton.addEventListener('click', e => {
            e.preventDefault();
            editPreset(0);
        });
    }
};

/**
 * Attach events to actions.
 */
const attachTableActionEvents = () => {
    document.querySelectorAll(SELECTORS.ACTION).forEach(element => {
        element.addEventListener('click', e => {
            e.preventDefault();
            const actionElement = e.target.closest(SELECTORS.ACTION);
            const id = actionElement.dataset.id;
            const action = actionElement.dataset.action;
            switch (action) {
                case 'edit':
                    editPreset(id);
                    break;
                case 'delete':
                    deletePreset(id);
                    break;
            }
        });
    });
};

/**
 * Init.
 */
export const init = () => {
    attachAddNewButtonEvents();
    attachTableActionEvents();
    document.addEventListener(DynamicTable.Events.tableContentRefreshed, () => attachTableActionEvents());
};
