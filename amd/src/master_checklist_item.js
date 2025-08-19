import {BaseComponent, DragDrop} from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';
import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';
import {getString} from 'core/str';
import Ajax from 'core/ajax';

export default class extends BaseComponent {

    create(descriptor) {

        window.console.log('create item component: ' + descriptor.element.dataset.bookitChecklistitemTitle);

        const itemEditBtnSelector = 'EDIT_CHECKLISTITEM_BTN_' + descriptor.element.dataset.bookitChecklistitemId;

        this.selectors[itemEditBtnSelector] = `#edit-checklistitem-${descriptor.element.dataset.bookitChecklistitemId}`;

    }

    static init(target, selectors) {
        return new this({
            element: document.querySelector(target),
            reactive: masterChecklistReactiveInstance,
            selectors: selectors || SELECTORS,
        });
    }

    getWatchers() {
        // window.console.log('GET WATCHERS');
        return [
            // {watch: 'state:updated', handler: this._handleStateEvent},
            // {watch: 'checklistcategories:created', handler: this._handleCategoryCreatedEvent},
        ];
    }

    stateReady(state) {

        // this.relativeDrag = true;

        this.dragdrop = new DragDrop(this);

        // window.console.log('state ready');
        // window.console.log(state);

        // const name = state.masterchecklists.get(1).name;

        // window.console.log(this.selectors.MASTER_CHECKLIST_TITLE);
        // window.console.log(this.selectors);

        const itemEditBtnSelector = 'EDIT_CHECKLISTITEM_BTN_' + this.element.dataset.bookitChecklistitemId;

        this.addEventListener(this.getElement(this.selectors[itemEditBtnSelector]), 'click', (e) => {
            e.preventDefault();
            window.console.log('EDIT CHECKLIST ITEM BUTTON CLICKED', e.currentTarget);
            this._handleEditChecklistItemButtonClick(e);
        });

        // this.addEventListener(this.getElement('button[data-action="delete"]'), 'click', (e) => {
        //         e.preventDefault();
        //         window.console.log('DELETE CHECKLIST ITEM BUTTON CLICKED', e.currentTarget);
        //     });

    }

    destroy() {
        if (this.dragdrop !== undefined) {
            this.dragdrop.unregister();
        }
    }

    validateDropData(dropdata) {

        return true;
    }

    drop(dropdata, event) {
        dropdata.targetId = this.element.dataset.bookitChecklistitemId;
        dropdata.targetParentId = this.element.dataset.bookitChecklistitemCategory;
        window.console.log('whoops you dropped this on an item', dropdata);

        // const itemObject = this.reactive.state.checklistitems.get(dropdata.id);

        // const newEl = document.getElementById(`bookit-master-checklist-item-${itemObject.id}`);
        // if (newEl) {

        //     const itemHasChangedParent = dropdata.parentId !== dropdata.targetParentId;

        //     window.console.log('item has changed parent: ', itemHasChangedParent);

        //     this.element.parentNode.insertBefore(newEl, this.element.nextElementSibling);

        //     const targetElement = document.getElementById(`bookit-master-checklist-tbody-category-${dropdata.targetParentId}`);

        //     window.console.log('target element', targetElement);

        //     const category = this.reactive.state.checklistcategories.get(dropdata.targetParentId);
        //     window.console.log('category in _handleCategoryItemUpdatedEvent', category);

        //     const formDataObj = {
        //         id: category.id,
        //         masterid: 1,
        //         name: category.name,
        //         checklistitems: category.items,
        //         action: 'put',
        //         _qf__mod_bookit_form_edit_checklist_category_form: 1,
        //     };

        //     const formData = new URLSearchParams(formDataObj).toString();

        //     window.console.log('formData', formData);


        //     Ajax.call([{
        //         methodname: 'core_form_dynamic_form',
        //         args: {
        //             formdata: formData,
        //             form: 'mod_bookit\\form\\edit_checklist_category_form'
        //         }
        //     }])[0]
        //     .then((response) => {

        //         window.console.log('AJAX response received');
        //         window.console.log(response);

        //         if (itemHasChangedParent) {

        //             // TODO call category update

        //             const formDataObj = {
        //                 itemid: dropdata.id,
        //                 masterid: 1,
        //                 title: itemObject.title,
        //                 categoryid: category.id,
        //                 roomid: itemObject.roomid,
        //                 roleid: itemObject.roleid,
        //                 action: 'put',
        //                 _qf__mod_bookit_form_edit_checklistitem_form: 1,
        //             };

        //             const formData = new URLSearchParams(formDataObj).toString();

        //             window.console.log('formData ITEM', formData);


        //             Ajax.call([{
        //                 methodname: 'core_form_dynamic_form',
        //                 args: {
        //                     formdata: formData,
        //                     form: 'mod_bookit\\form\\edit_checklistitem_form'
        //                 }
        //                 }])[0]
        //                 .then((response) => {

        //                     window.console.log('AJAX response received');
        //                     window.console.log(response);

        //                     })
        //                     .catch(exception => {
        //                         window.console.error('AJAX error:', exception);
        //                     });


        //         }

        //         return null;
        //     })
        //     .catch(exception => {
        //         window.console.error('AJAX error:', exception);
        //     });



        //     } else {
        //     window.console.warn('New element not found after appending');
        //     }

        this.reactive.dispatch('reOrderCategoryItems', dropdata);

        // Templates.renderForPromise('mod_bookit/bookit_checklist_item',
        //         {
        //             id: itemObject.id,
        //             title: itemObject.title,
        //             order: itemObject.order,
        //             categoryid: dropdata.targetParentId,
        //             roomid: itemObject.roomid,
        //             roomname: itemObject.roomname,
        //             roleid: itemObject.roleid,
        //             rolename: itemObject.rolename,
        //         })
        //         .then(({html, js}) => {
        //             // window.console.log('rendered item');
        //             // window.console.log(html);
        //             // window.console.log(js);
        //             // this.getElement(`#bookit-master-checklist-item-${itemObject.id}`).remove();
        //             this.reactive.dispatch('reOrderCategoryItems', dropdata);
        //             // document.getElementById(`bookit-master-checklist-item-${dropdata.id}`).remove();
        //             window.console.log('element: ', this.element);
        //             window.console.log('element next sibling: ', this.element.nextElementSibling);
        //             // Templates.appendNodeContents(this.element.parentNode, html, js);

        //             // Step 2: Move the newly inserted element before the target
        //             const newEl = document.getElementById(`bookit-master-checklist-item-${itemObject.id}`);
        //             if (newEl) {
        //                 this.element.parentNode.insertBefore(newEl, this.element.nextElementSibling);
        //             } else {
        //                 window.console.warn('New element not found after appending');
        //             }
        //         })
        //         .then(async () => {
        //             // Toast.add(await getString('checklistitemsuccess', 'mod_bookit'),
        //             //     {type: 'success' });
        //         })
        //         .catch(error => {
        //             window.console.error('Error rendering checklist item:', error);
        //         });

        // this.reactive.dispatch('reOrderCategoryItems', dropdata);

        // document.getElementById(`bookit-master-checklist-item-${dropdata.id}`).remove();
    }


