import Swal from 'sweetalert2/dist/sweetalert2.js';

const darkMedia = matchMedia('(prefers-color-scheme: dark)');
function preference() {
    try { return localStorage.getItem('hris-theme') || localStorage.getItem('flux.appearance') || 'system'; }
    catch { return 'system'; }
}
export function applyTheme(value = preference(), persist = false) {
    const theme = value === 'system' ? (darkMedia.matches ? 'dark' : 'light') : value;
    document.documentElement.dataset.bsTheme = theme;
    document.documentElement.classList.toggle('dark', theme === 'dark');
    if (persist) {
        try { localStorage.setItem('hris-theme', value); localStorage.setItem('flux.appearance', value); } catch { /* Storage is optional. */ }
    }
    document.querySelectorAll('[data-theme-toggle]').forEach(button => {
        button.setAttribute('aria-checked', String(theme === 'dark'));
        button.title = theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme';
        const icon = button.querySelector('i');
        if (icon) icon.className = `fa-solid fa-toggle-${theme === 'dark' ? 'on' : 'off'}`;
    });
    document.querySelectorAll('[data-set-theme]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.setTheme === value)));
}
export function notify(message, error = false) {
    return Swal.fire({
        toast: true, position: 'top-end', icon: error ? 'error' : 'success', text: String(message),
        theme: document.documentElement.dataset.bsTheme || 'light',
        showConfirmButton: false, showCloseButton: true, timer: error ? 7000 : 3200, timerProgressBar: true,
        didOpen: popup => { popup.addEventListener('mouseenter', Swal.stopTimer); popup.addEventListener('mouseleave', Swal.resumeTimer); },
    });
}
export async function confirmAction(message) {
    const result = await Swal.fire({
        title: 'Please confirm', text: message, icon: 'question', showCancelButton: true,
        confirmButtonText: 'Continue', cancelButtonText: 'Cancel', confirmButtonColor: '#3468d7',
        theme: document.documentElement.dataset.bsTheme || 'light', heightAuto: false,
    });
    return result.isConfirmed;
}
function initialize() {
    applyTheme();
    document.querySelectorAll('[data-flash-message]:not([data-notified])').forEach(element => {
        element.dataset.notified = 'true';
        notify(element.dataset.flashMessage, element.dataset.flashError === 'true');
    });
}
document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (toggle) applyTheme(document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark', true);
    const option = event.target.closest('[data-set-theme]');
    if (option) applyTheme(option.dataset.setTheme, true);
});
document.addEventListener('error', event => {
    const image = event.target;
    if (image instanceof HTMLImageElement && image.dataset.avatar && !image.dataset.fallbackUsed) {
        image.dataset.fallbackUsed = 'true';
        image.src = image.dataset.avatar + '?fallback=1';
    }
}, true);
window.addEventListener('app:notify', event => notify(event.detail.message, event.detail.error ?? false));
window.addEventListener('storage', event => { if (['hris-theme', 'flux.appearance'].includes(event.key)) applyTheme(); });
darkMedia.addEventListener('change', () => { if (preference() === 'system') applyTheme(); });
document.addEventListener('livewire:navigated', initialize);
document.addEventListener('livewire:initialized', initialize);
// Livewire validation stays inline; also announce submission errors through the shared toast.
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ commit, succeed, fail }) => {
        const submitted = commit.calls.some(call => /^(update|save|store|delete|confirm|enable|disable|resend)/.test(call.method));
        if (!submitted) return;
        succeed(({ snapshot, effects }) => {
            const state = typeof snapshot === 'string' ? JSON.parse(snapshot) : snapshot;
            const errors = state.memo?.errors;
            if (errors && Object.keys(errors).length) notify(Object.values(errors).flat().join(' '), true);
            else if (!effects.redirect && !effects.dispatches?.some(event => event.name === 'app:notify')) notify('Changes saved.');
        });
        fail(() => notify('The request could not be completed. Please try again.', true));
    });
});
function refreshAvatars() {
    document.querySelectorAll('img[data-avatar], img[src*="/avatar"]').forEach(image => {
        const base = image.dataset.avatar || image.src.split('?')[0];
        image.src = `${base}?v=${Date.now()}`;
        delete image.dataset.fallbackUsed;
    });
}
document.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('[data-profile-photo-form]')) return;
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    try {
        const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}});
        const data = await response.json();
        if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'The photo could not be uploaded.');
        notify('Profile picture updated.'); refreshAvatars(); form.reset();
    } catch (error) { notify(error.message, true); }
    finally { button.disabled = false; }
});
window.AppUI = { notify, confirm: confirmAction, setTheme: value => applyTheme(value, true), refreshAvatars };
initialize();
