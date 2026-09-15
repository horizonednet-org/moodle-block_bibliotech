<?php
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
 * Action handler for block_bibliotech (adding/removing block resources).
 * Supports both personal quicklinks and shared course/category resources.
 *
 * @package    block_bibliotech
 * @copyright  2026 Trevor McCready, Horizon Education Network <https://www.horizonednet.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/bibliotech/block_bibliotech.php');

require_login();
confirm_sesskey();

$blockinstanceid = required_param('instanceid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$scope = optional_param('scope', 'personal', PARAM_ALPHA); // 'personal' or 'shared'
$returnurl = optional_param('returnurl', $CFG->wwwroot, PARAM_LOCALURL);
$isajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || optional_param('ajax', 0, PARAM_BOOL);

// Verify user has active subscription access.
if (!class_exists('\local_bibliotech\access_manager') || !\local_bibliotech\access_manager::has_access()) {
    if ($isajax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => get_string('access_denied', 'local_bibliotech')]);
        exit;
    }
    print_error('access_denied', 'local_bibliotech');
}

$blockrecord = $DB->get_record('block_instances', ['id' => $blockinstanceid], '*', MUST_EXIST);
$blockinstance = block_instance('bibliotech', $blockrecord);

$userid = (int)$USER->id;
$config = !empty($blockinstance->config) ? $blockinstance->config : new stdClass();

if (!isset($config->user_resources) || !is_array($config->user_resources)) {
    $config->user_resources = [];
}
if (!isset($config->user_resources[$userid]) || !is_array($config->user_resources[$userid])) {
    $config->user_resources[$userid] = [];
}
if (!isset($config->shared_resources) || !is_array($config->shared_resources)) {
    $config->shared_resources = [];
}

// Capability check for shared resources
$context = $blockinstance->context;
$canmanage = has_capability('moodle/block:edit', $context) 
            || has_capability('moodle/course:manageactivities', $context)
            || has_capability('moodle/category:manage', $context)
            || is_siteadmin();

if ($scope === 'shared' && !$canmanage) {
    $scope = 'personal';
}

if ($action === 'add') {
    $title = required_param('title', PARAM_TEXT);
    $uuid = optional_param('uuid', '', PARAM_RAW);
    $id = optional_param('id', '', PARAM_RAW);

    if (class_exists('\local_bibliotech\publication_resolver')) {
        if (empty($id) && !empty($uuid)) {
            $resolvedid = \local_bibliotech\publication_resolver::resolve_id($uuid);
            if ($resolvedid) {
                $id = (string)$resolvedid;
            }
        }
        if (empty($uuid) && !empty($id)) {
            $resolveduuid = \local_bibliotech\publication_resolver::resolve_uuid($id);
            if ($resolveduuid) {
                $uuid = $resolveduuid;
            }
        }
    }

    if (empty($uuid) && !empty($id)) {
        $uuid = $id;
    }
    if (empty($uuid) && empty($id)) {
        if ($isajax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => get_string('invalidparameter', 'error')]);
            exit;
        }
        print_error('invalidparameter', 'error');
    }

    $kind = optional_param('kind', 'book', PARAM_ALPHA);
    $token = optional_param('token', '', PARAM_RAW);
    $lcpkey = optional_param('lcpkey', '', PARAM_RAW);

    // Build supported deep link URI (with token & lcpKey only for personal quicklinks to prevent account impersonation).
    if ($scope === 'personal' && !empty($token) && !empty($lcpkey) && !empty($uuid)) {
        $uri = "bibliotech://publication/{$kind}/{$uuid}?token=" . urlencode($token) . "&lcpKey=" . urlencode($lcpkey);
    } else if (!empty($uuid)) {
        $uri = "bibliotech://publication/{$kind}/{$uuid}";
    } else {
        $uri = "bibliotech://publication/{$kind}/{$id}";
    }

    $newitem = [
        'id' => $id,
        'uuid' => $uuid,
        'title' => $title,
        'kind' => $kind,
        'uri' => $uri,
        'token' => ($scope === 'personal') ? $token : '',
        'lcpkey' => ($scope === 'personal') ? $lcpkey : '',
        'timeadded' => time(),
        'addedby_id' => $userid,
        'addedby_name' => fullname($USER)
    ];

    if ($scope === 'shared') {
        $exists = false;
        foreach ($config->shared_resources as $res) {
            if ($res['uuid'] === $uuid) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $config->shared_resources[] = $newitem;
            $blockinstance->instance_config_save($config);
        }
    } else {
        $exists = false;
        foreach ($config->user_resources[$userid] as $res) {
            if ($res['uuid'] === $uuid) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $config->user_resources[$userid][] = $newitem;
            $blockinstance->instance_config_save($config);
        }
    }

    if ($isajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'scope' => $scope]);
        exit;
    }
} else if ($action === 'remove') {
    $uuid = required_param('uuid', PARAM_ALPHANUMEXT);

    if ($scope === 'shared' && $canmanage) {
        $newshared = [];
        foreach ($config->shared_resources as $res) {
            if ($res['uuid'] !== $uuid) {
                $newshared[] = $res;
            }
        }
        $config->shared_resources = $newshared;
        $blockinstance->instance_config_save($config);
    } else {
        $newuserres = [];
        foreach ($config->user_resources[$userid] as $res) {
            if ($res['uuid'] !== $uuid) {
                $newuserres[] = $res;
            }
        }
        $config->user_resources[$userid] = $newuserres;
        $blockinstance->instance_config_save($config);
    }

    if ($isajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'scope' => $scope]);
        exit;
    }
}

redirect($returnurl);
