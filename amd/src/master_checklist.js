import { BaseComponent } from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';
import * as Toast from 'core/toast';
import {getString} from 'core/str';

export default class extends BaseComponent {

    create(descriptor) {

        window.console.log('create component: ' + descriptor.reactive.name);
        window.console.log("selectors in create master checklist: ", SELECTORS);

    }

    static init(target, selectors) {
        return new this({
            element: document.querySelector(target),
            reactive: masterChecklistReactiveInstance,
            selectors: selectors || SELECTORS,
        });
    }

    getWatchers() {
        window.console.log('GET WATCHERS');
        return [
            {watch: 'state:updated', handler: this._handleStateEvent},
            {watch: 'checklistcategories:created', handler: this._handleCategoryCreatedEvent},
            {watch: 'checklistcategories:deleted', handler: this._handleCategoryDeletedEvent},
            {watch: 'checklistcategories:updated', handler: this._handleCategoryUpdatedEvent},
            {watch: 'checklistitems:created', handler: this._handleItemCreatedEvent},
            {watch: 'checklistitems:deleted', handler: this._handleItemDeletedEvent},
            {watch: 'checklistitems:updated', handler: this._handleItemUpdatedEvent},
        ];
    }

    stateReady(state) {

        window.console.log('state ready');
        window.console.log(state);

        const name = state.masterchecklists.get(1).name;

        window.console.log(this.selectors.MASTER_CHECKLIST_TITLE);

        const titleElement = this.getElement(this.selectors.MASTER_CHECKLIST_TITLE);

        window.console.log(titleElement);

        titleElement.innerHTML = name;

        this.addEventListener(this.getElement(this.selectors.ADD_CHECKLIST_ITEM_BUTTON), 'click', (e) => {
            e.preventDefault();
            window.console.log('ADD CHECKLIST ITEM BUTTON CLICKED');
            this._handleAddChecklistItemButtonClick(e);
        });

        this.addEventListener(this.getElement(this.selectors.ADD_CHECKLIST_CATEGORY_BUTTON), 'click', (e) => {
            e.preventDefault();
            window.console.log('ADD CHECKLIST CATEGORY BUTTON CLICKED');
            this._handleAddChecklistCategoryButtonClick(e);
        });
    }

    _handleStateEvent(event) {
        // window.console.log('handle state event');
    }

    async _handleAddChecklistItemButtonClick(event) {
        window.console.log('handle add checklist item button click');

        window.console.log(Array.from(this.reactive.state.checklistcategories.values()));

        // TODO dont do this twice
        const modalForm = new ModalForm({
            formClass: "mod_bookit\\form\\edit_checklistitem_form",
            // moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                masterid: 1,
                itemid: null,
                categories: Array.from(this.reactive.state.checklistcategories.values()),
            },
            modalConfig: {
                title: await getString('checklistitem', 'mod_bookit'),
            },

        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);
        });

        modalForm.show();
    }

    async _handleAddChecklistCategoryButtonClick(event) {
        window.console.log('handle add checklist category button click');
        const modalForm = new ModalForm({
            formClass: "mod_bookit\\form\\edit_checklist_category_form",
            // moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                masterid: 1
            },
            modalConfig: {
                title: await getString('checklistcategory', 'mod_bookit'),
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);

        });

        modalForm.addEventListener(modalForm.events.NOSUBMIT_BUTTON_PRESSED, (response) => {
            window.console.log('no submit button pressed');
            window.console.log(response);
            // this.reactive.stateManager.processUpdates(response.detail);

        });

        modalForm.show();
    }

    _handleCategoryCreatedEvent(event) {
        window.console.log('handle category created event');

        Templates.renderForPromise('mod_bookit/bookit_checklist_category',
            {
                id: event.element.id,
                name: event.element.name,
                order: event.element.order
            })
            .then(({html, js}) => {
                Templates.appendNodeContents(this.getElement(this.selectors.TABLE), html, js);
            })
            .then(async () => {
                Toast.add(await getString('checklistcategorysuccess', 'mod_bookit'),
                    {type: 'success' });
            })
            .catch();
    }

    _handleItemCreatedEvent(event) {
        window.console.log('handle item created event');

        // const roomName = this.reactive.state.rooms.get(1).name;

        const targetElement = this.getElement(`#bookit-master-checklist-tbody-category-${event.element.category}`);

        window.console.log('event: ', event);

        Templates.renderForPromise('mod_bookit/bookit_checklist_item',
            {
                id: event.element.id,
                title: event.element.title,
                order: event.element.order,
                categoryid: event.element.category,
                roomid: event.element.roomid,
                roomname: event.element.roomname,
                roleid: event.element.roleid,
                rolename: event.element.rolename,
            })
            .then(({html, js}) => {
                // window.console.log('rendered item');
                // window.console.log(html);
                // window.console.log(js);
                Templates.appendNodeContents(targetElement, html, js);
            })
            .then(async () => {
                Toast.add(await getString('checklistitemsuccess', 'mod_bookit'),
                    {type: 'success' });
            })
            .catch(error => {
                window.console.error('Error rendering checklist item:', error);
            });
    }

    _handleItemDeletedEvent(event) {
        window.console.log('handle item deleted event');
        window.console.log(event);
        const targetElement = this.getElement(`#bookit-master-checklist-item-${event.element.id}`);
        targetElement.remove();

        Toast.add(getString('checklistitemdeleted', 'mod_bookit', {title: event.element.title}),
            {type: 'success' });
    }

    _handleItemUpdatedEvent(event) {
        window.console.log('handle item updated event');
        window.console.log(event);

        const targetElement = this.getElement(`#bookit-master-checklist-item-${event.element.id}`);

        window.console.log('target element', targetElement);

        // TODO if category is changed, element needs to be moved, not just replaced

        Templates.renderForPromise('mod_bookit/bookit_checklist_item',
            {
                id: event.element.id,
                title: event.element.title,
                order: event.element.order,
                categoryid: event.element.category,
                roomid: event.element.roomid,
                roomname: event.element.roomname,
                roleid: event.element.roleid,
                rolename: event.element.rolename,
            })
            .then(({html, js}) => {
                window.console.log('rendered item');
                window.console.log(html);
                // window.console.log(js);
                Templates.replaceNode(targetElement, html, js);
            })
            .then(async () => {
                Toast.add(await getString('checklistitemupdatesuccess', 'mod_bookit'),
                    {type: 'success' });
            })
            .catch(error => {
                window.console.error('Error rendering checklist item:', error);
            });
    }

    _handleCategoryDeletedEvent(event) {
        window.console.log('handle category deleted event');
        window.console.log(event);
    }

    _handleCategoryUpdatedEvent(event) {
        window.console.log('handle category updated event');
        window.console.log(event);

        const targetElement = this.getElement(`#bookit-master-checklist-category-row-${event.element.id}`);

        window.console.log('target element', targetElement);

        Templates.renderForPromise('mod_bookit/bookit_checklist_category_row',
            {
                id: event.element.id,
                name: event.element.name,
                order: event.element.order,
            })
            .then(({html, js}) => {
                window.console.log('rendered category');
                window.console.log(html);
                // window.console.log(js);
                Templates.replaceNode(targetElement, html, js);
            })
            .then(async () => {
                Toast.add(await getString('checklistcategoryupdatesuccess', 'mod_bookit'),
                    {type: 'success' });
            })
            .catch(error => {
                window.console.error('Error rendering checklist category:', error);
            });
    }

}