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

        const categoryTbodySelector = 'CATEGORY_TBODY_' + descriptor.element.dataset.bookitCategoryId;
        this.selectors[categoryTbodySelector] = `#bookit-master-checklist-tbody-category-${descriptor.element.dataset.bookitCategoryId}`;

        window.console.log(this.selectors);

//         document.addEventListener('categoryRendered', (e) => {
//     window.console.log('🔥 caught event on document:', e.detail);
// }, true);

    }

    static init(target, selectors) {
        return new this({
            element: document.querySelector(target),
            reactive: masterChecklistReactiveInstance,
            selectors: selectors || SELECTORS,
        });
    }

    getWatchers() {
        return [
            {watch: 'checklistcategories.name:updated', handler: this._refreshEditButtonListener},
            {watch: 'mod_bookit:master_checklist_category_rendered', handler: this._foo},
            // {watch: 'state:updated', handler: this._handleStateEvent},
            // {watch: 'checklistcategories:created', handler: this._handleCategoryCreatedEvent},
        ];
    }

    stateReady(state) {
        window.console.log(this.element.dataset);

        const categoryEditBtnSelector = 'EDIT_CHECKLISTCATEGORY_BTN_' + this.element.dataset.bookitCategoryId;

        this.addEventListener(this.getElement(this.selectors[categoryEditBtnSelector]), 'click', (e) => {
            e.preventDefault();
            window.console.log('EDIT CHECKLIST CATEGORY BUTTON CLICKED', e.currentTarget);
            this._handleEditChecklistCategoryButtonClick(e);
        });

        const categoryTbodySelector = 'CATEGORY_TBODY_' + this.element.dataset.bookitCategoryId;

        const elem = document.querySelector(this.selectors.MAIN_ELEMENT);
        window.console.log('elem:', elem);

        // this.addEventListener(document.querySelector(this.selectors.MAIN_ELEMENT), 'mod_bookit:master_checklist_category_rendered', (e) => {
        //     window.console.log('category rendered event received in component');
        //     window.console.log(e);
        //     this._foo(e);
        // });

        document.addEventListener('mod_bookit:master_checklist_category_rendered', (e) => {
            window.console.log('NOW THIS', e.detail);
            this._refreshEditButtonListener(e);
        }, true);


    }

    async _handleEditChecklistCategoryButtonClick(event) {
        window.console.log('handle edit checklist category button click');
        const modalForm = new ModalForm({
            formClass: 'mod_bookit\\form\\edit_checklist_category_form',
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                id: this.element.dataset.bookitCategoryId,
                masterid: 1,
                checklistitems: JSON.stringify(this.reactive.state.checklistcategories.get(this.element.dataset.bookitCategoryId).items),
            },
            modalConfig: {
                title: await getString('checklistcategory', 'mod_bookit'),
            },
        })

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);
        });

        modalForm.addEventListener(modalForm.events.LOADED, (response) => {

            const deleteButton = modalForm.modal.getRoot().find('button[data-action="delete"]');

            deleteButton.on('click', (e) => {
                modalForm.getFormNode().querySelector('input[name="action"]').value = 'delete';
                modalForm.submitFormAjax();
            });
        });

        modalForm.show();
    };

    _refreshEditButtonListener(event) {
        window.console.log('handle category updated event in component');
        window.console.log(event);

        this.removeAllEventListeners();

        const categoryEditBtnSelector = 'EDIT_CHECKLISTCATEGORY_BTN_' + this.element.dataset.bookitCategoryId;

        this.addEventListener(this.getElement(this.selectors[categoryEditBtnSelector]), 'click', (e) => {
            e.preventDefault();
            window.console.log('EDIT CHECKLIST CATEGORY BUTTON CLICKED', e.currentTarget);
            this._handleEditChecklistCategoryButtonClick(e);
        });
    }

    _foo(event) {
        window.console.log('foo');
        window.console.log(event);
    }

}