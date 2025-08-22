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
            {watch: 'checklistcategories.items:updated', handler: this._handleCategoryItemsUpdatedEvent},
            {watch: 'checklistitems:created', handler: this._handleItemCreatedEvent},
            {watch: 'checklistitems:deleted', handler: this._handleItemDeletedEvent},
            {watch: 'checklistitems:updated', handler: this._handleItemUpdatedEvent},
            {watch: 'checklistitems.categoryid:updated', handler: this._handleItemCategoryUpdatedEvent},
            {watch: 'checklistitems.title:updated', handler: this._replaceRenderedItem},
            {watch: 'checklistitems.roomid:updated', handler: this._replaceRenderedItem},
            {watch: 'checklistitems.roleid:updated', handler: this._replaceRenderedItem},
            {watch: 'activeRole:updated', handler: this._handleRoleUpdate},
            {watch: 'activeRoom:updated', handler: this._handleRoomUpdate},
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

        this.addEventListener(this.getElement(this.selectors.ROLE_SELECT), 'change', (e) => {
            window.console.log('ROLE SELECT CHANGE', e);
            this.reactive.dispatch('roleChanged', { id: e.target.value });
        });
        this.addEventListener(this.getElement(this.selectors.ROOM_SELECT), 'change', (e) => {
            window.console.log('ROOM SELECT CHANGE', e);
            this.reactive.dispatch('roomChanged', { id: e.target.value });
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
        window.console.log('handle category created event', event);

        Templates.renderForPromise('mod_bookit/bookit_checklist_category',
            {
                id: event.element.id,
                name: event.element.name,
                order: event.element.order,
                masterid: 1, // TODO get from state
                type: 'category',
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
                type: 'item',
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

        // const targetElement = this.getElement(`#bookit-master-checklist-item-${event.element.id}`);

        // window.console.log('target element', targetElement);

        // TODO if category is changed, element needs to be moved, not just replaced

        // Templates.renderForPromise('mod_bookit/bookit_checklist_item',
        //     {
        //         id: event.element.id,
        //         title: event.element.title,
        //         order: event.element.order,
        //         categoryid: event.element.category,
        //         roomid: event.element.roomid,
        //         roomname: event.element.roomname,
        //         roleid: event.element.roleid,
        //         rolename: event.element.rolename,
        //     })
        //     .then(({html, js}) => {
        //         window.console.log('rendered item');
        //         window.console.log(html);
        //         // window.console.log(js);
        //         Templates.replaceNode(targetElement, html, js);
        //     })
        //     .then(async () => {
        //         Toast.add(await getString('checklistitemupdatesuccess', 'mod_bookit'),
        //             {type: 'success' });
        //             // this.dispatchEvent(this.events.categoryRendered, {
        //             //     component: this
        //             // });
        //     })
        //     .catch(error => {
        //         window.console.error('Error rendering checklist item:', error);
        //     });
    }

    _replaceRenderedItem(event) {

        window.console.log('replace rendered item');
        window.console.log(event.action);

        const actionParts = event.action.split('.');
        const fieldPart = actionParts[1].split(':')[0];

        const elementSelector = `td[data-bookit-checklistitem-tabledata-${fieldPart}-id="${event.element.id}"]`;

        window.console.log('elementSelector', elementSelector);

        const targetElement = this.getElement(elementSelector);

        window.console.log('targetElement', targetElement);


        if (fieldPart.endsWith('id')) {
            const nameField = fieldPart.substring(0, fieldPart.length - 2) + 'name';

            if (nameField in event.element) {
                targetElement.innerHTML = event.element[nameField];
            }
        } else {
            targetElement.innerHTML = event.element[fieldPart];
        }

        // targetElement.innerHTML = event.element[fieldPart];

        // Templates.renderForPromise('mod_bookit/bookit_checklist_item',
        //     {
        //         id: event.element.id,
        //         title: event.element.title,
        //         order: event.element.order,
        //         categoryid: event.element.categoryid,
        //         roomid: event.element.roomid,
        //         roomname: event.element.roomname,
        //         roleid: event.element.roleid,
        //         rolename: event.element.rolename,
        //     })
        //     .then(({html, js}) => {
        //         Templates.replaceNode(targetElement, html, js);
        //     })
        //     .then(async () => {
        //         Toast.add(await getString('checklistitemupdatesuccess', 'mod_bookit'),
        //             {type: 'success' });

        //     })
        //     .catch(error => {
        //         window.console.error('Error rendering checklist item:', error);
        //     });
    }

    _handleItemCategoryUpdatedEvent(event) {
        window.console.log('handle checklistitem category updated event');
        window.console.log(event);

        const itemObject = this.reactive.state.checklistitems.get(event.element.id);

        const formDataObj = {
            itemid: itemObject.id,
            masterid: 1,
            title: itemObject.title,
            categoryid: itemObject.categoryid,
            roomid: itemObject.roomid,
            roleid: itemObject.roleid,
            action: 'put',
            _qf__mod_bookit_form_edit_checklistitem_form: 1,
        };

        const formData = new URLSearchParams(formDataObj).toString();

        window.console.log('formData ITEM', formData);

        Ajax.call([{
            methodname: 'core_form_dynamic_form',
            args: {
                formdata: formData,
                form: 'mod_bookit\\form\\edit_checklistitem_form'
            }
            }])[0]
            .then((response) => {
                window.console.log('AJAX response received');
                window.console.log(response);

                // // TODO move item to target category

                // const itemElement = document.getElementById(`bookit-master-checklist-item-${itemObject.id}`);



                })
                .catch(exception => {
                    window.console.error('AJAX error:', exception);
                });

    }

    _handleCategoryDeletedEvent(event) {
        window.console.log('handle category deleted event');
        window.console.log(event);

        // TODO fix this like on item with this.remove()

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

    _handleCategoryItemsUpdatedEvent(event) {
        window.console.log('handle category checklistitems updated event');
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


            // TODO this response could trigger item updates via processUpdates maybe

        //     // const itemObject = this.reactive.state.checklistitems.get(dropdata.id);

        //     // const newEl = document.getElementById(`bookit-master-checklist-item-${itemObject.id}`);
        //     //         if (newEl) {
        //     //             this.element.parentNode.insertBefore(newEl, this.element.nextElementSibling);
        //     //         } else {
        //     //             window.console.warn('New element not found after appending');
        //     //         }

        //     // const data = JSON.parse(response.data);

        //     // window.console.log('data', data);
        //     // window.console.log(data[0].fields.items);
        //     // const itemsArray = data[0].fields.items.split(',').map(item => parseInt(item));
        //     // window.console.log('Items as array:', itemsArray);

        //     // // Now you can use itemsArray for rendering the ordered category items
        //     // itemsArray.forEach(item => {
        //     //     window.console.log('item', item);
        //     //     const itemObject = this.reactive.state.checklistitems.get(item);
        //     //     window.console.log('itemObject', itemObject);
        //     //     window.console.log('category', category);
        //     //     Templates.renderForPromise('mod_bookit/bookit_checklist_item',
        //     //     {
        //     //         id: itemObject.id,
        //     //         title: itemObject.title,
        //     //         order: itemObject.order,
        //     //         categoryid: category.id,
        //     //         roomid: itemObject.roomid,
        //     //         roomname: itemObject.roomname,
        //     //         roleid: itemObject.roleid,
        //     //         rolename: itemObject.rolename,
        //     //     })
        //     //     .then(({html, js}) => {
        //     //         // window.console.log('rendered item');
        //     //         // window.console.log(html);
        //     //         // window.console.log(js);
        //     //         this.getElement(`#bookit-master-checklist-item-${itemObject.id}`).remove();
        //     //         Templates.appendNodeContents(targetElement, html, js);
        //     //     })
        //     //     .then(async () => {
        //     //         // Toast.add(await getString('checklistitemsuccess', 'mod_bookit'),
        //     //         //     {type: 'success' });
        //     //     })
        //     //     .catch(error => {
        //     //         window.console.error('Error rendering checklist item:', error);
        //     //     });

        //     // });
        //     // TODO render the new ordered category items

        //     // if (!response.submitted) {
        //     //     // Form was not submitted because validation failed.
        //     //     const promise = new Promise(
        //     //         resolve => resolve({html: response.html, js: Fragment.processCollectedJavascript(response.javascript)}));
        //     //     this.modal.setBodyContent(promise);
        //     //     this.enableButtons();
        //     //     this.trigger(this.events.SERVER_VALIDATION_ERROR);
        //     // } else {
        //     //     // Form was submitted properly. Hide the modal and execute callback.
        //     //     const data = JSON.parse(response.data);
        //     //     FormChangeChecker.markFormSubmitted(form[0]);
        //     //     const event = this.trigger(this.events.FORM_SUBMITTED, data);
        //     //     if (!event.defaultPrevented) {
        //     //         this.modal.hide();
        //     //     }
        //     // }
        //     return null;
        })
        .catch(exception => {
            window.console.error('AJAX error:', exception);
        //     // this.enableButtons();
        //     // this.onSubmitError(exception);
        });
    }

    _handleRoleUpdate(event) {
        window.console.log('handle role update');
        window.console.log(event);

        const allCategoryElements = document.querySelectorAll(this.selectors.ALL_CATEGORY_TABLE_ROWS);
        allCategoryElements.forEach(categoryElement => {

            window.console.log('categoryElement', categoryElement);

            const category = this.reactive.state.checklistcategories.get(categoryElement.dataset.bookitCategoryId);

            window.console.log('category', category);
            const items = [...category.items];
            window.console.log('items', items);
            var hasVisibleItems = false;

            const activeRoom = this.reactive.state.activeRoom.id;

            items.forEach(itemId => {

                window.console.log('itemId', itemId);
                const itemElement = document.querySelector(`tr[data-bookit-checklistitem-id="${itemId}"]`);
                window.console.log('itemElement', itemElement);

                window.console.log('activeRoom', activeRoom);

                if (activeRoom === 0) {

                    window.console.log('activeRoom is 0, showing all items');

                    if (parseInt(itemElement.dataset.bookitChecklistitemRole) === event.element.id) {
                        itemElement.classList.remove('d-none');
                        if (!hasVisibleItems) {
                            hasVisibleItems = true;
                        }
                    } else {
                        itemElement.classList.add('d-none');
                    }
                } else if (activeRoom === parseInt(itemElement.dataset.bookitChecklistitemRoom)) {
                    window.console.log('activeRoom matches item room, checking role');
                    if (parseInt(itemElement.dataset.bookitChecklistitemRole) === event.element.id) {
                        itemElement.classList.remove('d-none');
                        if (!hasVisibleItems) {
                            hasVisibleItems = true;
                        }
                    } else {
                        itemElement.classList.add('d-none');
                    }
                } else {
                    window.console.log('activeRoom does not match item room, hiding item');
                    itemElement.classList.add('d-none');
                }
            });

            if (!hasVisibleItems) {
                categoryElement.classList.add('d-none');
            } else {
                categoryElement.classList.remove('d-none');
            }

        });

    }

    _handleRoomUpdate(event) {
        window.console.log('handle room update');
        window.console.log(event);
        const allCategoryElements = document.querySelectorAll(this.selectors.ALL_CATEGORY_TABLE_ROWS);
        allCategoryElements.forEach(categoryElement => {

            window.console.log('categoryElement', categoryElement);

            const category = this.reactive.state.checklistcategories.get(categoryElement.dataset.bookitCategoryId);

            window.console.log('category', category);
            const items = [...category.items];
            window.console.log('items', items);
            var hasVisibleItems = false;

            const activeRole = this.reactive.state.activeRole.id;

            items.forEach(itemId => {

                window.console.log('itemId', itemId);
                const itemElement = document.querySelector(`tr[data-bookit-checklistitem-id="${itemId}"]`);
                window.console.log('itemElement', itemElement);

                if (parseInt(itemElement.dataset.bookitChecklistitemRoom) === event.element.id) {
                    if (activeRole === 0 || parseInt(itemElement.dataset.bookitChecklistitemRole) === activeRole) {
                        itemElement.classList.remove('d-none');
                        if (!hasVisibleItems) {
                            hasVisibleItems = true;
                        }
                    } else {
                        itemElement.classList.add('d-none');
                    }
                } else {
                    // TODO this
                    if (event.element.id === 0 && parseInt(itemElement.dataset.bookitChecklistitemRole) === activeRole) {
                        itemElement.classList.remove('d-none');
                        if (!hasVisibleItems) {
                            hasVisibleItems = true;
                        }
                    } else {
                        itemElement.classList.add('d-none');
                    }
                }
            });

            if (!hasVisibleItems) {
                categoryElement.classList.add('d-none');
            } else {
                categoryElement.classList.remove('d-none');
            }

        });
    }

}