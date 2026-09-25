/**
 * Tools → Negaresh (I6): scan in batches (nothing saved), list posts that would change with their
 * changed lines, then fix exactly those posts in batches.
 */
(function () {
    'use strict';

    var config = window.negareshBulk || {};
    var root = document.querySelector('.negaresh-bulk');
    if (!root || !window.wp || !window.wp.apiFetch) {
        return;
    }
    var form = root.querySelector('.negaresh-bulk-form');
    var scanButton = root.querySelector('.negaresh-scan');
    var applyButton = root.querySelector('.negaresh-apply');
    var status = root.querySelector('.negaresh-bulk-status');
    var table = root.querySelector('.negaresh-bulk-results');
    var body = table.querySelector('tbody');
    var batch = config.batch || 10;
    var changedIds = [];

    function format(text) {
        var args = Array.prototype.slice.call(arguments, 1);
        return String(text || '').replace(/%(\d)\$d|%d/g, function (match, index) {
            return String(index ? args[Number(index) - 1] : args[0]);
        });
    }

    function busy(on) {
        scanButton.disabled = on;
        applyButton.disabled = on || changedIds.length === 0;
    }

    function cell(row, content) {
        var td = document.createElement('td');
        if (content) {
            td.appendChild(content);
        }
        row.appendChild(td);
        return td;
    }

    function showResult(result) {
        var row = document.createElement('tr');
        row.dataset.id = result.id;
        var title = document.createElement(result.edit_link ? 'a' : 'span');
        title.textContent = result.title || ('#' + result.id);
        if (result.edit_link) {
            title.href = result.edit_link;
        }
        cell(row, title);

        var details = document.createElement('details');
        var summary = document.createElement('summary');
        summary.textContent = config.changes || '';
        details.appendChild(summary);
        (result.diff || []).forEach(function (field) {
            var pre = document.createElement('pre');
            pre.dir = 'rtl';
            field.lines.forEach(function (line) {
                var span = document.createElement('span');
                span.className = line[0] === '-' ? 'negaresh-removed' : 'negaresh-added';
                span.textContent = line[0] + ' ' + line[1] + '\n';
                pre.appendChild(span);
            });
            details.appendChild(pre);
        });
        cell(row, details);
        body.appendChild(row);
        table.hidden = false;
    }

    function run(ids, apply, onResult, progressText) {
        var done = 0;
        var chain = Promise.resolve();
        for (var start = 0; start < ids.length; start += batch) {
            (function (slice) {
                chain = chain.then(function () {
                    return window.wp.apiFetch({
                        path: '/negaresh/v1/bulk/process',
                        method: 'POST',
                        data: { ids: slice, apply: apply }
                    }).then(function (response) {
                        (response.results || []).forEach(onResult);
                        done += slice.length;
                        status.textContent = format(progressText, done, ids.length);
                    });
                });
            })(ids.slice(start, start + batch));
        }
        return chain;
    }

    function selectedTypes() {
        return Array.prototype.map.call(form.querySelectorAll('input[name="post_type"]:checked'), function (box) {
            return box.value;
        });
    }

    scanButton.addEventListener('click', function () {
        changedIds = [];
        body.textContent = '';
        table.hidden = true;
        busy(true);
        var all = form.querySelector('input[name="all"]').checked;
        window.wp.apiFetch({
            path: '/negaresh/v1/bulk/find',
            method: 'POST',
            data: { post_type: selectedTypes(), all: all }
        }).then(function (response) {
            var ids = response.ids || [];
            return run(ids, false, function (result) {
                if (result.changed) {
                    changedIds.push(result.id);
                    showResult(result);
                }
            }, config.scanning).then(function () {
                status.textContent = changedIds.length ? format(config.found, changedIds.length, ids.length) : config.none;
            });
        }).catch(function () {
            status.textContent = config.failed || '';
        }).finally(function () {
            busy(false);
        });
    });

    applyButton.addEventListener('click', function () {
        if (!changedIds.length || !window.confirm(format(config.confirm, changedIds.length))) {
            return;
        }
        busy(true);
        var fixed = 0;
        run(changedIds.slice(), true, function (result) {
            var row = body.querySelector('tr[data-id="' + result.id + '"]');
            if (result.skipped && row) {
                row.classList.add('negaresh-skipped');
                row.title = result.skipped === 'not_allowed' ? (config.notAllowed || '') : result.skipped;
            } else if (row) {
                row.classList.add('negaresh-done');
                fixed++;
            }
        }, config.fixing).then(function () {
            changedIds = [];
            status.textContent = format(config.fixed, fixed);
        }).catch(function () {
            status.textContent = config.failed || '';
        }).finally(function () {
            busy(false);
        });
    });
})();
