import { BaseComponent, DragDrop } from 'core/reactive';
import { masterChecklistReactiveInstance } from 'mod_bookit/master_checklist_reactive';
import { SELECTORS } from 'mod_bookit/master_checklist_reactive';
import ModalForm from 'core_form/modalform';
import { getString } from 'core/str';

export default class extends BaseComponent {

    create(descriptor) {

        const itemEditBtnSelector = 'EDIT_CHECKLISTITEM_BTN_' + descriptor.element.dataset.bookitChecklistitemId;
        this.selectors[itemEditBtnSelector] = `#edit-checklistitem-${descriptor.element.dataset.bookitChecklistitemId}`;
    }

    static init(target, selectors) {
        return new this({
            element: document.querySelector(target),
            reactive: masterChecklistReactiveInstance,
            selectors: selectors || SELECTORS,
        });
    }

    getWatchers() {
        return [];
    }

    stateReady(state) {

        this.dragdrop = new DragDrop(this);

        const itemEditBtnSelector = 'EDIT_CHECKLISTITEM_BTN_' + this.element.dataset.bookitChecklistitemId;

        this.addEventListener(this.getElement(this.selectors[itemEditBtnSelector]), 'click', (e) => {
            e.preventDefault();
            this._handleEditChecklistItemButtonClick(e);
        });

        this.shouldBeVisible();

    }

    destroy() {
        if (this.dragdrop !== undefined) {
            this.dragdrop.unregister();
        }
    }

    validateDropData(dropdata) {
        return true;
    }

    drop(dropdata, event) {
        switch (dropdata.type) {
            case 'item':
                this._handleItemDrop(dropdata, event);
                break;
            case 'category':
                this._handleCategoryDrop(dropdata, event);
                break;
            default:
                throw new Error(`Unknown drop type: ${dropdata.type}`);
        }
    }


    showDropZone(dropdata, event) {

        const root = document.querySelector('html');
        const primaryColor = getComputedStyle(root).getPropertyValue('--primary');

        switch (dropdata.type) {
            case 'item':
            this.element.style.boxShadow = `0px -5px 0px 0px ${primaryColor} inset`;
            this.element.style.transition = 'box-shadow 0.1s ease';
                break;
            case 'category':
                const itemParentId = parseInt(this.element.dataset.bookitChecklistitemCategoryid);
                const categoryParentElement = document.getElementById(`bookit-master-checklist-tbody-category-${itemParentId}`);
                var isActive = parseInt(categoryParentElement.dataset.bookitCategoryActive || 0);
                if (!isActive) {
                    categoryParentElement.dataset.bookitCategoryActive = 1;
                }
                const categoryLastChild = categoryParentElement.lastElementChild;
                setTimeout(() => {
                    categoryParentElement.dataset.bookitCategoryActive = 0;
                }, 5);
                categoryLastChild.style.boxShadow = `0px -5px 0px 0px ${primaryColor} inset`;
                categoryLastChild.style.transition = 'box-shadow 0.1s ease';
                break;
            default:
                throw new Error(`Unknown drop type: ${dropdata.type}`);
        }

    }

    hideDropZone(dropdata, event) {

        switch (dropdata.type) {
            case 'item':
                this.element.style.boxShadow = '';
                this.element.style.transition = '';
                break;
            case 'category':
                const itemParentId = parseInt(this.element.dataset.bookitChecklistitemCategoryid);
                const categoryParentElement = document.getElementById(`bookit-master-checklist-tbody-category-${itemParentId}`);
                const categoryLastChild = categoryParentElement.lastElementChild;
                var isActive = parseInt(categoryParentElement.dataset.bookitCategoryActive || 0);
                if (!isActive) {
                    categoryLastChild.style.boxShadow = '';
                    categoryLastChild.style.transition = '';
                }
                break;
            default:
                throw new Error(`Unknown drop type: ${dropdata.type}`);
        }

    }

    _handleItemDrop(dropdata, event) {
        dropdata.targetId = parseInt(this.element.dataset.bookitChecklistitemId);
        dropdata.targetParentId = parseInt(this.element.dataset.bookitChecklistitemCategoryid);

        this.reactive.dispatch('reOrderCategoryItems', dropdata);

        const itemObject = this.reactive.state.checklistitems.get(dropdata.id);

        const itemElement = document.getElementById(`bookit-master-checklist-item-${itemObject.id}`);

        const itemHasChangedParent = dropdata.parentId !== dropdata.targetParentId;

        if (itemHasChangedParent) {
            itemElement.dataset.bookitChecklistitemCategoryid = dropdata.targetParentId;
        }

        this.element.parentNode.insertBefore(itemElement, this.element.nextElementSibling);
    }


    _handleCategoryDrop(dropdata, event) {
        const categoryElement = document.getElementById(`bookit-master-checklist-tbody-category-${dropdata.id}`);

        dropdata.targetId = this.element.dataset.bookitChecklistitemCategoryid;
        dropdata.targetParentId = categoryElement.dataset.bookitCategoryMasterid;

        this.reactive.dispatch('reOrderCategories', dropdata);

        const tableElement = document.querySelector(this.selectors.TABLE);

        categoryElement.dataset.bookitCategoryActive = 1;

        this.hideDropZone(dropdata, event);

        tableElement.insertBefore(categoryElement, this.element.parentNode.nextElementSibling);
    }


