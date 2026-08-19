// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AMD helper for block_bibliotech resource selection modal.
 *
 * @module     block_bibliotech/block_helper
 * @copyright  2026 Trevor McCready, Horizon Education Network <https://www.horizonednet.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/modal_factory', 'core/notification'], function(ModalFactory, Notification) {
    'use strict';

    let activeInstanceId = 0;
    let activeScope = 'personal';
    let activeSesskey = '';
    let activeWwwroot = '';
    let activeModal = null;

    function extractResourceData(data) {
        if (!data) {
            return null;
        }

        let item = data;
        if (data.multiple && Array.isArray(data.multiple) && data.multiple.length > 0) {
            item = data.multiple[0];
        }

        const title = item.name || item.title || item.text || 'Bibliotech Publication';
        let kind = item.kind || 'book';

        const customParamsStr = item.instructorcustomparameters || (typeof item.custom === 'string' ? item.custom : '');
        const customParams = {};

        if (customParamsStr) {
            customParamsStr.split('\n').forEach(function(line) {
                const parts = line.split('=');
                if (parts.length >= 2) {
                    customParams[parts[0].trim()] = parts.slice(1).join('=').trim();
                }
            });
        } else if (typeof item.custom === 'object' && item.custom !== null) {
            Object.assign(customParams, item.custom);
        }

        if (customParams.kind) {
            kind = customParams.kind.toLowerCase() === 'journal' ? 'journal' : 'book';
        }

        // 1. Search for genuine 36-char UUID in payload
        const uuidRegex = /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i;
        const allText = JSON.stringify(item) + ' ' + (item.instructorcustomparameters || '') + ' ' + (item.toolurl || '') + ' ' + (item.url || '');
        let uuid = '';
        const match = allText.match(uuidRegex);
        if (match) {
            uuid = match[0].toLowerCase();
        }

        // 2. Direct property checks
        if (!uuid) {
            uuid = item.uuid || customParams.uuid || '';
        }

        // 3. Fallback to id / publication_id
        if (!uuid) {
            uuid = item.id || customParams.publication_id || customParams.id || customParams.resource_id || '';
        }

        if (!uuid) {
            return null;
        }

        return {
            title: title,
            uuid: uuid,
            kind: kind
        };
    }

    function saveResource(resData) {
        if (!resData || !activeInstanceId) {
            return;
        }

        const formData = new FormData();
        formData.append('instanceid', activeInstanceId);
        formData.append('action', 'add');
        formData.append('scope', activeScope);
        formData.append('title', resData.title);
        formData.append('uuid', resData.uuid);
        formData.append('kind', resData.kind);
        formData.append('sesskey', activeSesskey);

        fetch(activeWwwroot + '/blocks/bibliotech/action.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function(res) {
            return res.json();
        }).then(function() {
            if (activeModal) {
                activeModal.destroy();
            }
            window.location.reload();
        }).catch(function(err) {
            if (activeModal) {
                activeModal.destroy();
            }
            Notification.exception(err);
        });
    }

    // Define global callback expected by Moodle LTI Deep Linking return (mod_lti/contentitem_return)
    window.processContentItemReturnData = function(returnData) {
        const resData = extractResourceData(returnData);
        if (resData) {
            saveResource(resData);
        } else if (activeModal) {
            activeModal.destroy();
        }
    };

    function setupAddButton(btnId, scope, instanceId, sesskey, wwwroot, deeplinkUrl) {
        const btn = document.getElementById(btnId);
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            activeInstanceId = instanceId;
            activeScope = scope;
            activeSesskey = sesskey;
            activeWwwroot = wwwroot;

            const targetUrl = deeplinkUrl || (wwwroot + '/local/bibliotech/select_content.php');
            const modalTitle = (scope === 'shared') ? 'Select Shared Publication' : 'Select Personal Quicklink';

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: modalTitle,
                body: '<div class="text-center p-2"><iframe id="bibliotech_block_iframe" src="' + targetUrl + '" style="width:100%;height:500px;border:none;"></iframe></div>',
                large: true
            }).then(function(modal) {
                activeModal = modal;
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

                    if (data.type === 'bibliotech_resource_selected' || data.isbn || data.id || data.uuid) {
                        const resData = extractResourceData(data);
                        if (resData) {
                            window.removeEventListener('message', handleMessage);
                            saveResource(resData);
                        }
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
