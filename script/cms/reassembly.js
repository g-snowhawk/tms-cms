/**
 * This file is part of Tak-Me Contents Management System.
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @author    PlusFive.
 * @copyright (c)2019 PlusFive. (http://www.plus-5.com/)
 */
const buttonSubmit = document.querySelector('input[name=s1_submit]');
const progressScreenId = 'progress1';

let pollingTimer = 0;
let pollingDelay = 1000;
let pollingInterval = 10000;

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', initializeReassembly)
        break;
    case 'interactive':
    case 'complete':
        initializeReassembly();
        break;
}

function initializeReassembly(event) {
    if (buttonSubmit) {
        buttonSubmit.addEventListener('click', assembly);
    }
}

function assembly(event) {
    event.preventDefault();
    const element = event.currentTarget;
    const form = element.form;

    if (element.dataset.confirm && !confirm(element.dataset.confirm)) {
        console.warn('The operation was canceled by user.');
        return;
    }

    setProgressScreen();

    if (typeof(fetch) !== 'function') {
        return form.submit();
    }

    data = new FormData(form);
    data.append('returntype', 'json');

    fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: data
    }).then(response => response.json())
        .then(json => received(json))
        .catch(function(error){
            setProgressScreen(1);
            console.error(error)
        });
}

function received(json) {
    if (json.status === 0) {
        pollingTimer = setTimeout(
            polling,
            pollingInterval,
            json.arguments.polling_address + "&polling_id=" + json.arguments.polling_id
        );
    } else {
        setProgressScreen(1);
        alert(json.message);
    }
}

function polling(url) {
    fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
    }).then(response => response.json())
        .then(function(json){
            clearTimeout(pollingTimer);
            if (json.status === 'running') {
                pollingTimer = setTimeout(polling, pollingInterval, url);
            } else if (json.status === 'ended') {
                setProgressScreen(1);
                alert(json.response.message);
            }
        })
        .catch(function(error){
            setProgressScreen(1);
            console.error(error)
        });
}

function setProgressScreen(clear) {
    if (clear) {
        const div = document.getElementById(progressScreenId);
        if (div) div.parentNode.removeChild(div);
    } else {
        const div = document.body.appendChild(document.createElement('div'));
        div.id = progressScreenId;
        div.appendChild(document.createElement('div'));
    }
}
