const colorMap = {
    success: {
        bg: 'border border-success/20 bg-soft',
        dot: 'bg-success',
        title: 'text-success',
        time: 'text-success/80',
    },
    danger: {
        bg: 'border border-danger/20 bg-soft',
        dot: 'bg-danger',
        title: 'text-danger',
        time: 'text-danger/80',
    },
    primary: {
        bg: 'border border-brand-100 bg-brand-50  ',
        dot: 'bg-brand-500',
        title: 'text-brand-700 ',
        time: 'text-brand-600/80 ',
    },
    warning: {
        bg: 'border border-warning/20 bg-soft',
        dot: 'bg-warning',
        title: 'text-warning',
        time: 'text-warning/80',
    },
    info: {
        bg: 'border border-brand-500/20 bg-soft',
        dot: 'bg-brand-500',
        title: 'text-brand-500',
        time: 'text-brand-500/80',
    },
};

const escapeHtml = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

export const renderEventItem = (eventInfo) => {
    const level = (eventInfo.event.extendedProps?.calendar || 'primary').toLowerCase();
    const colors = colorMap[level] ?? colorMap.primary;
    const title = escapeHtml(eventInfo.event.title || '');
    const timeText = escapeHtml(eventInfo.timeText || '');
    const isTimeGridView = !eventInfo.event?.allDay && eventInfo.view?.type && eventInfo.view.type.startsWith('timeGrid');

    if (isTimeGridView) {
        return `
            <div dir="ltr" class="event-fc-color flex h-full w-full flex-col justify-start overflow-hidden rounded-md p-1 transition-colors sm:rounded-lg sm:p-1.5 ${colors.bg}">
                <div class="flex items-center gap-1 sm:gap-1.5">
                    <div class="size-1.5 shrink-0 rounded-full sm:size-2 ${colors.dot}"></div>
                    <div class="truncate text-[11px] leading-tight font-semibold sm:text-xs ${colors.title}">${title}</div>
                </div>
                ${timeText ? `<div class="mt-0.5 truncate ps-2.5 text-[10px] leading-tight font-medium sm:ps-3.5 sm:text-[11px] ${colors.time}">${timeText}</div>` : ''}
            </div>`;
    }

    return `
        <div dir="ltr" class="event-fc-color flex items-center rounded-md py-1 ps-1.5 pe-2 transition-colors sm:rounded-lg sm:py-1.5 sm:ps-2.5 sm:pe-3 ${colors.bg}">
            <div class="fc-daygrid-event-dot ms-0 me-1 h-2.5 w-1 shrink-0 rounded-full border-none sm:me-2 sm:h-3.5 ${colors.dot}"></div>
            ${timeText ? `<div class="fc-event-time me-1 p-0 text-[10px] font-normal text-muted sm:me-1.5 sm:text-xs ">${timeText}</div>` : ''}
            <div class="fc-event-title truncate p-0 text-[11px] font-medium text-ink sm:text-xs ">${title}</div>
        </div>`;
};
