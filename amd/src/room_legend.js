define(['jquery'], function($) {
    /**
     * Toggle shortname buttons vs overflow control based on horizontal fit.
     *
     * @param {HTMLElement} root Legend root element
     */
    function measure(root) {
        const buttons = root.querySelector('[data-region="room-legend-buttons"]');
        const overflow = root.querySelector('[data-region="room-legend-overflow"]');
        if (!buttons || !overflow) {
            return;
        }

        // Measure with buttons visible so scrollWidth reflects natural strip width.
        buttons.classList.remove('d-none');
        buttons.hidden = false;
        overflow.classList.add('d-none');
        overflow.hidden = true;
        // Force layout after un-hiding (display:none → visible).
        void buttons.offsetWidth;

        const clientWidth = buttons.clientWidth;
        const scrollWidth = buttons.scrollWidth;
        // Skip toggle when not laid out yet (zero width) to avoid false overflow.
        const doesOverflow = clientWidth > 0 && scrollWidth > clientWidth;
        if (doesOverflow) {
            buttons.classList.add('d-none');
            buttons.hidden = true;
            overflow.classList.remove('d-none');
            overflow.hidden = false;
        }
    }

    /**
     * Initialise Bootstrap popovers on legend controls (BS4/BS5 attribute variants).
     *
     * @param {HTMLElement} root Legend root element
     */
    function initPopovers(root) {
        const nodes = $(root).find('[data-toggle="popover"], [data-bs-toggle="popover"]');
        if (!nodes.length) {
            return;
        }
        if (typeof nodes.popover === 'function') {
            nodes.popover({
                html: true,
                container: 'body',
                trigger: 'hover focus'
            });
        }
    }

    return {
        /**
         * Wire room legend overflow detection and popovers.
         *
         * @param {String} [selector] Optional root selector
         */
        init: function(selector) {
            const root = document.querySelector(selector || '[data-region="room-legend"]');
            if (!root) {
                return;
            }

            initPopovers(root);
            measure(root);

            if (typeof ResizeObserver !== 'undefined') {
                const observer = new ResizeObserver(function() {
                    measure(root);
                });
                observer.observe(root);
            } else {
                window.addEventListener('resize', function() {
                    measure(root);
                });
            }
        }
    };
});
