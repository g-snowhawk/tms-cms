
switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', tmsImportTemplatesInit)
        break;
    case 'interactive':
    case 'complete':
        tmsImportTemplatesInit();
        break;
}

function tmsImportTemplatesInit(event) {
    const element = document.querySelector('input[name=template_xml]');
    if (element) {
        element.addEventListener('change', tmsImportTemplateRun);
    }
}

function tmsImportTemplateRun(event) {
    const trigger = event.target;
    const form = trigger.form;
    if (!confirm(trigger.dataset.confirm)) {
        event.preventDefault();
        return;
    }

    const formData = new FormData(form);
    formData.set('mode', trigger.dataset.mode);

    fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then((response) => {
        console.log(response);
    })
    .catch((error) => {
        console.error(error);
    });
}
