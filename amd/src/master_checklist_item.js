import { BaseComponent, DragDrop } from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';
import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';
import { getString } from 'core/str';
import Ajax from 'core/ajax';

export default class extends BaseComponent {

    create(descriptor) {

        window.console.log('create item component: ' + descriptor.element.dataset.bookitChecklistitemTitle);

        const itemEditBtnSelector = 'EDIT_CHECKLISTITEM_BTN_' + descriptor.element.dataset.bookitChecklistitemId;
        this.selectors[itemEditBtnSelector] = `#edit-checklistitem-${descriptor.element.dataset.bookitChecklistitemId}`;

        // const itemTitleSelector = 'ITEM_TITLE_' + descriptor.element.dataset.bookitChecklistitemId;
        // this.selectors[itemTitleSelector] = `td[data-bookit-checklistitem-tabledata-title-id="${descriptor.element.dataset.bookitChecklistitemId}"]`;

        // const itemRoomSelector = 'ITEM_ROOM_' + descriptor.element.dataset.bookitChecklistitemId;
        // this.selectors[itemRoomSelector] = `td[data-bookit-checklistitem-tabledata-roomid-id="${descriptor.element.dataset.bookitChecklistitemId}"]`;

        // const itemRoleSelector = 'ITEM_ROLE_' + descriptor.element.dataset.bookitChecklistitemId;
        // this.selectors[itemRoleSelector] = `td[data-bookit-checklistitem-tabledata-roleid-id="${descriptor.element.dataset.bookitChecklistitemId}"]`;

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
        switch (dropdata.type) {
            case 'item':
                this._handleItemDrop(dropdata, event);
                break;
            case 'category':
                this._handleCategoryDrop(dropdata, event);
                break;
            default:
                throw new Error(`Unknown drop type: ${dropdata.type}`);
        }
    }


    showDropZone(dropdata, event) {

        window.console.log('show drop zone');
        window.console.log(dropdata);
        window.console.log(event);
        const root = document.querySelector('html');
        const primaryColor = getComputedStyle(root).getPropertyValue('--primary');

        switch (dropdata.type) {
            case 'item':
            this.element.style.boxShadow = `0px -5px 0px 0px ${primaryColor} inset`;
            this.element.style.transition = 'box-shadow 0.1s ease';
                break;
            case 'category':
                // probably needs last element, right now this looks weird
                const itemParentId = parseInt(this.element.dataset.bookitChecklistitemCategoryid);
                const categoryParentElement = document.getElementById(`bookit-master-checklist-tbody-category-${itemParentId}`);
                var isActive = parseInt(categoryParentElement.dataset.bookitCategoryActive || 0);
                if (!isActive) {
                    categoryParentElement.dataset.bookitCategoryActive = 1;
                }
                const categoryLastChild = categoryParentElement.lastElementChild;
                setTimeout(() => {
                    categoryParentElement.dataset.bookitCategoryActive = 0;
                    // categoryLastChild.style.boxShadow = `0px -5px 0px 0px ${primaryColor} inset`;
                    // categoryLastChild.style.transition = 'box-shadow 0.1s ease';
                }, 5);
                categoryLastChild.style.boxShadow = `0px -5px 0px 0px ${primaryColor} inset`;
                categoryLastChild.style.transition = 'box-shadow 0.1s ease';
                break;
            default:
                throw new Error(`Unknown drop type: ${dropdata.type}`);
        }

    }

    hideDropZone(dropdata, event) {

        window.console.log('hide drop zone');
        window.console.log(dropdata);
        window.console.log(event);

        switch (dropdata.type) {
            case 'item':
                this.element.style.boxShadow = '';
                this.element.style.transition = '';
                break;
            case 'category':
                const itemParentId = parseInt(this.element.dataset.bookitChecklistitemCategoryid);
                const categoryParentElement = document.getElementById(`bookit-master-checklist-tbody-category-${itemParentId}`);
                const categoryLastChild = categoryParentElement.lastElementChild;
                var isActive = parseInt(categoryParentElement.dataset.bookitCategoryActive || 0);
                if (!isActive) {
                    categoryLastChild.style.boxShadow = '';
                    categoryLastChild.style.transition = '';
                }
                break;
            default:
                throw new Error(`Unknown drop type: ${dropdata.type}`);
        }

    }

