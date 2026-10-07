/**
 * Tools → Negaresh (I6, B33): the list is built by PHP like the posts list. This script checks the
 * posts on the current page (nothing is saved) and fills the Changes column, and runs "Fix" on the
 * selected posts and "Fix all waiting posts" in batches over REST. A request that fails never
 * stops the run: its posts are counted as failed, the others go on. Without JavaScript the bulk
 * action and "Fix now" still work through the page itself.
 */
(function () {
    'use strict';

    var config = window.negareshBulk || {};
    var root = document.querySelector('.negaresh-bulk');
    if (!root || !window.wp || !window.wp.apiFetch) {
        return;
    }
    var form = document.getElementById('negaresh-posts-filter');
    var status = root.querySelector('.negaresh-bulk-status');
    var fixAll = root.querySelector('.negaresh-fix-all');
    // wp_localize_script() sends every value as a string: "10" made `start += batch` glue strings
    // ("010", "01010"), so the second request asked for every remaining post, was refused, and
    // the old scan stopped after the first ten posts (B33).
    var batch = parseInt(config.batch, 10) || 10;
    var running = false;

    function format(text) {
        var args = Array.prototype.slice.call(arguments, 1);
        return String(text || '').replace(/%(\d)\$[ds]|%[ds]/g, function (match, index) {
            return String(index ? args[Number(index) - 1] : args[0]);
        });
    }

    function chunks(ids) {
        var out = [];
        for (var start = 0; start < ids.length; start += batch) {
            out.push(ids.slice(start, start + batch));
        }
        return out;
    }

    function process(ids, apply) {
        return window.wp.apiFetch({
            path: '/negaresh/v1/bulk/process',
            method: 'POST',
            data: { ids: ids, apply: apply }
        });
    }

    function errorText(error) {
        return (error && (error.message || error.code)) || config.failed || '';
    }

    // Changes column: check the posts of this page, one batch after the other.
    function showChanges(cell, result) {
        cell.textContent = '';
        if (result.skipped === 'not_allowed') {
            cell.textContent = config.notAllowed || '';
            return;
        }
        if (!result.changed) {
            cell.textContent = (cell.dataset.state === 'fixed' ? config.upToDate : config.noChange) || '';
            cell.classList.add('negaresh-no-change');
            return;
        }
        var details = document.createElement('details');
        var summary = document.createElement('summary');
        var count = 0;
        (result.diff || []).forEach(function (field) {
            var pre = document.createElement('pre');
            pre.dir = 'rtl';
            field.lines.forEach(function (line) {
                var span = document.createElement('span');
                span.className = line[0] === '-' ? 'negaresh-removed' : 'negaresh-added';
                span.textContent = line[0] + ' ' + line[1] + '\n';
                pre.appendChild(span);
                count++;
            });
            details.appendChild(pre);
        });
        summary.textContent = format(config.changes, count);
        details.insertBefore(summary, details.firstChild);
        cell.appendChild(details);
    }

    var cells = {};
    Array.prototype.forEach.call(root.querySelectorAll('.negaresh-changes[data-id]'), function (cell) {
        cells[cell.dataset.id] = cell;
        cell.textContent = config.checking || '';
    });
    chunks(Object.keys(cells).map(Number)).reduce(function (chain, ids) {
        return chain.then(function () {
            return process(ids, false).then(function (response) {
                (response.results || []).forEach(function (result) {
                    if (cells[result.id]) {
                        showChanges(cells[result.id], result);
                    }
                });
            }, function (error) {
                ids.forEach(function (id) {
                    cells[id].textContent = format(config.checkFailed, errorText(error));
                    cells[id].classList.add('negaresh-error');
                });
            });
        });
    }, Promise.resolve());

    // Fixing: every batch runs even when one fails; then back to the list with the counts.
    function fix(ids) {
        running = true;
        if (fixAll) {
            fixAll.disabled = true;
        }
        var counts = { negaresh_fixed: 0, negaresh_checked: 0, negaresh_failed: 0 };
        var done = 0;
        status.textContent = format(config.fixing, 0, ids.length);
        return chunks(ids).reduce(function (chain, slice) {
            return chain.then(function () {
                return process(slice, true).then(function (response) {
                    (response.results || []).forEach(function (result) {
                        var row = document.getElementById('negaresh-post-' + result.id);
                        if (result.skipped) {
                            counts.negaresh_failed++;
                        } else {
                            counts[result.changed ? 'negaresh_fixed' : 'negaresh_checked']++;
                        }
                        if (row) {
                            row.classList.add(result.skipped ? 'negaresh-skipped' : 'negaresh-done');
                        }
                    });
                }, function () {
                    counts.negaresh_failed += slice.length;
                }).then(function () {
                    done += slice.length;
                    status.textContent = format(config.fixing, done, ids.length);
                });
            });
        }, Promise.resolve()).then(function () {
            var url = new URL(window.location.href);
            ['action', 'action2', 'post[]', '_wpnonce', '_wp_http_referer'].forEach(function (key) {
                url.searchParams.delete(key);
            });
            Object.keys(counts).forEach(function (key) {
                url.searchParams.set(key, String(counts[key]));
            });
            window.location.assign(url.toString());
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            // Like WordPress: the button pressed decides which of the two selects counts.
            var submitter = event.submitter;
            if (submitter && submitter.id !== 'doaction' && submitter.id !== 'doaction2') {
                return;
            }
            var which = submitter && submitter.id === 'doaction2' ? 'action2' : 'action';
            var select = form.querySelector('select[name="' + which + '"]');
            if (!select || select.value !== config.action) {
                return;
            }
            event.preventDefault();
            if (running) {
                return;
            }
            var ids = Array.prototype.map.call(form.querySelectorAll('input[name="post[]"]:checked'), function (box) {
                return Number(box.value);
            });
            if (!ids.length) {
                window.alert(config.noneSelected || '');
                return;
            }
            if (window.confirm(format(config.confirmSelected, ids.length))) {
                fix(ids);
            }
        });
    }

    if (fixAll) {
        fixAll.addEventListener('click', function () {
            if (running || !window.confirm(format(config.confirmAll, fixAll.dataset.count))) {
                return;
            }
            fixAll.disabled = true;
            window.wp.apiFetch({
                path: '/negaresh/v1/bulk/find',
                method: 'POST',
                data: { post_type: fixAll.dataset.type ? [fixAll.dataset.type] : [] }
            }).then(function (response) {
                var ids = response.ids || [];
                if (!ids.length) {
                    status.textContent = config.nothingWaiting || '';
                    fixAll.disabled = false;
                    return null;
                }
                return fix(ids);
            }).catch(function (error) {
                status.textContent = errorText(error);
                fixAll.disabled = false;
                running = false;
            });
        });
    }
})();
