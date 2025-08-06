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

    }


}