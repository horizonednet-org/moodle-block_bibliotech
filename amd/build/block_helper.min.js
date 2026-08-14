define(['core/modal_factory', 'core/notification'], function(ModalFactory, Notification) {
    'use strict';

    function setupAddButton(btnId, scope, instanceId, sesskey, wwwroot, deeplinkUrl) {
        const btn = document.getElementById(btnId);
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function(e) {
            e.preventDefault();

            const targetUrl = deeplinkUrl || (wwwroot + '/local/bibliotech/select_content.php');
            const modalTitle = (scope === 'shared') ? 'Select Shared Publication' : 'Select Personal Quicklink';

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: modalTitle,
                body: '<div class="text-center p-2"><iframe id="bibliotech_block_iframe" src="' + targetUrl + '" style="width:100%;height:500px;border:none;"></iframe></div>',
                large: true
            }).then(function(modal) {
                modal.show();

                const handleMessage = function(event) {
                    const iframe = document.getElementById('bibliotech_block_iframe');
                    if (iframe && event.source !== iframe.contentWindow) {
                        return;
                    }

                    if (!event.data) {
                        return;
                    }

                    let data = event.data;
                    if (typeof data === 'string') {
                        try {
                            data = JSON.parse(data);
                        } catch (err) {
                            return;
                        }
                    }

                    if (data.type === 'bibliotech_resource_selected' || data.isbn || data.id) {
                        const title = data.title || 'Bibliotech Publication';
                        const uuid = data.uuid || data.isbn || data.id;
                        if (!uuid) {
                            return;
                        }
                        const kind = data.kind || 'book';

                        const formData = new FormData();
                        formData.append('instanceid', instanceId);
                        formData.append('action', 'add');
                        formData.append('scope', scope);
                        formData.append('title', title);
                        formData.append('uuid', uuid);
                        formData.append('kind', kind);
                        formData.append('sesskey', sesskey);

                        fetch(wwwroot + '/blocks/bibliotech/action.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function(res) {
                            return res.json();
                        }).then(function() {
                            window.removeEventListener('message', handleMessage);
                            modal.destroy();
                            window.location.reload();
                        }).catch(function(err) {
                            Notification.exception(err);
                        });
                    }
                };

                window.addEventListener('message', handleMessage);
            });
        });
    }

    return {
        init: function(instanceId, sesskey, wwwroot, deeplinkUrl) {
            setupAddButton('block_bibliotech_add_personal_' + instanceId, 'personal', instanceId, sesskey, wwwroot, deeplinkUrl);
            setupAddButton('block_bibliotech_add_shared_' + instanceId, 'shared', instanceId, sesskey, wwwroot, deeplinkUrl);
            setupAddButton('block_bibliotech_add_btn_' + instanceId, 'personal', instanceId, sesskey, wwwroot, deeplinkUrl);
        }
    };
});
