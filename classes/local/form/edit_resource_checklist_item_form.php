<?php
/**
 * Form for editing a resource checklist item.
 *
 * @package     mod_bookit
 * @copyright   2026 ssystems GmbH <oss@ssystems.de>
 * @author      Andreas Rosenthal
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookit\local\form;

use core_form\dynamic_form;
use mod_bookit\local\entity\bookit_notification_type;
use mod_bookit\local\entity\bookit_notification_slot;
use mod_bookit\local\entity\bookit_resource_checklist;
use mod_bookit\local\manager\resource_checklist_manager;

/**
 * Dynamic form for editing a resource checklist item.
 */
class edit_resource_checklist_item_form extends dynamic_form {
    use notification_slots_form_trait;

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Resource name as static display.
        $mform->addElement('static', 'resourcename', get_string('name', 'core'));

        // Due date (absolute timestamp or offset).
        $mform->addElement(
            'date_time_selector',
            'duedate',
            get_string('resources:duedate', 'mod_bookit'),
            ['optional' => true]
        );

        // Due date type.
        $duedatetypeoptions = [
            '' => get_string('choosedots', 'core'),
            'before_event' => get_string('duedate_before_event', 'mod_bookit'),
            'after_event' => get_string('duedate_after_event', 'mod_bookit'),
            'fixed_date' => get_string('duedate_fixed_date', 'mod_bookit'),
        ];
        $mform->addElement('select', 'duedatetype', get_string('resources:duedate_type', 'mod_bookit'), $duedatetypeoptions);
        $mform->setType('duedatetype', PARAM_TEXT);

        // Active flag.
        $mform->addElement('advcheckbox', 'active', get_string('active', 'core'));

        // Sort order (hidden).
        $mform->addElement('hidden', 'sortorder');
        $mform->setType('sortorder', PARAM_INT);

        // Notification slots section.
        $allroles = get_all_roles();
        $this->definition_notification_section($allroles);
    }

    /**
     * Get default message prefix for resource checklist.
     *
     * @return string
     */
    protected function get_notification_default_message_prefix(): string {
        return 'resources:customtemplatedefaultmessage';
    }

    /**
     * Resource checklist slots are not linked to checklist items.
     *
     * @param int $itemid Checklist item ID (ignored)
     * @return null
     */
    protected function get_checklistitem_id_for_new_slot(int $itemid): ?int {
        return null;
    }

    /**
     * No inactive slot reuse for resource checklist.
     *
     * @param int $itemid Checklist item ID
     * @param string $type Notification type
     * @return null
     */
    protected function find_existing_inactive_slot(int $itemid, string $type): ?bookit_notification_slot {
        return null;
    }

    /**
     * After saving a notification slot, update the FK on the checklist item.
     *
     * @param bookit_notification_type $case Notification type
     * @param int $slotid Saved slot ID
     * @param int $itemid Checklist item ID
     */
    protected function on_notification_slot_saved(bookit_notification_type $case, int $slotid, int $itemid): void {
        $item = resource_checklist_manager::get_checklist_item($itemid);
        if ($item === null) {
            return;
        }
        switch ($case->value) {
            case 'before_due':
                $item->set_beforedueid($slotid);
                break;
            case 'when_due':
                $item->set_whendueid($slotid);
                break;
            case 'overdue':
                $item->set_overdueid($slotid);
                break;
            case 'when_done':
                $item->set_whendoneid($slotid);
                break;
        }
        global $USER;
        resource_checklist_manager::save_checklist_item($item, (int)$USER->id);
    }

    /**
     * Check access for dynamic submission.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('mod/bookit:managebasics', \context_system::instance());
    }

    /**
     * Get the context for this form.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Get the page URL for dynamic submission.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/bookit/admin/resource_checklist.php');
    }

    /**
     * Set data for the form from the checklist item.
     */
    public function set_data_for_dynamic_submission(): void {
        $id = $this->optional_param('id', null, PARAM_INT);

        $formdata = new \stdClass();
        $formdata->id = $id;
        $itemslots = [];

        if (!empty($id)) {
            $item = resource_checklist_manager::get_checklist_item($id);
            if ($item !== null) {
                // Load joined resource data for the name.
                $items = resource_checklist_manager::get_all_checklist_items_with_rooms();
                $joined = $items[$id] ?? null;
                $formdata->resourcename = $joined ? format_string($joined->name) : '';

                $formdata->duedate = $item->get_duedate() ?? 0;
                $formdata->duedatetype = $item->get_duedatetype() ?? '';
                $formdata->sortorder = $item->get_sortorder();
                $formdata->active = (int)$item->is_active();

                // Load existing notification slots by FK IDs.
                $slotids = array_filter([
                    $item->get_beforedueid(),
                    $item->get_whendueid(),
                    $item->get_overdueid(),
                    $item->get_whendoneid(),
                ]);
                foreach ($slotids as $slotid) {
                    try {
                        $slot = bookit_notification_slot::from_database($slotid);
                        $itemslots[] = $slot;
                    } catch (\dml_exception $e) {
                        // Slot no longer exists, skip.
                        continue;
                    }
                }
            }
        }

        $slotdata = $this->get_notification_slot_form_data($itemslots);
        $this->populate_notification_form_data($formdata, $slotdata);

        $this->set_data($formdata);
    }

    /**
     * Process the dynamic form submission.
     *
     * @return array processUpdates-compatible array
     */
    public function process_dynamic_submission(): array {
        global $USER;

        $data = $this->get_data();
        $id = (int)($data->id ?? 0);

        $item = resource_checklist_manager::get_checklist_item($id);
        if ($item === null) {
            return [];
        }

        // Update fields.
        $item->set_duedate(!empty($data->duedate) ? (int)$data->duedate : null);
        $item->set_duedatetype(!empty($data->duedatetype) ? $data->duedatetype : null);
        $item->set_active((bool)($data->active ?? true));
        $item->set_sortorder((int)($data->sortorder ?? $item->get_sortorder()));

        resource_checklist_manager::save_checklist_item($item, (int)$USER->id);

        // Save notification slots — on_notification_slot_saved updates FKs.
        $this->save_notification_slots((array)$data, $id);

        // Reload to get updated FK ids.
        $item = resource_checklist_manager::get_checklist_item($id);

        // Load resource name via join.
        $joined = resource_checklist_manager::get_all_checklist_items_with_rooms()[$id] ?? null;

        return [
            [
                'action' => 'put',
                'name' => 'checklistitems',
                'fields' => [
                    'id' => $item->get_id(),
                    'resourceid' => $item->get_resourceid(),
                    'name' => $joined ? format_string($joined->name) : '',
                    'description' => $joined ? format_text($joined->description ?? '', FORMAT_HTML) : '',
                    'categoryid' => $joined->categoryid ?? 0,
                    'sortorder' => $item->get_sortorder(),
                    'active' => (int)$item->is_active(),
                    'duedate' => $item->get_duedate(),
                    'duedatetype' => $item->get_duedatetype(),
                    'beforedueid' => $item->get_beforedueid(),
                    'whendueid' => $item->get_whendueid(),
                    'overdueid' => $item->get_overdueid(),
                    'whendoneid' => $item->get_whendoneid(),
                ],
            ],
        ];
    }
}