    showDropZone(dropdata, event) {
        const root = document.querySelector('html');
        const primaryColor = getComputedStyle(root).getPropertyValue('--primary');

        window.console.log('primary color: ', primaryColor);

        this.element.style.boxShadow = `0px -5px 0px 0px ${primaryColor} inset`;
        this.element.style.transition = 'box-shadow 0.1s ease';
    }

    hideDropZone(dropdata, event) {
        this.element.style.boxShadow = '';
        this.element.style.backgroundBlendMode = '';
        this.element.style.transition = '';
    }

    // getDraggableData() {
    //     return {id: 35, name: "Something"};
    // }

    // dragStart(dragData, event) {
    //     // This method is called when the drag operation starts.
    //     window.console.log('Drag started', dragData);
    // }

    // dragEnd(dragData, event) {
    //     // This method is called when the drag operation ends.
    //     window.console.log('Drag ended', dragData);
    // }

    // _handleStateEvent(event) {
    //     window.console.log('handle state event');
    // }

    async _handleEditChecklistItemButtonClick(event) {
        window.console.log('handle edit checklist item button click');
        window.console.log(event);
        const modalForm = new ModalForm({
            formClass: "mod_bookit\\form\\edit_checklistitem_form",
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                masterid: 1,
                itemid: event.currentTarget.value,
                categories: Array.from(this.reactive.state.checklistcategories.values()),
            },
            modalConfig: {
                title: await getString('checklistitem', 'mod_bookit'),
            },

        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);
        });

        modalForm.addEventListener(modalForm.events.LOADED, (response) => {
            window.console.log('FORM LOADED');
            window.console.log(response);
            const deleteButton = modalForm.modal.getRoot().find('button[data-action="delete"]');

            window.console.log('DELETE BUTTON', deleteButton);

            deleteButton.on('click', (e) => {

                window.console.log('DELETE CHECKLIST ITEM BUTTON CLICKED', e.currentTarget);
                window.console.log('DELETE ITEM ' + this.element.dataset.bookitChecklistitemId);

                modalForm.getFormNode().querySelector('input[name="action"]').value = 'delete';
                modalForm.submitFormAjax();
                // TODO delete function
            });
        });

        // modalForm.show();
        modalForm.show();

    }


    // _handleCategoryCreatedEvent(event) {
    //     window.console.log('handle category created event');
    //     window.console.log(event.element);

    //     // TODO cats and/or items should be own components

    //     Templates.renderForPromise('mod_bookit/bookit_checklist_category',
    //         {
    //             id: event.element.id,
    //             name: event.element.name,
    //             order: event.element.order
    //         })
    //         .then(({html, js}) => {
    //             // Templates.appendNodeContents(this.getElement(this.selectors.TABLE_BODY), html, js);
    //             Templates.appendNodeContents(this.getElement(this.selectors.TABLE), html, js);
    //         })
    //         .catch();
    // }

}