import { BaseComponent } from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';
import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';
import {getString} from 'core/str';

export default class extends BaseComponent {

    create(descriptor) {

        window.console.log('create category component: ' + descriptor.element.dataset.bookitCategoryName);
        window.console.log(descriptor.element.dataset);

        const categoryEditBtnSelector = 'EDIT_CHECKLISTCATEGORY_BTN_' + descriptor.element.dataset.bookitCategoryId;

        this.selectors[categoryEditBtnSelector] = `#edit-checklistcategory-${descriptor.element.dataset.bookitCategoryId}`;

        window.console.log(this.selectors);

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

        // window.console.log('state ready');
        // window.console.log(state);

        // const name = state.masterchecklists.get(1).name;

        // window.console.log(this.selectors.MASTER_CHECKLIST_TITLE);
        window.console.log(this.element.dataset);

        const categoryEditBtnSelector = 'EDIT_CHECKLISTCATEGORY_BTN_' + this.element.dataset.bookitCategoryId;

        this.addEventListener(this.getElement(this.selectors[categoryEditBtnSelector]), 'click', (e) => {
            e.preventDefault();
            window.console.log('EDIT CHECKLIST CATEGORY BUTTON CLICKED', e.currentTarget);
            this._handleEditChecklistCategoryButtonClick(e);
        });

    }

    async _handleEditChecklistCategoryButtonClick(event) {
        window.console.log('handle edit checklist category button click');
        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\form\\edit_checklist_category_form',
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                id: this.element.dataset.bookitCategoryId,
                masterid: 1
            },
            modalConfig: {
                title: await getString('checklistcategory', 'mod_bookit'),
            },
        })

        modalForm.show();

    };



    // _handleStateEvent(event) {
    //     window.console.log('handle state event');
    // }


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