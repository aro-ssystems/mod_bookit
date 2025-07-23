import { Reactive } from 'core/reactive';
import Mutations from 'mod_bookit/master_checklist_mutations';
import MasterChecklist from 'mod_bookit/master_checklist';

export const SELECTORS = {
            TABLE: '#mod-bookit-master-checklist-table',
            ADD_CHECKLIST_CATEGORY_BUTTON: '#add-checklist-category-button',
            ADD_CHECKLIST_ITEM_BUTTON: '#add-checklist-item-button',
            MASTER_CHECKLIST_TITLE: '#mod-bookit-master-checklist-title',
            ALL_CATEGORY_TABLE_ROWS: 'tr[data-bookit-category-id]',
            ALL_ITEM_TABLE_ROWS: 'tr[data-bookit-checklistitem-id]',
            TABLE_BODY: '#mod-bookit-master-checklist-tbody',
        };


const EVENTNAME = 'mod_bookit:master_checklist_state_event'

export const masterChecklistReactiveInstance = new Reactive({
        eventName: EVENTNAME,
        eventDispatch: dispatchMasterChecklistStateEvent,
        // target: document.getElementById(elementId),
        mutations: new Mutations(),
        name: 'Moodle Bookit Master Checklist',
        // state: {}
    });

export const init = (elementId) => {

    window.console.log('THIS IS REACTIVE');

    // const masterChecklistReactiveInstance = new Reactive({
    //     eventName: EVENTNAME,
    //     eventDispatch: dispatchMasterChecklistStateEvent,
    //     target: document.getElementById(elementId),
    //     mutations: new Mutations(),
    //     name: 'Moodle Bookit Master Checklist',
    //     // state: {}
    // });

    loadState(masterChecklistReactiveInstance);

    // return new MasterChecklist({
    //     element: document.getElementById(elementId),
    //     reactive: masterChecklistReactiveInstance,
    //     selectors: SELECTORS,
    // });
}

function dispatchMasterChecklistStateEvent(detail, target) {

    window.console.log('dispatch master checklist state event function');
    if (target === undefined) {
        target = document;
    }
    target.dispatchEvent(
        new CustomEvent(
            EVENTNAME,
            {
                bubbles: true,
                detail: detail,
            }
        )
    );
}

/**
 * Load the initial state.
 *
 * This iterates over the initial tree of category items, and captures the data required for the state from each category.
 * It also captures a count of the number of children in each list.
 *
 * @param {Reactive} reactive
 * @return {Promise<void>}
 */
const loadState = async(reactive) => {

    window.console.log('loading state');

    const tableElement = document.querySelector(SELECTORS.TABLE);


    const stateData = {
        masterchecklists: [{
            id: tableElement.dataset.masterChecklistId,
            name: tableElement.dataset.masterChecklistName
        }],
        activechecklist: {
            id: tableElement.dataset.masterChecklistId,
        },
        checklistcategories: [],
        checklistitems: [],
    };
    const checklistCategoryRows = document.querySelectorAll(SELECTORS.ALL_CATEGORY_TABLE_ROWS);
    checklistCategoryRows.forEach(categoryRow => {

        const categoryItemRows = document.querySelectorAll(`tr[data-bookit-checklistitem-category="${categoryRow.dataset.bookitCategoryId}"]`);

        const checklistItems = [];
        categoryItemRows.forEach(itemRow => {
            checklistItems.push(itemRow.dataset.bookitChecklistitemId)
        });

        stateData.checklistcategories.push({
            id: categoryRow.dataset.bookitCategoryId,
            name: categoryRow.dataset.bookitCategoryName,
            order: categoryRow.dataset.bookitCategoryOrder,
            items: checklistItems
        });
    });

    const checklistItemRows = document.querySelectorAll(SELECTORS.ALL_ITEM_TABLE_ROWS);
    checklistItemRows.forEach(itemRow => {
        stateData.checklistitems.push({
            id: itemRow.dataset.bookitChecklistitemId,
            name: itemRow.dataset.bookitChecklistitemName,
            order: itemRow.dataset.bookitChecklistitemOrder,
            category: itemRow.dataset.bookitCategoryId
        });
    });


    // const categoryLists = document.querySelectorAll(SELECTORS.CATEGORY_LIST);
    // categoryLists.forEach(categoryList => {
    //     stateData.categoryLists.push({
    //         id: categoryList.dataset.categoryid,
    //         childCount: categoryList.querySelectorAll(SELECTORS.CATEGORY_ITEM).length,
    //     });
    // });
    reactive.setInitialState(stateData);
};