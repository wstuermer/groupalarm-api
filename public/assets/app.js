// Minor progressive enhancement only - every form here works fine without JS.
document.addEventListener('submit', function (event) {
    const form = event.target;
    if (form.matches('[data-confirm]') && !window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});
