import Ajax from 'core/ajax';

export default class {
    masterChecklistStateEvent(stateManager, data) {
        const state = stateManager.state;
        // window.console.log('state master checklist state event');
        // window.console.log(data);
        // window.console.log(state);
    }

    reOrderCategoryItems(stateManager, data) {
        const state = stateManager.state;
        window.console.log('reorder category items');
        window.console.log(data);

        stateManager.setReadOnly(false);

        const category = state.checklistcategories.get(data.parentId);

        window.console.log('category items order: ', category.items);

        // Find the current positions of both IDs
        const currentItems = [...category.items];
        const idToMove = data.id;
        const targetId = data.targetId;

        const currentIndex = currentItems.indexOf(idToMove);
        const targetIndex = currentItems.indexOf(targetId);

        if (currentIndex !== -1 && targetIndex !== -1) {
            // Remove the element to move
            currentItems.splice(currentIndex, 1);

            // Find the new target index (might have shifted if the item was removed before target)
            const newTargetIndex = currentItems.indexOf(targetId);

            // Insert the element after the target
            currentItems.splice(newTargetIndex + 1, 0, idToMove);

            // Update the items array
            category.items = currentItems;

            window.console.log('new category items order: ', category.items);
        }

        stateManager.setReadOnly(true);

    }


}