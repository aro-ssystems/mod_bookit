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

        // Check if parentId and targetParentId are different
        if (data.parentId !== data.targetParentId) {
            // Handle moving item between different categories
            const sourceCategory = state.checklistcategories.get(data.parentId);
            const targetCategory = state.checklistcategories.get(data.targetParentId);

            // Initialize source category items array if needed
            if (!sourceCategory.items || !Array.isArray(sourceCategory.items)) {
                sourceCategory.items = [];
            }

            // Initialize target category items array if needed
            if (!targetCategory.items || !Array.isArray(targetCategory.items)) {
                targetCategory.items = [];
            }

            const idToMove = parseInt(data.id);
            const targetId = parseInt(data.targetId);

            // Remove the item from the source category
            sourceCategory.items = sourceCategory.items.filter(item => item !== idToMove);

            // Add the item to the target category if it doesn't already exist
            const targetItems = [...targetCategory.items];
            const existingTargetIndex = targetItems.indexOf(idToMove);

            // Only add if the item doesn't already exist in target array
            if (existingTargetIndex === -1) {
                const targetIndex = targetItems.indexOf(targetId);

                if (targetIndex !== -1) {
                    // Insert after the target ID
                    targetItems.splice(targetIndex + 1, 0, idToMove);
                } else {
                    // If target ID not found, add to the end
                    targetItems.push(idToMove);
                }
            }

            targetCategory.items = targetItems;

            const targetItem = state.checklistitems.get(idToMove);

            targetItem.category = targetCategory.id;

            window.console.log('New source category items:', sourceCategory.items);
            window.console.log('New target category items:', targetCategory.items);
        } else {
            // Same category - original logic for reordering within a category
            const category = state.checklistcategories.get(data.targetParentId);

            window.console.log('category items order: ', category.items);

            // Initialize items array if it doesn't exist or isn't iterable
            if (!category.items || !Array.isArray(category.items)) {
                category.items = [];
            }

            const currentItems = [...category.items];
            const idToMove = parseInt(data.id);
            const targetId = parseInt(data.targetId);

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
        }

        stateManager.setReadOnly(true);
    }

    checklistitemCreated(stateManager, data) {
        const state = stateManager.state;
        window.console.log('checklist item created');
        window.console.log(data);
        window.console.log(data[0]);
        window.console.log(data[0].fields);
        window.console.log(data[0].fields.category);
        stateManager.processUpdates(data);

        stateManager.setReadOnly(false);
        const category = state.checklistcategories.get(data[0].fields.category);
        const currentItems = [...category.items];
        currentItems.push(data[0].fields.id);
        category.items = currentItems;
        stateManager.setReadOnly(true);


    }

}