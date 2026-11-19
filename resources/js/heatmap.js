/**
 * Activity heatmap. Native Date is the single source of truth for weekday and
 * month placement: rows are Monday..Sunday, columns are consecutive weeks and
 * a month label is attached to the first column that contains that month.
 */
const heatmapPayload = document.getElementById('dotahub-heatmap-data');
const heatmapRoot = document.getElementById('activity-heatmap');

if (heatmapPayload && heatmapRoot) {
    const heatmap = JSON.parse(heatmapPayload.textContent);
    const weekdaysNode = document.getElementById('heatmap-weekdays');
    const monthsNode = document.getElementById('heatmap-months');
    const totalNode = document.getElementById('activity-total');
    const weekdayLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    const monthLabels = ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сент', 'Окт', 'Ноя', 'Дек'];
    const tones = {
        future: 'bg-transparent text-transparent',
        empty: 'bg-white/[0.04] ring-1 ring-inset ring-white/[0.03] text-transparent',
        win: 'bg-emerald-500 ring-1 ring-inset ring-emerald-300/40 text-emerald-950',
        loss: 'bg-rose-500 ring-1 ring-inset ring-rose-300/40 text-rose-950',
        loss_majority: 'bg-orange-500 ring-1 ring-inset ring-orange-300/40 text-orange-950',
        win_majority: 'bg-yellow-400 ring-1 ring-inset ring-yellow-200/50 text-yellow-950',
        mixed: 'bg-amber-400 ring-1 ring-inset ring-amber-200/50 text-amber-950',
    };
    const DAY_MS = 86400000;

    const parseDay = (value) => {
        const [year, month, day] = String(value).split('-').map(Number);
        return new Date(Date.UTC(year, month - 1, day));
    };
    const weekdayOf = (date) => (date.getUTCDay() + 6) % 7;
    const days = (Array.isArray(heatmap.weeks) ? heatmap.weeks.flat() : [])
        .filter((day) => day && day.date)
        .sort((left, right) => left.date.localeCompare(right.date));

    if (days.length > 0) {
        const firstDate = parseDay(days[0].date);
        const gridStart = new Date(firstDate);
        gridStart.setUTCDate(firstDate.getUTCDate() - weekdayOf(firstDate));

        const lastDate = parseDay(days[days.length - 1].date);
        const columnCount = Math.floor(Math.round((lastDate - gridStart) / DAY_MS) / 7) + 1;
        const columns = Array.from({ length: columnCount }, () => Array(7).fill(null));

        days.forEach((day) => {
            const date = parseDay(day.date);
            const offset = Math.round((date - gridStart) / DAY_MS);
            const column = Math.floor(offset / 7);
            const row = weekdayOf(date);

            if (column >= 0 && column < columnCount && row >= 0 && row < 7) {
                columns[column][row] = day;
            }
        });

        if (weekdaysNode) {
            weekdaysNode.replaceChildren(...weekdayLabels.map((label) => {
                const span = document.createElement('span');
                span.className = 'flex items-center justify-end';
                span.textContent = label;
                return span;
            }));
        }

        if (monthsNode) {
            monthsNode.style.gridTemplateColumns = `repeat(${columnCount}, minmax(0, 1fr))`;
            let previousMonth = -1;

            columns.forEach((column, index) => {
                const date = new Date(gridStart);
                date.setUTCDate(gridStart.getUTCDate() + index * 7);
                const month = date.getUTCMonth();

                if (month === previousMonth) return;
                previousMonth = month;

                const label = document.createElement('span');
                label.className = 'whitespace-nowrap';
                label.style.gridColumn = String(index + 1);
                label.textContent = monthLabels[month];
                monthsNode.appendChild(label);
            });
        }

        const cells = [];

        columns.forEach((column) => {
            column.forEach((day, row) => {
                if (! day) {
                    const empty = document.createElement('span');
                    empty.className = 'aspect-square rounded-[2px] bg-white/[0.04] ring-1 ring-inset ring-white/[0.03]';
                    empty.setAttribute('aria-hidden', 'true');
                    cells.push(empty);
                    return;
                }

                const count = Number(day.count) || 0;
                const title = `${day.label}: ${count} игр, ${Number(day.wins) || 0} побед, ${Number(day.losses) || 0} поражений`;
                const cell = document.createElement('div');
                cell.className = `flex aspect-square items-center justify-center rounded-[2px] text-[11px] font-black leading-none transition hover:scale-125 hover:ring-white/30 ${tones[day.level] || tones.empty}`;
                cell.dataset.date = day.date;
                cell.dataset.count = String(count);
                cell.dataset.weekday = weekdayLabels[row];
                cell.setAttribute('title', title);
                cell.setAttribute('aria-label', title);
                cell.textContent = count > 0 ? String(count) : '';
                cells.push(cell);
            });
        });

        heatmapRoot.style.gridTemplateColumns = `repeat(${columnCount}, minmax(0, 1fr))`;
        heatmapRoot.style.gridAutoFlow = 'column';
        heatmapRoot.style.gridTemplateRows = 'repeat(7, minmax(0, 1fr))';
        heatmapRoot.replaceChildren(...cells);

        if (totalNode) {
            totalNode.textContent = `${days.reduce((sum, day) => sum + (Number(day.count) || 0), 0).toLocaleString('ru-RU')} игр`;
        }
    }
}