    async _handleEditChecklistItemButtonClick(event) {
        const modalForm = new ModalForm({
            formClass: "mod_bookit\\form\\edit_checklistitem_form",
            moduleName: 'mod_bookit/modal_delete_save_cancel',
            args: {
                masterid: 1,
                itemid: event.currentTarget.value,
                categories: Array.from(this.reactive.state.checklistcategories.values()),
            },
            modalConfig: {
                title: await getString('checklistitem', 'mod_bookit'),
            },

        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (response) => {
            this.reactive.stateManager.processUpdates(response.detail);

            if (response.detail[0].action === 'delete') {
                window.console.log('item deleted - removing & unregistering');
                this.reactive.dispatch('checklistitemDeleted',
                    {
                        id: parseInt(response.detail[0].fields.id),
                        categoryid: parseInt(this.element.dataset.bookitChecklistitemCategoryid),
                    });
                this.remove();
                return;
            }

            const parentId = parseInt(this.element.dataset.bookitChecklistitemCategoryid);

            const updatedParentId = parseInt(response.detail[0].fields.categoryid);

            if (parentId !== updatedParentId) {

                const targetParentCategoryObject = this.reactive.state.checklistcategories.get(response.detail[0].fields.categoryid);

                const copiedArray = [...targetParentCategoryObject.items];

                const lastItemOfParentCategoryId = copiedArray.pop();

                const data = {
                    id: parseInt(this.element.dataset.bookitChecklistitemId),
                    type: 'item',
                    parentId: parentId,
                    targetId: lastItemOfParentCategoryId,
                    targetParentId: updatedParentId,
                }

                this.reactive.dispatch('reOrderCategoryItems', data);

                this.element.dataset.bookitChecklistitemCategoryid = data.targetParentId;

                const targetParentElement = document.getElementById(`bookit-master-checklist-tbody-category-${data.targetParentId}`);

                targetParentElement.append(this.element);
            }


        });

        modalForm.addEventListener(modalForm.events.LOADED, (response) => {
            const deleteButton = modalForm.modal.getRoot().find('button[data-action="delete"]');

            deleteButton.on('click', (e) => {

                modalForm.getFormNode().querySelector('input[name="action"]').value = 'delete';
                modalForm.submitFormAjax();

            });

            setTimeout(() => {
                this._addResetButtonsToNotificationEditors(modalForm);
            }, 800);
        });

        modalForm.show();

    }

    /**
     * Add reset buttons to notification type message editors
     * @param {ModalForm} modalForm The modal form instance
     */
    _addResetButtonsToNotificationEditors(modalForm) {
        const notificationTypes = ['before_due', 'when_due', 'overdue', 'when_done'];

        notificationTypes.forEach(type => {
            const selector = `div[id^="fitem_id_${type}_messagetext_"]`;
            const formItems = modalForm.modal.getRoot().find(selector);

            formItems.each(async (index, formItem) => {
                const editorElement = formItem.querySelector('[data-fieldtype="editor"]');

                if (editorElement) {
                    const resetButton = document.createElement('button');
                    resetButton.type = 'button';
                    resetButton.className = 'btn btn-secondary btn-sm mt-2';
                    resetButton.innerHTML = await getString('reset', 'mod_bookit');
                    resetButton.style.marginLeft = '10px';

                    resetButton.addEventListener('click', async (e) => {
                        e.preventDefault();
                        const defaultMessage = await getString(`customtemplatedefaultmessage_${type}`, 'mod_bookit');

                        const textarea = editorElement.querySelector('textarea');
                        const iframe = editorElement.querySelector('iframe');

                        if (textarea) {
                            textarea.value = defaultMessage;
                            textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            textarea.dispatchEvent(new Event('change', { bubbles: true }));

                            if (window.M && window.M.editor_atto && textarea.id) {
                                const editorInstance = window.M.editor_atto.get(textarea.id);
                                if (editorInstance) {
                                    editorInstance.updateOriginal();
                                }
                            }
                        } else if (iframe) {
                            try {
                                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                                const body = iframeDoc.querySelector('body');
                                if (body) {
                                    body.innerHTML = defaultMessage;

                                    iframe.dispatchEvent(new Event('input', { bubbles: true }));
                                    iframe.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            } catch (error) {
                                window.console.log('Could not access iframe content:', error);
                            }
                        }

                        if (window.tinymce) {
                            const editorId = textarea?.id || editorElement.querySelector('[id]')?.id;
                            if (editorId) {
                                const editor = window.tinymce.get(editorId);
                                if (editor) {
                                    editor.setContent(defaultMessage);
                                    editor.save();
                                }
                            }
                        }
                    });

                    editorElement.appendChild(resetButton);
                }
            });
        });
    }

    shouldBeVisible() {
        const activeRoomId = this.reactive.state.activeRoom.id;
        const activeRoleId = this.reactive.state.activeRole.id;
        const itemId = parseInt(this.element.dataset.bookitChecklistitemId)
        const stateItem = this.reactive.state.checklistitems.get(itemId);
        const roomIds = stateItem.roomids;
        const roleIds = stateItem.roleids;

        var isInRoom = false;

        if (activeRoomId == 0 || roomIds.includes(activeRoomId.toString())) {
            isInRoom = true;
            window.console.log('ITEM IS IN ROOM: ', itemId);
        } else {
            window.console.log('ITEM IS NOT IN ROOM: ', itemId);
        }

        var hasRole = false;

        if (activeRoleId == 0 || roleIds.includes(activeRoleId.toString())) {
            hasRole = true;
            window.console.log('ITEM HAS ROLE: ', itemId);
        } else {
            window.console.log('ITEM DOES NOT HAVE ROLE: ', itemId);
        }

        const shouldBeVisible = isInRoom && hasRole;

        if (!shouldBeVisible) {
            this.element.classList.add('d-none');
        } else {
            this.element.classList.remove('d-none');
        }

        return shouldBeVisible;
    }
}