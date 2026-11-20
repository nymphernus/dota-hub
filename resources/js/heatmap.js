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
    const weekdayLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    const monthLabels = ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сент', 'Окт', 'Ноя', 'Дек'];
    const tones = {
        future: 'bg-transparent text-transparent',
        empty: 'bg-ink-900/40 text-transparent',
        single: 'bg-gray-500 text-white',
        win: 'bg-green-500 text-green-950',
        loss: 'bg-red-500 text-red-950',
        more_wins: 'bg-yellow-500 text-yellow-950',
        more_losses: 'bg-orange-500 text-orange-950',
    };
    const DAY_MS = 86400000;

    const parseDay = (value) => {
        const [year, month, day] = String(value).split('-').map(Number);
        return new Date(Date.UTC(year, month - 1, day));
    };
    const weekdayOf = (date) => (date.getUTCDay() + 6) % 7;
    const days = (Array.isArray(heatmap.weeks) ? heatmap.weeks.flat() : (heatmap.days || []))
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
            monthsNode.replaceChildren();
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
            column.forEach((day) => {
                if (!day) {
                    const empty = document.createElement('span');
                    empty.className = 'aspect-square rounded-[2px] bg-ink-900/40';
                    empty.setAttribute('aria-hidden', 'true');
                    cells.push(empty);
                    return;
                }

                const count = Number(day.count) || 0;
                const title = count > 0
                    ? `${day.label}: ${count} ${gameLabel(count)} (${Number(day.wins) || 0}П / ${Number(day.losses) || 0}П)`
                    : `${day.label}: ${day.level === 'future' ? 'будущий день' : 'нет игр'}`;
                const matchUrlTemplate = heatmapRoot.dataset.matchUrlTemplate;
                const cell = matchUrlTemplate && count > 0
                    ? document.createElement('a')
                    : document.createElement('div');

                if (matchUrlTemplate && count > 0) {
                    cell.href = matchUrlTemplate.replace('__DATE__', day.date);
                }

                cell.className = `flex aspect-square items-center justify-center rounded-[2px] text-[11px] font-semibold leading-none transition hover:scale-125 ${tones[day.level] || tones.empty}`;
                cell.dataset.date = day.date;
                cell.dataset.count = String(count);
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
    }
}

function gameLabel(count) {
    if (count % 10 === 1 && count % 100 !== 11) return 'игра';
    if ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100)) return 'игры';
    return 'игр';
}
