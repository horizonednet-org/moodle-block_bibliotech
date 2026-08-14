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
 * Bibliotech dashboard/course block class implementation.
 *
 * @package    block_bibliotech
 * @copyright  2026 Trevor McCready, Horizon Education Network <https://www.horizonednet.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Bibliotech dashboard/course block.
 */
class block_bibliotech extends block_base {

    /**
     * Initialize block title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_bibliotech');
    }

    /**
     * Applicable locations for the block.
     *
     * @return array Allowed page contexts.
     */
    public function applicable_formats() {
        return [
            'all' => true,
        ];
    }

    /**
     * Allow multiple instances on the same page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Enable instance configuration saving.
     *
     * @return bool
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Generate content for the block.
     *
     * @return stdClass Content object.
     */
    public function get_content() {
        global $OUTPUT, $PAGE, $CFG, $USER, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $hasaccess = false;
        if (class_exists('\local_bibliotech\access_manager')) {
            $hasaccess = \local_bibliotech\access_manager::has_access();
        }

        $subscribeurl = get_config('local_bibliotech', 'subscribe_url') ?: 'https://bibliotechsl.com/subscribe/';
        $instanceid = !empty($this->instance) ? $this->instance->id : 0;

        $html = html_writer::start_div('block_bibliotech_container p-2');

        if ($hasaccess) {
            // Determine page context and capabilities
            $context = $PAGE->context;
            $canaddshared = has_capability('moodle/block:edit', $this->context) 
                        || has_capability('moodle/course:manageactivities', $context)
                        || has_capability('moodle/category:manage', $context)
                        || is_siteadmin();

            // Label shared resources based on context level using get_string
            $sharedsectiontitle = get_string('section_course_resources', 'block_bibliotech');
            $sharedbuttonstr = get_string('add_course_resource', 'block_bibliotech');

            if ($context->contextlevel == CONTEXT_COURSECAT) {
                $sharedsectiontitle = get_string('section_category_resources', 'block_bibliotech');
                $sharedbuttonstr = get_string('add_category_resource', 'block_bibliotech');
            } else if ($context->contextlevel == CONTEXT_SYSTEM || $context->contextlevel == CONTEXT_USER) {
                $sharedsectiontitle = get_string('section_site_resources', 'block_bibliotech');
                $sharedbuttonstr = get_string('add_site_resource', 'block_bibliotech');
            }

            // Header Logo
            $html .= html_writer::start_div('text-center mb-3');
            $html .= html_writer::tag('img', '', [
                'src' => 'https://storage.googleapis.com/bibliotech-thumbnail/BiblioTech.png',
                'alt' => 'Bibliotech Logo',
                'style' => 'max-width:140px; height:auto;',
                'class' => 'mb-2'
            ]);
            $html .= html_writer::end_div();

            // Primary App Open Button
            $html .= html_writer::start_div('mb-3');
            $html .= html_writer::tag('a', get_string('open_app_button', 'local_bibliotech'), [
                'href' => 'bibliotech://',
                'class' => 'btn btn-primary btn-block w-100 font-weight-bold shadow-sm',
                'target' => '_self'
            ]);
            $html .= html_writer::end_div();

            // Action Buttons Area
            $html .= html_writer::start_div('btn-group-vertical w-100 mb-3 shadow-sm');
            
            // 1. Personal Quicklink Button (Available to all subscribers)
            $html .= html_writer::tag('button', get_string('add_personal_quicklink', 'block_bibliotech'), [
                'id' => 'block_bibliotech_add_personal_' . $instanceid,
                'class' => 'btn btn-outline-primary btn-sm text-left font-weight-bold',
                'title' => get_string('tooltip_personal_button', 'block_bibliotech')
            ]);

            // 2. Shared Resource Button (Available to teachers, managers, admins)
            if ($canaddshared) {
                $html .= html_writer::tag('button', $sharedbuttonstr, [
                    'id' => 'block_bibliotech_add_shared_' . $instanceid,
                    'class' => 'btn btn-outline-info btn-sm text-left font-weight-bold',
                    'title' => get_string('tooltip_shared_button', 'block_bibliotech')
                ]);
            }
            $html .= html_writer::end_div();

            // Fetch Resources Data
            $userid = (int)$USER->id;
            $personalresources = [];
            $sharedresources = [];

            if (!empty($this->config)) {
                if (isset($this->config->user_resources) && isset($this->config->user_resources[$userid])) {
                    $personalresources = $this->config->user_resources[$userid];
                } else if (isset($this->config->resources)) {
                    $personalresources = $this->config->resources;
                }

                if (isset($this->config->shared_resources) && is_array($this->config->shared_resources)) {
                    $sharedresources = $this->config->shared_resources;
                }
            }

            // Accordion / Collapsible Container
            $accordionid = 'bibliotech_accordion_' . $instanceid;
            $html .= html_writer::start_div('accordion', ['id' => $accordionid]);

            // SECTION 1: Shared Course / Category Resources
            if (!empty($sharedresources) || $canaddshared) {
                $sharedcollapseid = 'collapse_shared_' . $instanceid;
                $isexpanded = !empty($sharedresources);

                $html .= html_writer::start_div('card border-info mb-2 shadow-sm');
                
                // Card Header
                $html .= html_writer::start_div('card-header p-2 bg-light d-flex justify-content-between align-items-center');
                $html .= html_writer::tag('button', '📚 ' . $sharedsectiontitle, [
                    'class' => 'btn btn-link text-info p-0 font-weight-bold small text-left',
                    'data-toggle' => 'collapse',
                    'data-target' => '#' . $sharedcollapseid,
                    'aria-expanded' => $isexpanded ? 'true' : 'false',
                    'aria-controls' => $sharedcollapseid
                ]);
                $html .= html_writer::tag('span', get_string('total_badge', 'block_bibliotech', count($sharedresources)), ['class' => 'badge badge-info badge-pill']);
                $html .= html_writer::end_div();

                // Card Body / Collapsible Content
                $html .= html_writer::start_div('collapse ' . ($isexpanded ? 'show' : ''), [
                    'id' => $sharedcollapseid,
                    'data-parent' => '#' . $accordionid
                ]);
                $html .= html_writer::start_div('card-body p-2');

                if (!empty($sharedresources)) {
                    $html .= html_writer::start_div('list-group list-group-flush');
                    foreach ($sharedresources as $res) {
                        $uuid = !empty($res['uuid']) ? $res['uuid'] : (!empty($res['id']) ? $res['id'] : '');
                        $kind = !empty($res['kind']) ? $res['kind'] : 'book';
                        $title = !empty($res['title']) ? $res['title'] : 'Bibliotech Publication';
                        $addedby = !empty($res['addedby_name']) ? get_string('added_by', 'block_bibliotech', s($res['addedby_name'])) : '';

                        $applink = "bibliotech://publication/{$kind}/{$uuid}";
                        $removeurl = new moodle_url('/blocks/bibliotech/action.php', [
                            'instanceid' => $instanceid,
                            'action' => 'remove',
                            'scope' => 'shared',
                            'uuid' => $uuid,
                            'sesskey' => sesskey(),
                            'returnurl' => $PAGE->url->out(false)
                        ]);

                        $html .= html_writer::start_div('list-group-item p-2 d-flex flex-column border rounded mb-2 bg-white shadow-sm');
                        $html .= html_writer::tag('div', html_writer::tag('strong', s($title)), ['class' => 'small text-dark mb-1']);
                        if ($addedby) {
                            $html .= html_writer::tag('div', s($addedby), ['class' => 'extra-small text-muted mb-2']);
                        }

                        $html .= html_writer::start_div('d-flex w-100 justify-content-between align-items-center');
                        $html .= html_writer::tag('a', get_string('open_in_app', 'block_bibliotech'), [
                            'href' => $applink,
                            'class' => 'btn btn-sm btn-info text-white py-0 px-2 small font-weight-bold',
                            'title' => get_string('open_in_app_title', 'block_bibliotech')
                        ]);

                        if ($canaddshared) {
                            $html .= html_writer::tag('a', get_string('remove', 'block_bibliotech'), [
                                'href' => $removeurl,
                                'class' => 'btn btn-sm btn-outline-danger py-0 px-2 small',
                                'onclick' => "return confirm(" . json_encode(get_string('remove_confirm_shared', 'block_bibliotech')) . ");"
                            ]);
                        }
                        $html .= html_writer::end_div();
                        $html .= html_writer::end_div();
                    }
                    $html .= html_writer::end_div();
                } else {
                    $html .= html_writer::tag('div', get_string('no_shared_resources', 'block_bibliotech'), ['class' => 'small text-muted p-1 text-center']);
                }

                $html .= html_writer::end_div(); // End card body
                $html .= html_writer::end_div(); // End collapse
                $html .= html_writer::end_div(); // End card
            }

            // SECTION 2: Personal Quicklinks
            $personalcollapseid = 'collapse_personal_' . $instanceid;
            $ispersonalexpanded = !empty($personalresources) || empty($sharedresources);

            $html .= html_writer::start_div('card border-secondary mb-2 shadow-sm');
            
            // Card Header
            $html .= html_writer::start_div('card-header p-2 bg-light d-flex justify-content-between align-items-center');
            $html .= html_writer::tag('button', get_string('section_personal_quicklinks', 'block_bibliotech'), [
                'class' => 'btn btn-link text-dark p-0 font-weight-bold small text-left',
                'data-toggle' => 'collapse',
                'data-target' => '#' . $personalcollapseid,
                'aria-expanded' => $ispersonalexpanded ? 'true' : 'false',
                'aria-controls' => $personalcollapseid
            ]);
            $html .= html_writer::tag('span', get_string('total_badge', 'block_bibliotech', count($personalresources)), ['class' => 'badge badge-secondary badge-pill']);
            $html .= html_writer::end_div();

            // Card Body / Collapsible Content
            $html .= html_writer::start_div('collapse ' . ($ispersonalexpanded ? 'show' : ''), [
                'id' => $personalcollapseid,
                'data-parent' => '#' . $accordionid
            ]);
            $html .= html_writer::start_div('card-body p-2');

            if (!empty($personalresources)) {
                $html .= html_writer::start_div('list-group list-group-flush');
                foreach ($personalresources as $res) {
                    $uuid = !empty($res['uuid']) ? $res['uuid'] : (!empty($res['id']) ? $res['id'] : '');
                    $kind = !empty($res['kind']) ? $res['kind'] : 'book';
                    $title = !empty($res['title']) ? $res['title'] : 'Bibliotech Publication';

                    $applink = "bibliotech://publication/{$kind}/{$uuid}";
                    $removeurl = new moodle_url('/blocks/bibliotech/action.php', [
                        'instanceid' => $instanceid,
                        'action' => 'remove',
                        'scope' => 'personal',
                        'uuid' => $uuid,
                        'sesskey' => sesskey(),
                        'returnurl' => $PAGE->url->out(false)
                    ]);

                    $html .= html_writer::start_div('list-group-item p-2 d-flex flex-column border rounded mb-2 bg-white shadow-sm');
                    $html .= html_writer::tag('div', html_writer::tag('strong', s($title)), ['class' => 'small text-dark mb-2']);

                    $html .= html_writer::start_div('d-flex w-100 justify-content-between align-items-center');
                    $html .= html_writer::tag('a', get_string('open_in_app', 'block_bibliotech'), [
                        'href' => $applink,
                        'class' => 'btn btn-sm btn-info text-white py-0 px-2 small font-weight-bold',
                        'title' => get_string('open_in_app_title', 'block_bibliotech')
                    ]);

                    $html .= html_writer::tag('a', get_string('remove', 'block_bibliotech'), [
                        'href' => $removeurl,
                        'class' => 'btn btn-sm btn-outline-danger py-0 px-2 small',
                        'onclick' => "return confirm(" . json_encode(get_string('remove_confirm_personal', 'block_bibliotech')) . ");"
                    ]);
                    $html .= html_writer::end_div();
                    $html .= html_writer::end_div();
                }
                $html .= html_writer::end_div();
            } else {
                $html .= html_writer::tag('div', get_string('no_personal_resources', 'block_bibliotech'), ['class' => 'small text-muted p-1 text-center']);
            }

            $html .= html_writer::end_div(); // End card body
            $html .= html_writer::end_div(); // End collapse
            $html .= html_writer::end_div(); // End card

            $html .= html_writer::end_div(); // End accordion

            // Require AMD JS helper for modal integration
            $deeplinkurl = \local_bibliotech\lti_manager::get_deeplink_url(!empty($COURSE->id) ? $COURSE->id : SITEID);
            $PAGE->requires->js_call_amd('block_bibliotech/block_helper', 'init', [$instanceid, sesskey(), $CFG->wwwroot, $deeplinkurl]);

        } else {
            // Unauthorized User UI: Subscription Call to Action (CTA)
            $html .= html_writer::start_div('card border-info p-3 mb-2 shadow-sm text-center');
            $html .= html_writer::tag('h5', get_string('subscribe_cta_heading', 'local_bibliotech'), ['class' => 'card-title text-info font-weight-bold mb-2']);
            $html .= html_writer::tag('p', get_string('subscribe_cta_text', 'local_bibliotech'), ['class' => 'card-text small text-muted mb-3']);
            $html .= html_writer::tag('a', get_string('subscribe_now_button', 'local_bibliotech'), [
                'href' => $subscribeurl,
                'class' => 'btn btn-success btn-block font-weight-bold',
                'target' => '_blank'
            ]);
            $html .= html_writer::end_div();
        }

        $html .= html_writer::end_div();

        $this->content->text = $html;

        return $this->content;
    }
}
