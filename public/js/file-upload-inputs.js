(() => {
    const zoneFor = (input) => document.querySelector(`.upload-dropzone[for="${CSS.escape(input.id)}"]`);
    const inputFor = (zone) => zone?.htmlFor ? document.getElementById(zone.htmlFor) : null;

    document.addEventListener('change', (event) => {
        const input = event.target.closest('.upload-input');
        if (!input) return;
        zoneFor(input)?.classList.toggle('has-file', input.files?.length > 0);
    });

    document.addEventListener('dragover', (event) => {
        const zone = event.target.closest('.upload-dropzone');
        if (!zone) return;
        event.preventDefault();
        zone.classList.add('is-dragging');
    });

    document.addEventListener('dragleave', (event) => {
        const zone = event.target.closest('.upload-dropzone');
        if (zone && !zone.contains(event.relatedTarget)) zone.classList.remove('is-dragging');
    });

    document.addEventListener('drop', (event) => {
        const zone = event.target.closest('.upload-dropzone');
        if (!zone) return;
        event.preventDefault();
        zone.classList.remove('is-dragging');
        const input = inputFor(zone);
        if (!input || !event.dataTransfer?.files?.length) return;
        try {
            const transfer = new DataTransfer();
            transfer.items.add(event.dataTransfer.files[0]);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        } catch {
            zone.classList.remove('has-file');
        }
    });
})();
