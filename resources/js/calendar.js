import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import luxonPlugin from '@fullcalendar/luxon3';
import { DateTime } from 'luxon';

export function mountCalendar(element, { loadEvents, onDateClick, onEventClick }) {
    const status = document.getElementById('calendar-status');
    const retry = document.getElementById('calendar-retry');
    const calendar = new Calendar(element, {
        plugins: [dayGridPlugin, interactionPlugin, luxonPlugin],
        initialView: 'dayGridMonth', timeZone: 'Asia/Manila', height: 'auto', fixedWeekCount: false,
        dayMaxEvents: 3, eventDisplay: 'block', displayEventTime: false,
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
        buttonIcons: false, buttonText: { prev: 'Previous month', next: 'Next month', today: 'Today' },
        events: async (range, success, failure) => {
            retry.hidden = true;
            status.textContent = 'Loading events…';
            try {
                const from = DateTime.fromISO(range.startStr, {zone: 'Asia/Manila'}).toISODate();
                const to = DateTime.fromISO(range.endStr, {zone: 'Asia/Manila'}).minus({ days: 1 }).toISODate();
                const events = await loadEvents({ from, to });
                success(events);
                status.textContent = events.length ? `${events.length} events in this calendar view · Asia/Manila` : 'No events in this calendar view · Asia/Manila';
            } catch (error) {
                failure(error);
                status.textContent = error.message;
                retry.hidden = false;
            }
        },
        dateClick: info => onDateClick(info.dateStr),
        eventClick: info => onEventClick(info.event.id, info.event.extendedProps.can_edit),
        eventClassNames: info => ['calendar-event', info.event.extendedProps.calendar === 'Office' ? 'calendar-event-office' : ''],
        datesSet: () => {
            for (const [selector, icon] of [['.fc-prev-button', 'chevron-left'], ['.fc-next-button', 'chevron-right']]) {
                const button = element.querySelector(selector);
                if (!button) continue;
                button.setAttribute('aria-label', selector.includes('prev') ? 'Previous month' : 'Next month');
                const glyph = document.createElement('i'); glyph.className = `fa-solid fa-${icon}`; glyph.setAttribute('aria-hidden', 'true');
                button.replaceChildren(glyph);
            }
        },
    });
    retry.addEventListener('click', () => calendar.refetchEvents());
    calendar.render();
    element.calendar = calendar;
    return calendar;
}
