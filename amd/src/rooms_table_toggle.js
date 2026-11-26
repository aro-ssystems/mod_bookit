define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {
    /**
     * Update row muted styling and switch label after a successful toggle.
     *
     * @param {HTMLElement} checkbox
     * @param {Boolean} active
     * @param {String} activeLabel
     * @param {String} inactiveLabel
     */
    function applyUiState(checkbox, active, activeLabel, inactiveLabel) {
        const row = checkbox.closest('tr');
        if (row) {
            row.classList.toggle('text-muted', !active);
        }
        const label = document.querySelector('label[for="' + checkbox.id + '"]');
        if (label) {
            label.textContent = active ? activeLabel : inactiveLabel;
        }
    }

    /**
     * Persist a room active toggle via web service.
     *
     * @param {HTMLElement} checkbox
     * @param {String} activeLabel
     * @param {String} inactiveLabel
     * @return {Promise}
     */
    function handleToggle(checkbox, activeLabel, inactiveLabel) {
        const roomId = parseInt(checkbox.getAttribute('data-room-id'), 10);
        const newActive = checkbox.checked;

        return Ajax.call([{
            methodname: 'mod_bookit_toggle_room_active',
            args: {
                roomid: roomId,
                active: newActive
            }
        }])[0].then(function(response) {
            applyUiState(checkbox, !!response.active, activeLabel, inactiveLabel);
            return response;
        }).catch(function(error) {
            checkbox.checked = !newActive;
            Notification.exception(error);
            return null;
        });
    }

    return {
        /**
         * Wire rooms table active toggles to mod_bookit_toggle_room_active.
         *
         * @return {Promise}
         */
        init: function() {
            return Promise.all([
                Str.get_string('active', 'core'),
                Str.get_string('inactive', 'core')
            ]).then(function(strings) {
                const activeLabel = strings[0];
                const inactiveLabel = strings[1];

                $(document).on('change', '[data-action="toggle-room-active"]', function(e) {
                    handleToggle(e.target, activeLabel, inactiveLabel);
                });

                return true;
            }).catch(Notification.exception);
        }
    };
});
