<div
    class="fi-resource-relation-manager"
    x-data="{
        highlightedRowObserver: null,
        highlightedRowInterval: null,
        highlightedRowAttempts: 0,
        scrollToHighlightedRow() {
            const row = this.$el.querySelector('.idrx-highlighted-table-row');

            if (! row) {
                return false;
            }

            window.setTimeout(() => {
                const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                row.scrollIntoView({
                    behavior: prefersReducedMotion ? 'auto' : 'smooth',
                    block: 'center',
                    inline: 'nearest',
                });
            }, 100);

            return true;
        },
        watchHighlightedRow() {
            if (this.scrollToHighlightedRow()) {
                return;
            }

            this.highlightedRowInterval = window.setInterval(() => {
                this.highlightedRowAttempts++;

                if (this.scrollToHighlightedRow() || this.highlightedRowAttempts >= 30) {
                    window.clearInterval(this.highlightedRowInterval);
                }
            }, 150);

            this.highlightedRowObserver = new MutationObserver(() => {
                if (! this.scrollToHighlightedRow()) {
                    return;
                }

                window.clearInterval(this.highlightedRowInterval);
                this.highlightedRowObserver.disconnect();
            });

            this.highlightedRowObserver.observe(this.$el, {
                childList: true,
                subtree: true,
            });

            window.setTimeout(() => {
                this.highlightedRowObserver?.disconnect();
                window.clearInterval(this.highlightedRowInterval);
            }, 6000);
        },
    }"
    x-init="$nextTick(() => watchHighlightedRow())"
>
    {{ $this->content }}

    <x-filament-panels::unsaved-action-changes-alert />
</div>
