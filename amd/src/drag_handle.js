import {BaseComponent, DragDrop} from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';

export default class extends BaseComponent {

    static init(target, selectors) {
        return new this({
            element: document.querySelector(target),
            reactive: masterChecklistReactiveInstance,
            selectors: selectors || SELECTORS,
        });
    }

    stateReady() {
        this.relativeDrag = true;

        // this.dragdrop = new DragDrop(this);

        const itemSelector = `tr[data-bookit-drag-handle-id="${this.element.dataset.bookitDragHandleId}"]`;
        window.console.log('itemSelector: ', itemSelector);

        const itemRow = document.querySelector(itemSelector);

        window.console.log('DragDrop initialized for item: ', itemRow);

        window.console.log(this.element)

        this.fullregion = itemRow;

        this.dragdrop = new DragDrop(this);
    }

    destroy() {
        if (this.dragdrop !== undefined) {
            this.dragdrop.unregister();
        }
    }

    getDraggableData() {
        return {
            id: this.element.dataset.bookitDragHandleId,
            type: this.element.dataset.bookitDragHandleType,
            parentId: this.fullregion.dataset.bookitDragHandleParentId,
        };
    }

    dragStart(dragData, event) {
        // This method is called when the drag operation starts.
        window.console.log('Drag started', dragData);
    }

    dragEnd(dragData, event) {
        // This method is called when the drag operation ends.
        window.console.log('Drag ended', dragData);
    }

    // validateDropData(dropdata) {

    //     return true;
    // }

    // drop(dropdata, event) {
    //     window.console.log('whoops you dropped this on a handle', dropdata);
    // }
}