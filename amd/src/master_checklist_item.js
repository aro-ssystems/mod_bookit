import {BaseComponent, DragDrop} from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';
import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';
import {getString} from 'core/str';

export default class extends BaseComponent {

    create(descriptor) {

        window.console.log('create item component: ' + descriptor.element.dataset.bookitChecklistitemName);

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
        window.console.log('whoops you dropped this on an item', dropdata);
        this.reactive.dispatch('reOrderCategoryItems', dropdata);
    }


    showDropZone(dropdata, event) {


        const root = document.querySelector('html');
        const primaryColor = getComputedStyle(root).getPropertyValue('--primary');

        window.console.log('primary color: ', primaryColor);


            this.element.style.backgroundImage = `linear-gradient(0deg,${primaryColor} 0%, rgba(255, 255, 255, 0) 8%)`;
            this.element.style.backgroundBlendMode = 'multiply';
            this.element.style.transition = 'background 0.2s ease';

    }

    hideDropZone(dropdata, event) {

        this.element.style.backgroundImage = '';
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