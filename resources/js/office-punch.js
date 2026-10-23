export function initializeOfficePunch() {
    const buttons = [...document.querySelectorAll('[data-office-punch]')];
    if (!buttons.length) return;
    let access = JSON.parse(buttons[0].dataset.officeAccess), pending = false, timer, sequence = 0;

    function render() {
        buttons.forEach(button => {
            button.hidden = !access.office;
            button.classList.toggle('d-none', !access.office);
            button.disabled = !access.office || pending;
        });
        const status = document.getElementById('office-punch-status');
        if (status) status.textContent = access.office
            ? 'Office punch access is currently enabled by an administrator.'
            : 'Office punches are unavailable until an administrator enables a time window.';
    }

    async function refresh() {
        const current = ++sequence;
        clearTimeout(timer);
        let delay = 30000;
        try {
            const result = await window.HRIS.request('/attendance/clock-availability');
            if (current !== sequence) return;
            access = result;
            if (access.next_change_at) delay = Math.min(delay, Math.max(100, Date.parse(access.next_change_at) - Date.parse(access.server_time)));
        } catch {
            if (current !== sequence) return;
            access = {office: false};
        }
        render();
        timer = setTimeout(refresh, delay);
    }

    buttons.forEach(button => button.addEventListener('click', async () => {
        if (pending || !access.office) return;
        pending = true;
        render();
        try {
            const result = await window.HRIS.request('/attendance/clock', {method: 'POST', body: {wfh: false}});
            window.HRIS.feedback(result.message);
            window.dispatchEvent(new Event('attendance:punch-recorded'));
        } catch (error) {
            window.HRIS.feedback(error.message, true);
        } finally {
            pending = false;
            await refresh();
        }
    }));
    window.addEventListener('attendance:office-access-changed', refresh);
    window.addEventListener('pageshow', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    render();
    refresh();
}
