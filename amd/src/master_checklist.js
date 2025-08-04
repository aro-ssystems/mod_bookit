import { BaseComponent } from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';
import * as Toast from 'core/toast';

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
            {watch: 'checklistitems:created', handler: this._handleItemCreatedEvent},
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

    _handleAddChecklistItemButtonClick(event) {
        window.console.log('handle add checklist item button click');

        window.console.log(Array.from(this.reactive.state.checklistcategories.values()));

        // TODO dont do this twice
        const modalForm = new ModalForm({
            formClass: "mod_bookit\\form\\edit_checklistitem_form",
            args: {
                masterid: 1,
                categories: Array.from(this.reactive.state.checklistcategories.values()),
            },
            modalConfig: {
                title: 'TITLE HERE', // TODO use lang string
            },

        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);
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
                title: 'Add Checklist Category', // TODO use lang string
            },
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);

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
            .then(() => {
                Toast.add('Checklist category created successfully.',
                    {type: 'success' });
            })
            .catch();
    }

    _handleItemCreatedEvent(event) {
        window.console.log('handle item created event');

        const targetElement = this.getElement(`#bookit-master-checklist-tbody-category-${event.element.category}`);

        Templates.renderForPromise('mod_bookit/bookit_checklist_item',
            {
                id: event.element.id,
                name: event.element.name,
                order: event.element.order,
                categoryid: event.element.category
            })
            .then(({html, js}) => {
                window.console.log('rendered item');
                window.console.log(html);
                // window.console.log(js);
                Templates.appendNodeContents(targetElement, html, js);
            })
            .then(() => {
                Toast.add('Checklist item created successfully.',
                    {type: 'success' });
            })
            .catch(error => {
                window.console.error('Error rendering checklist item:', error);
            });
    }

}