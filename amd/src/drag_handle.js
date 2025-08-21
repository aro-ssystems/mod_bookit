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

        const dragType = this.element.dataset.bookitDragHandleType;
        const dragId = this.element.dataset.bookitDragHandleId;

        // TODO fullregion needs to differentiate between type item & category

        var fullRegionSelector = '';

        switch (dragType) {
            case 'item':
                fullRegionSelector = `tr[data-bookit-drag-handle-${dragType}-id="${dragId}"]`;
                break;
            case 'category':
                fullRegionSelector = `tbody[data-bookit-tbody-category-id="${dragId}"]`;
                break;
            default:
                throw new Error(`Unknown drag handle type: ${dragType}`);
        }

        window.console.log('fullRegionSelector: ', fullRegionSelector);

        const fullRegionElement = document.querySelector(fullRegionSelector);

        window.console.log('DragDrop initialized for item: ', fullRegionElement);

        window.console.log(this.element)

        this.fullregion = fullRegionElement;

        this.dragdrop = new DragDrop(this);
    }

    destroy() {
        if (this.dragdrop !== undefined) {
            this.dragdrop.unregister();
        }
    }

    getDraggableData() {
        const dragType = this.element.dataset.bookitDragHandleType;
        var parentId = undefined;

        switch (dragType) {
            case 'item':
                parentId = this.fullregion.dataset.bookitChecklistitemCategoryid;
                break;
            case 'category':
                parentId = this.fullregion.dataset.bookitCategoryMasterid;
                break;
            default:
                throw new Error(`Unknown drag handle type: ${dragType}`);
        }

        return {
            id: this.element.dataset.bookitDragHandleId,
            type: this.element.dataset.bookitDragHandleType,
            parentId: parentId,
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