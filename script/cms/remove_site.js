/**
 * Javascript Library for Tak-Me CMS
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2020 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', cmsSiteInitializeRemoving)
        break;
    case 'interactive':
    case 'complete':
        cmsSiteInitializeRemoving();
        break;
}

function cmsSiteInitializeRemoving(event) {
    const checkbox = document.querySelector('input[name=removing]');
    if (checkbox) {
        checkbox.addEventListener('click', cmsSiteEnableSubmitListener);
        cmsSiteEnableSubmit(checkbox);
    }
}
function cmsSiteEnableSubmitListener(event) {
    cmsSiteEnableSubmit(event.target);
}

function cmsSiteEnableSubmit(element) {
    const form = element.form;
    const button = form.querySelector('input[name=s1_submit]');
    if (button) {
        button.disabled = !element.checked;
        if (button.disabled) {
            form.removeEventListener('submit', cmsSiteConfirmRemoval);
            const p = document.getElementById('passphrase-container');
            if (p) {
                p.parentNode.removeChild(p);
            }
        } else {
            form.addEventListener('submit', cmsSiteConfirmRemoval);
            const button = form.querySelector('input[name=s1_submit]');
            const p = document.createElement('p');
            p.id = 'passphrase-container';
            const passwd = p.appendChild(document.createElement('input'));
            passwd.type = 'password';
            passwd.name = 'passphrase';
            passwd.placeholder = 'Your Password';
            passwd.required = true;
            button.parentNode.insertBefore(p, button);
            passwd.focus();
        }
    }
}

function cmsSiteConfirmRemoval(event) {
    event.preventDefault();
    const form = event.target;
    if (confirm(decodeURIComponent(form.dataset.removeConfirm))) {
        form.submit();
    }
}
