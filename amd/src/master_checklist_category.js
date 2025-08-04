import { BaseComponent } from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';
import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';

export default class extends BaseComponent {

    create(descriptor) {

        window.console.log('create category component: ' + descriptor.element.dataset.bookitCategoryName);

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

    }

    _handleStateEvent(event) {
        // window.console.log('handle state event');
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