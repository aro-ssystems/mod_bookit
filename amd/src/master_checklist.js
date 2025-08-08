import { BaseComponent } from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';
import * as Toast from 'core/toast';
import {getString} from 'core/str';
import Ajax from 'core/ajax';

export default class extends BaseComponent {

    static getEvents() {
        return {
            categoryRendered: 'mod_bookit:master_checklist_category_rendered',
        };
    }

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

    // static getEvents() {
    //     return {
    //         categoryRendered: 'mod_bookit:master_checklist_category_rendered',
    //     };
    // }

    getWatchers() {
        window.console.log('GET WATCHERS');
        return [
            {watch: 'state:updated', handler: this._handleStateEvent},
            {watch: 'checklistcategories:created', handler: this._handleCategoryCreatedEvent},
            {watch: 'checklistcategories:deleted', handler: this._handleCategoryDeletedEvent},
            {watch: 'checklistcategories.name:updated', handler: this._handleCategoryNameUpdatedEvent},
            {watch: 'checklistcategories.items:updated', handler: this._handleCategoryItemUpdatedEvent},
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

            // TODO dispatch event to update state
            this.reactive.dispatch('checklistitemCreated', response.detail);
            // this.reactive.stateManager.processUpdates(response.detail);
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
                    // this.dispatchEvent(this.events.categoryRendered, {
                    //     component: this
                    // });
            })
            .catch(error => {
                window.console.error('Error rendering checklist item:', error);
            });
    }

    _handleCategoryDeletedEvent(event) {
        window.console.log('handle category deleted event');
        window.console.log(event);

        const targetElement = this.getElement(`#bookit-master-checklist-tbody-category-${event.element.id}`);

        targetElement.remove();

        Toast.add(getString('checklistcategorydeleted', 'mod_bookit', {name: event.element.name}),
            {type: 'success' });
    }

    _handleCategoryNameUpdatedEvent(event) {
        window.console.log('handle category name updated event');
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
                // window.console.log('rendered category');
                // window.console.log(html);
                // window.console.log(js);
                Templates.replaceNode(targetElement, html, js);
                // this.dispatchEvent(this.events.categoryRendered, {
                //     foo: 'bar'
                // });
            })
            .then(async () => {
                Toast.add(await getString('checklistcategoryupdatesuccess', 'mod_bookit'),
                    {type: 'success' });
                    this.dispatchEvent(this.events.categoryRendered, {
                        categoryId: event.element.id
                    });
            })
            .catch(error => {
                window.console.error('Error rendering checklist category:', error);
            });

    }

    async _handleCategoryItemUpdatedEvent(event) {
        window.console.log('handle category item updated event');
        window.console.log(event);

        const targetElement = this.getElement(`#bookit-master-checklist-tbody-category-${event.element.id}`);

        window.console.log('target element', targetElement);

        const category = this.reactive.state.checklistcategories.get(event.element.id);
        window.console.log('category in _handleCategoryItemUpdatedEvent', category);

        const formDataObj = {
            id: category.id,
            masterid: 1,
            name: category.name,
            checklistitems: category.items,
            action: 'put',
            _qf__mod_bookit_form_edit_checklist_category_form: 1,
        };

        const formData = new URLSearchParams(formDataObj).toString();

        window.console.log('formData', formData);

        Ajax.call([{
            methodname: 'core_form_dynamic_form',
            args: {
                formdata: formData,
                form: 'mod_bookit\\form\\edit_checklist_category_form'
            }
        }])[0]
        .then((response) => {

            window.console.log('AJAX response received');
            window.console.log(response);
            // if (!response.submitted) {
            //     // Form was not submitted because validation failed.
            //     const promise = new Promise(
            //         resolve => resolve({html: response.html, js: Fragment.processCollectedJavascript(response.javascript)}));
            //     this.modal.setBodyContent(promise);
            //     this.enableButtons();
            //     this.trigger(this.events.SERVER_VALIDATION_ERROR);
            // } else {
            //     // Form was submitted properly. Hide the modal and execute callback.
            //     const data = JSON.parse(response.data);
            //     FormChangeChecker.markFormSubmitted(form[0]);
            //     const event = this.trigger(this.events.FORM_SUBMITTED, data);
            //     if (!event.defaultPrevented) {
            //         this.modal.hide();
            //     }
            // }
            return null;
        })
        .catch(exception => {
            window.console.error('AJAX error:', exception);
            // this.enableButtons();
            // this.onSubmitError(exception);
        });
    }

}