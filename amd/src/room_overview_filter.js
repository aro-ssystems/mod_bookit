define([], function() {
    return {
        /**
         * Client-side multi-select filter for room overview rows.
         * Empty selection ⇒ show all rows; otherwise hide non-selected room ids.
         */
        init: function() {
            const select = document.querySelector('[data-region="room-overview-filter"]');
            const rows = document.querySelectorAll('[data-region="room-overview-row"]');
            if (!select || !rows.length) {
                return;
            }

            const applyFilter = function() {
                const selected = Array.prototype.map.call(select.selectedOptions, function(option) {
                    return option.value;
                });
                rows.forEach(function(row) {
                    const roomId = row.getAttribute('data-room-id');
                    row.hidden = selected.length > 0 && selected.indexOf(roomId) === -1;
                });
            };

            select.addEventListener('change', applyFilter);
        }
    };
});
