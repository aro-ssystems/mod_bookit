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

        const category = state.checklistcategories.get(data.targetParentId);

        window.console.log('category items order: ', category.items);

        // Initialize items array if it doesn't exist or isn't iterable
        if (!category.items || !Array.isArray(category.items)) {
            category.items = [];
        }

        const currentItems = [...category.items];
        const idToMove = data.id;
        const targetId = data.targetId;

        const currentIndex = currentItems.indexOf(idToMove);

        // If the ID to move is not found in the array, add it at the end
        if (currentIndex === -1) {
            currentItems.push(idToMove);
        }

        // After possible addition, check if target exists
        const targetIndex = currentItems.indexOf(targetId);

        if (targetIndex !== -1 && currentIndex !== -1) {
            // Remove the element to move
            currentItems.splice(currentIndex, 1);

            // Find the new target index (might have shifted if the item was removed before target)
            const newTargetIndex = currentItems.indexOf(targetId);

            // Insert the element after the target
            currentItems.splice(newTargetIndex + 1, 0, idToMove);
        } else if (currentIndex !== -1) {
            // targetId doesn't exist but idToMove does - keep the current position
            // No changes needed
        }

        // Update the items array
        category.items = currentItems;

        window.console.log('new category items order: ', category.items);

        stateManager.setReadOnly(true);

    }


}