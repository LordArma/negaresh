/**
 * Negaresh panel in the block editor's document sidebar (I6): "Leave this post alone" and
 * "Fix this post now". Plain JavaScript on the wp.* globals, no build step.
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.plugins || !wp.element || !wp.data || !wp.components) {
        return;
    }
    // WordPress 6.6 moved the panel to wp.editor; 5.8 to 6.5 have it in wp.editPost.
    var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
    if (!Panel) {
        return;
    }

    var el = wp.element.createElement;
    var config = window.negareshEditor || {};
    var META = config.meta || '_negaresh_skip';

    function notice(kind, message) {
        wp.data.dispatch('core/notices').createNotice(kind, message, { type: 'snackbar', isDismissible: true });
    }

    function NegareshPanel() {
        var skip = wp.data.useSelect(function (select) {
            var meta = select('core/editor').getEditedPostAttribute('meta') || {};
            return !!meta[META];
        }, []);
        var editor = wp.data.useDispatch('core/editor');
        var busyState = wp.element.useState(false);
        var busy = busyState[0];
        var setBusy = busyState[1];

        function setSkip(value) {
            var meta = {};
            meta[META] = !!value;
            editor.editPost({ meta: meta });
        }

        function fixNow() {
            var select = wp.data.select('core/editor');
            var content = select.getEditedPostContent();
            var title = select.getEditedPostAttribute('title') || '';
            setBusy(true);
            wp.apiFetch({
                path: '/negaresh/v1/fix',
                method: 'POST',
                data: { content: content, title: title }
            }).then(function (result) {
                if (!result || (result.content === content && result.title === title)) {
                    notice('info', config.nothing || '');
                    return;
                }
                if (result.content !== content) {
                    // One undoable step: the whole document is replaced by the fixed blocks.
                    editor.resetEditorBlocks(wp.blocks.parse(result.content));
                }
                if (result.title !== title) {
                    editor.editPost({ title: result.title });
                }
                notice('success', config.done || '');
            }).catch(function () {
                notice('error', config.failed || '');
            }).finally(function () {
                setBusy(false);
            });
        }

        return el(
            Panel,
            { name: 'negaresh', title: config.title || 'Negaresh', className: 'negaresh-panel' },
            el(wp.components.CheckboxControl, {
                className: 'negaresh-skip-toggle',
                label: config.skipLabel || '',
                checked: skip,
                onChange: setSkip
            }),
            el(wp.components.Button, {
                className: 'negaresh-fix-now',
                variant: 'secondary',
                isSecondary: true, // WordPress before 6.0
                isBusy: busy,
                disabled: busy || skip,
                onClick: fixNow
            }, config.fixLabel || ''),
            el('p', { className: 'components-form-token-field__help' }, config.fixHelp || '')
        );
    }

    wp.plugins.registerPlugin('negaresh', { render: NegareshPanel, icon: 'editor-spellcheck' });
})(window.wp);
