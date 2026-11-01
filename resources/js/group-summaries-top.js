/*
 * Alpine component of the group summary row drawn under a group header (SummaryGroup::position()).
 *
 * The row is plain inline markup inside the header's description, so it cannot be a real <tr>. This
 * lines its cells up under the table's own header cells (found by their `fi-ta-header-cell-*` class).
 * Without a <table> (grid / stacked layout), on a narrow screen, or when a header cell is missing, the
 * cells simply wrap as a strip. Livewire's morph strips the inline styles set here, so a
 * MutationObserver puts them back.
 */
export default function groupSummariesTop() {
    return {
        resizeObserver: null,

        mutationObserver: null,

        init() {
            const table = this.$el.closest('table')

            if (!table) {
                return
            }

            this.$nextTick(() => this.align(table))

            this.resizeObserver = new ResizeObserver(() => this.align(table))
            this.resizeObserver.observe(table)

            this.mutationObserver = new MutationObserver(() => {
                const cell = this.$el.querySelector('[data-gs-th]')

                if (cell && !cell.style.width) {
                    this.align(table)
                }
            })
            this.mutationObserver.observe(this.$el.parentElement ?? this.$el, { attributes: true, childList: true, subtree: true })
        },

        destroy() {
            this.resizeObserver?.disconnect()
            this.mutationObserver?.disconnect()
        },

        align(table) {
            const cells = Array.from(this.$el.querySelectorAll('[data-gs-th]'))

            this.mutationObserver?.disconnect()

            this.$el.classList.remove('fi-gs-aligned')
            this.$el.style.marginInlineStart = ''
            cells.forEach((cell) => cell.removeAttribute('style'))

            const heads = cells.map((cell) => table.querySelector(`thead th.${CSS.escape(cell.dataset.gsTh)}`))

            if (!cells.length || heads.some((head) => !head) || !window.matchMedia('(min-width: 640px)').matches) {
                this.observeAgain()

                return
            }

            const rtl = getComputedStyle(this.$el).direction === 'rtl'
            const own = this.$el.getBoundingClientRect()
            const first = heads[0].getBoundingClientRect()

            this.$el.style.marginInlineStart = `${rtl ? own.right - first.right : first.left - own.left}px`
            this.$el.classList.add('fi-gs-aligned')

            heads.forEach((head, index) => {
                const style = getComputedStyle(head)

                cells[index].style.width = `${head.getBoundingClientRect().width}px`
                cells[index].style.paddingInlineStart = style.paddingInlineStart
                cells[index].style.paddingInlineEnd = style.paddingInlineEnd
            })

            this.observeAgain()
        },

        observeAgain() {
            this.mutationObserver?.observe(this.$el.parentElement ?? this.$el, { attributes: true, childList: true, subtree: true })
        },
    }
}
