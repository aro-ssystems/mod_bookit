import { BaseComponent } from 'core/reactive';
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
            {watch: 'state:updated', handler: this._handleStateEvent},
            // {watch: 'checklistcategories:created', handler: this._handleCategoryCreatedEvent},
        ];
    }

    stateReady(state) {

        // window.console.log('state ready');
        // window.console.log(state);

        // const name = state.masterchecklists.get(1).name;

        // window.console.log(this.selectors.MASTER_CHECKLIST_TITLE);
        window.console.log(this.selectors);

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

    _handleStateEvent(event) {
        // window.console.log('handle state event');
    }

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