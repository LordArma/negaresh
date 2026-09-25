/**
 * Negaresh settings page: live preview (I5) and a confirmation before resetting the rules.
 * Loaded only on Settings → Negaresh. Uses wp.apiFetch, which adds the REST nonce.
 */
(function () {
    'use strict';

    var config = window.negareshAdmin || {};
    var input = document.getElementById('negaresh-preview-input');
    var output = document.getElementById('negaresh-preview-output');
    var status = document.querySelector('.negaresh-preview-status');
    var form = document.querySelector('.negaresh-settings form');
    var timer = null;
    var latest = 0;

    function checkedRules() {
        var rules = {};
        (config.rules || []).forEach(function (key) {
            var box = document.getElementById('negaresh_' + key);
            rules[key] = !!(box && box.checked);
        });
        return rules;
    }

    function protectedWords() {
        var box = document.getElementById('negaresh_protected_words');
        return box ? box.value : '';
    }

    function preview() {
        if (!input || !output || !window.wp || !window.wp.apiFetch) {
            return;
        }
        var text = input.value;
        if (!text.trim()) {
            output.value = '';
            status.textContent = '';
            return;
        }
        var request = ++latest;
        status.textContent = config.working || '';
        window.wp.apiFetch({
            path: '/negaresh/v1/preview',
            method: 'POST',
            data: { text: text, rules: checkedRules(), words: protectedWords() }
        }).then(function (response) {
            if (request !== latest) {
                return; // an older answer arriving late
            }
            output.value = response && typeof response.text === 'string' ? response.text : '';
            status.textContent = '';
        }).catch(function () {
            if (request === latest) {
                status.textContent = config.failed || '';
            }
        });
    }

    function later() {
        window.clearTimeout(timer);
        timer = window.setTimeout(preview, 300);
    }

    if (input) {
        input.addEventListener('input', later);
    }
    var wordsBox = document.getElementById('negaresh_protected_words');
    if (wordsBox) {
        wordsBox.addEventListener('input', later);
    }
    if (form) {
        form.addEventListener('change', function (event) {
            if (event.target && event.target.type === 'checkbox') {
                later();
            }
        });
        var reset = form.querySelector('.negaresh-reset');
        if (reset) {
            reset.addEventListener('click', function (event) {
                if (!window.confirm(config.confirmReset || '')) {
                    event.preventDefault();
                }
            });
        }
    }
})();
