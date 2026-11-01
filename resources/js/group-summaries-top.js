/*
 * Alpine component of the group summary row drawn under a group header (SummaryGroup::position()).
 *
 * The row is plain inline markup inside the header's description, so it cannot be a real <tr>. This
 * lines its cells up under the table's own header cells (found by their `fi-ta-header-cell-*` class).
 * Without a <table> (grid layout), on a narrow screen, or when a header cell is missing, the cells
 * simply wrap as a strip. A column hidden responsively (`visibleFrom()`) is hidden in the row too.
 *
 * One ResizeObserver and one MutationObserver per TABLE align every group in it: all rectangles are
 * read first and all styles written after, so many groups cost one layout, not one each. Livewire's
 * morph strips the inline styles set here; the mutation observer notices and puts them back.
 */
const tables = new WeakMap()

function align(table) {
    const state = tables.get(table)

    if (!state) {
        return
    }

    state.mutations.disconnect()
    state.groups.forEach((group) => group.isConnected || state.groups.delete(group))

    const wide = window.matchMedia('(min-width: 640px)').matches

    // Read phase.
    const plans = Array.from(state.groups).map((group) => {
        const cells = Array.from(group.querySelectorAll('[data-gs-th]'))
        const heads = cells.map((cell) => table.querySelector(`thead th.${CSS.escape(cell.dataset.gsTh)}`))

        if (!wide || !cells.length || heads.some((head) => !head)) {
            return { group, cells, fallback: true }
        }

        const visible = heads.map((head) => head.getClientRects().length > 0)
        const first = heads.find((head, index) => visible[index])

        if (!first) {
            return { group, cells, fallback: true }
        }

        const rtl = getComputedStyle(group).direction === 'rtl'
        const own = group.getBoundingClientRect()
        const margin = parseFloat(group.style.marginInlineStart) || 0
        const firstRect = first.getBoundingClientRect()

        return {
            group,
            cells,
            fallback: false,
            offset: rtl ? own.right + margin - firstRect.right : firstRect.left - (own.left - margin),
            columns: heads.map((head, index) => {
                if (!visible[index]) {
                    return null
                }

                const style = getComputedStyle(head)

                return { width: head.getBoundingClientRect().width, start: style.paddingInlineStart, end: style.paddingInlineEnd }
            }),
        }
    })

    // Write phase.
    plans.forEach((plan) => {
        plan.cells.forEach((cell) => cell.removeAttribute('style'))
        plan.group.style.marginInlineStart = ''
        plan.group.classList.toggle('fi-gs-aligned', !plan.fallback)
        plan.group._gsAligned = !plan.fallback

        if (plan.fallback) {
            return
        }

        plan.group.style.marginInlineStart = `${plan.offset}px`

        plan.columns.forEach((column, index) => {
            if (column === null) {
                plan.cells[index].style.display = 'none'

                return
            }

            plan.cells[index].style.width = `${column.width}px`
            plan.cells[index].style.paddingInlineStart = column.start
            plan.cells[index].style.paddingInlineEnd = column.end
        })
    })

    state.mutations.takeRecords()
    state.mutations.observe(table, { attributes: true, childList: true, subtree: true })
}

function register(table, group) {
    let state = tables.get(table)

    if (!state) {
        let frame = null
        const schedule = () => {
            frame ??= requestAnimationFrame(() => {
                frame = null
                align(table)
            })
        }

        state = {
            groups: new Set(),
            resize: new ResizeObserver(schedule),
            // Morph removed the styles of a group that was aligned, or brought a new group in.
            mutations: new MutationObserver(() => {
                const stale = Array.from(state.groups).some((el) => (el._gsAligned && !el.classList.contains('fi-gs-aligned')) || el._gsAligned === undefined)

                if (stale) {
                    schedule()
                }
            }),
            schedule,
        }
        state.resize.observe(table)
        state.mutations.observe(table, { attributes: true, childList: true, subtree: true })
        tables.set(table, state)
    }

    state.groups.add(group)
    state.schedule()
}

function unregister(table, group) {
    const state = tables.get(table)

    if (!state) {
        return
    }

    state.groups.delete(group)

    if (state.groups.size === 0) {
        state.resize.disconnect()
        state.mutations.disconnect()
        tables.delete(table)
    }
}

export default function groupSummariesTop() {
    return {
        table: null,

        init() {
            this.table = this.$el.closest('table')

            if (this.table) {
                register(this.table, this.$el)
            }
        },

        destroy() {
            if (this.table) {
                unregister(this.table, this.$el)
            }
        },
    }
}
