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
 * Resource item reactive component.
 *
 * @module mod_bookit/resource_item
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {resourceReactiveInstance} from 'mod_bookit/resource_reactive';
import {SELECTORS} from 'mod_bookit/resource_reactive';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

export default class extends BaseComponent {

    create(descriptor) {
        const itemEditBtnSelector = 'EDIT_ITEM_BTN_' + descriptor.element.dataset.bookitItemId;
        this.selectors[itemEditBtnSelector] = `#edit-item-${descriptor.element.dataset.bookitItemId}`;
    }

    static init(target, selectors) {
        return new this({
            element: document.querySelector(target),
            reactive: resourceReactiveInstance,
            selectors: selectors || SELECTORS,
        });
    }

    getWatchers() {
        return [];
    }

    stateReady() {
        const itemEditBtnSelector = 'EDIT_ITEM_BTN_' + this.element.dataset.bookitItemId;

        this.addEventListener(this.getElement(this.selectors[itemEditBtnSelector]), 'click', (e) => {
            e.preventDefault();
            this._handleEdit(e);
        });
    }

    async _handleEdit(event) {
        const modalForm = new ModalForm({
            formClass: "mod_bookit\\form\\edit_resource_form",
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                id: event.currentTarget.value,
            },
            modalConfig: {
                title: await getString('resources:edit_resource', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);

            if (response.detail[0].action === 'delete') {
                this.reactive.dispatch('resourceDeleted', {id: parseInt(response.detail[0].fields.id)});
                this.remove();
                return;
            }
        });

        modalForm.show();
    }

    remove() {
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
    }
}