    _handleItemDrop(dropdata, event) {
        window.console.log('handle item drop on item');
        window.console.log(event);

        dropdata.targetId = parseInt(this.element.dataset.bookitChecklistitemId);
        dropdata.targetParentId = parseInt(this.element.dataset.bookitChecklistitemCategoryid);
        window.console.log(`whoops you dropped an ${dropdata.type} on an item`, dropdata);

        this.reactive.dispatch('reOrderCategoryItems', dropdata);

        const itemObject = this.reactive.state.checklistitems.get(dropdata.id);

        const itemElement = document.getElementById(`bookit-master-checklist-item-${itemObject.id}`);

        const itemHasChangedParent = dropdata.parentId !== dropdata.targetParentId;

        if (itemHasChangedParent) {
            itemElement.dataset.bookitChecklistitemCategoryid = dropdata.targetParentId;
        }

        this.element.parentNode.insertBefore(itemElement, this.element.nextElementSibling);
    }


    _handleCategoryDrop(dropdata, event) {
        window.console.log('handle category drop on item');
        window.console.log(dropdata);
        window.console.log(event);

        const categoryElement = document.getElementById(`bookit-master-checklist-tbody-category-${dropdata.id}`);

        dropdata.targetId = this.element.dataset.bookitChecklistitemCategoryid;
        dropdata.targetParentId = categoryElement.dataset.bookitCategoryMasterid;
        window.console.log(`whoops you dropped an ${dropdata.type} on an item`, dropdata);

        this.reactive.dispatch('reOrderCategories', dropdata);

        // const categoryElement = document.getElementById(`bookit-master-checklist-tbody-category-${dropdata.id}`);

        const tableElement = document.querySelector(this.selectors.TABLE);

        categoryElement.dataset.bookitCategoryActive = 1;

        this.hideDropZone(dropdata, event);

        // tableElement.append(categoryElement);
        tableElement.insertBefore(categoryElement, this.element.parentNode.nextElementSibling);

        // const categoryObject = this.reactive.state.checklistcategories.get(dropdata.id);

        // const categoryElement = document.getElementById(`bookit-master-checklist-tbody-category-${categoryObject.id}`);

        // this.element.parentNode.append(categoryElement);
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
            window.console.log('EDIT ITEM FORM SUBMITTED - RESPONSE:');
            window.console.log(response);
            // TODO handle response
            // this.reactive.stateManager.processUpdates(response.detail);
            this.reactive.stateManager.processUpdates(response.detail);

            // TODO if delete, skip rest but trigger db update for cat

            if (response.detail[0].action === 'delete') {
                window.console.log('item deleted - removing & unregistering');
                this.reactive.dispatch('checklistitemDeleted',
                    {
                        id: parseInt(response.detail[0].fields.id),
                        categoryid: parseInt(this.element.dataset.bookitChecklistitemCategoryid),
                    });
                this.remove();
                return;
            }

            const parentId = parseInt(this.element.dataset.bookitChecklistitemCategoryid);

            const updatedParentId = parseInt(response.detail[0].fields.categoryid);

            if (parentId !== updatedParentId) {

                const targetParentCategoryObject = this.reactive.state.checklistcategories.get(response.detail[0].fields.categoryid);

                window.console.log("TARGET PARENT ITEMS", targetParentCategoryObject.items);

                const copiedArray = [...targetParentCategoryObject.items];

                const lastItemOfParentCategoryId = copiedArray.pop();

                const data = {
                    id: parseInt(this.element.dataset.bookitChecklistitemId),
                    type: 'item',
                    parentId: parentId,
                    targetId: lastItemOfParentCategoryId,
                    targetParentId: updatedParentId,
                }

                window.console.log('item has changed parent - DISPATCHING REORDER EVENT');
                window.console.log('data for reOrderCategoryItems ind item edit btn handler', data);
                this.reactive.dispatch('reOrderCategoryItems', data);

                // const itemObject = this.reactive.state.checklistitems.get(data.id);

                this.element.dataset.bookitChecklistitemCategoryid = data.targetParentId;

                const targetParentElement = document.getElementById(`bookit-master-checklist-tbody-category-${data.targetParentId}`);

                targetParentElement.append(this.element);
            }


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