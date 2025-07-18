import { BaseComponent } from 'core/reactive';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';

export default class extends BaseComponent {

    create(descriptor) {

        window.console.log('create component: ' + descriptor.reactive.name);

    }

    getWatchers() {
        window.console.log('GET WATCHERS');
        return [
            {watch: 'state:updated', handler: this._handleStateEvent},
            {watch: 'checklistcategories:created', handler: this._handleCategoryCreatedEvent},
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
        window.console.log('handle state event');
    }

    _handleAddChecklistItemButtonClick(event) {
        window.console.log('handle add checklist item button click');

        // TODO dont do this twice
        const modalForm = new ModalForm({
            formClass: "mod_bookit\\form\\edit_checklistitem_form",
            args: {
                masterid: 1
            },
            modalConfig: {
                title: 'TITLE HERE',
            },
            // moduleName: 'mod_bookit/modal_checklistitem_form',
        });

        modalForm.show();
    }

    _handleAddChecklistCategoryButtonClick(event) {
        window.console.log('handle add checklist category button click');
        const modalForm = new ModalForm({
            formClass: "mod_bookit\\form\\edit_checklist_category_form",
            args: {
                masterid: 1
            },
            modalConfig: {
                title: 'Add Checklist Category',
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);


            // Templates.renderForPromise('mod_bookit/bookit_checklist_category', {id: 34, name: 'New Category', order: 43})
            // .then(({html, js}) => {
            //     Templates.appendNodeContents(this.getElement(this.selectors.TABLE_BODY), html, js);
            // })
            // .catch();
        });

        modalForm.show();
    }

    _handleCategoryCreatedEvent(event) {
        window.console.log('handle category created event');
        window.console.log(event.element);

        // TODO cats and/or items should be own components

        Templates.renderForPromise('mod_bookit/bookit_checklist_category',
            {
                id: event.element.id,
                name: event.element.name,
                order: event.element.order
            })
            .then(({html, js}) => {
                Templates.appendNodeContents(this.getElement(this.selectors.TABLE_BODY), html, js);
            })
            .catch();
    }

}