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
 * Course activity search modal and result navigation.
 *
 * @module     format_mtubebs/features/search
 * @copyright  2025 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import {callWebservice} from 'format_mtubebs/ajax_retry';
import ModalFactory from 'core/modal';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import {animateModal, elems} from '../ui';
import {notifyError} from 'format_mtubebs/error_toast';
import {getString} from 'core/str';
import {registerModalType, syncModalUrl, clearModalUrl} from 'format_mtubebs/modal_launcher';
import {emptyModalHeader} from './modal_fullscreen';

/** @type {Object<number, Array>} Cached CMS lists keyed by course id. */
const cmsCache = {};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const idSet = (value) => String(value || '')
    .split(',')
    .map((id) => parseInt(id, 10))
    .filter((id) => id > 0);

const uniqueById = (items) => {
    const seen = new Map();
    items.forEach((item) => {
        const raw = item?.id;
        if (raw === undefined || raw === null || raw === '') {
            return;
        }
        const id = (typeof raw === 'number' || /^\d+$/.test(String(raw))) ? Number(raw) : String(raw);
        if (!item.name || seen.has(id)) {
            return;
        }
        seen.set(id, {id, name: String(item.name)});
    });
    return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name));
};

/**
 * Fetch and cache the searchable CMS list for a course.
 *
 * @param {number} courseid Course id.
 * @return {Promise<Array|null>}
 */
const fetchSearchableCms = async(courseid) => {
    if (cmsCache[courseid]) {
        return cmsCache[courseid];
    }

    const cms = await callWebservice([{
        methodname: 'format_mtubebs_get_cms',
        args: {
            data: JSON.stringify({courseid}),
        },
    }]);

    if (!cms?.data) {
        return null;
    }

    const searchablecms = JSON.parse(cms.data);
    searchablecms.sort((a, b) => {
        if (a.sectionname < b.sectionname) {
            return -1;
        }
        if (a.sectionname > b.sectionname) {
            return 1;
        }
        if (a.name < b.name) {
            return -1;
        }
        if (a.name > b.name) {
            return 1;
        }
        return 0;
    });

    cmsCache[courseid] = searchablecms;
    return searchablecms;
};

/**
 * Fill one select from option records.
 *
 * @param {JQuery} $select
 * @param {Array<{id: number|string, name: string}>} options
 * @param {string} allLabel
 */
const fillSelect = ($select, options, allLabel) => {
    const current = $select.val();
    $select.empty().append(`<option value="">${escapeHtml(allLabel)}</option>`);
    options.forEach((option) => {
        $select.append(`<option value="${option.id}">${escapeHtml(option.name)}</option>`);
    });
    if (current && $select.find(`option[value="${current}"]`).length) {
        $select.val(current);
    }
    $select.closest('[data-region]').toggleClass('d-none', options.length === 0);
};

/**
 * Render search results and filter choices from a CMS list.
 *
 * @param {JQuery} searchroot Modal root.
 * @param {Array} searchablecms CMS records.
 * @param {Object} strings
 * @return {Promise<void>}
 */
const renderSearchResults = async(searchroot, searchablecms, strings) => {
    const searchablecmshtml = await Templates.render('format_mtubebs/searchablecms', {
        cms: searchablecms,
    });
    searchroot.find('.search-results-wrapper').html(searchablecmshtml);

    const types = uniqueById(searchablecms.map((item) => ({
        id: item.modname,
        name: item.modnamelabel,
    })));
    fillSelect(searchroot.find('[data-field="search-mod"]'), types, strings.all);
    fillSelect(
        searchroot.find('[data-field="search-competency"]'),
        uniqueById(searchablecms.flatMap((item) => item.competencies || [])),
        strings.all
    );
    fillSelect(
        searchroot.find('[data-field="search-outcome"]'),
        uniqueById(searchablecms.flatMap((item) => item.outcomes || [])),
        strings.all
    );

    const tags = uniqueById(searchablecms.flatMap((item) => item.tags || []));
    searchroot.find('[data-region="search-tags"]').toggleClass('d-none', tags.length === 0).data('tags', tags);

    const hasColumnFilters = searchroot.find('.search-filter-field').not('.d-none').length > 0;
    const $toggle = searchroot.find('[data-action="toggle-search-filters"]');
    const $filters = searchroot.find('.search-filters');
    $toggle.toggleClass('d-none', !hasColumnFilters);
    if (hasColumnFilters && $toggle.attr('aria-expanded') !== 'false') {
        $filters.removeClass('d-none');
    } else {
        $filters.addClass('d-none');
        if (!hasColumnFilters) {
            $toggle.attr('aria-expanded', 'false');
        }
    }
};

const selectedTagIds = (searchroot) => searchroot.find('[data-region="search-tag-chips"] .search-tag-chip')
    .map(function() {
        return parseInt($(this).attr('data-tagid'), 10);
    })
    .get()
    .filter((id) => id > 0);

const renderTagSuggestions = (searchroot) => {
    const tags = searchroot.find('[data-region="search-tags"]').data('tags') || [];
    const query = String(searchroot.find('[data-field="search-tag"]').val() || '').trim().toLowerCase();
    const selected = new Set(selectedTagIds(searchroot));
    const matches = tags.filter((tag) => !selected.has(tag.id) && (!query || tag.name.toLowerCase().includes(query)));
    const $list = searchroot.find('[data-region="search-tag-suggestions"]');
    const input = searchroot.find('[data-field="search-tag"]').get(0);
    if (document.activeElement !== input || !matches.length) {
        $list.addClass('d-none').empty();
        return;
    }
    $list.removeClass('d-none').html(matches.slice(0, 8).map((tag) => (
        `<button type="button" class="list-group-item list-group-item-action py-1" data-tagid="${tag.id}">`
        + `${escapeHtml(tag.name)}</button>`
    )).join(''));
};

/**
 * Show activities that match every active filter.
 *
 * @param {JQuery} searchroot
 * @param {Object} strings
 */
const applyFilters = (searchroot, strings) => {
    const query = String(searchroot.find('[data-field="search-query"]').val() || '').trim().toLowerCase();
    const hasQuery = query.length >= 3;
    const modname = String(searchroot.find('[data-field="search-mod"]').val() || '');
    const competencyid = parseInt(searchroot.find('[data-field="search-competency"]').val(), 10) || 0;
    const outcomeid = parseInt(searchroot.find('[data-field="search-outcome"]').val(), 10) || 0;
    const tagids = selectedTagIds(searchroot);
    const hasFilter = Boolean(modname || competencyid || outcomeid || tagids.length);
    const $hint = searchroot.find('.search-filter-hint');
    const $results = searchroot.find('.search-result');

    if (!hasQuery && !hasFilter) {
        $results.addClass('d-none');
        $hint.removeClass('d-none').text(strings.hint);
        return;
    }

    let visible = 0;
    $results.each(function() {
        const $row = $(this);
        const name = String($row.attr('data-name') || '').toLowerCase();
        const rowTags = idSet($row.attr('data-tagids'));
        const matches = (!hasQuery || name.includes(query))
            && (!modname || $row.attr('data-type') === modname)
            && (!competencyid || idSet($row.attr('data-competencyids')).includes(competencyid))
            && (!outcomeid || idSet($row.attr('data-outcomeids')).includes(outcomeid))
            && tagids.every((id) => rowTags.includes(id));
        $row.toggleClass('d-none', !matches);
        if (matches) {
            visible += 1;
        }
    });

    if (visible) {
        $hint.addClass('d-none');
    } else {
        $hint.removeClass('d-none').text(strings.nomatches);
    }
};

const buildFilterField = (label, region, control) => (
    `<div class="search-filter-field d-none" data-region="${region}">`
    + `<label class="form-label small fw-bold mb-1">${escapeHtml(label)}</label>${control}</div>`
);

// Handle search.
const searchmodal = async() => {
    let searchmodal;
    let searchroot;
    const strings = {
        search: await getString('search', 'format_mtubebs'),
        all: await getString('all', 'moodle'),
        activitytype: await getString('coursecontentmodulefilter', 'format_mtubebs'),
        competency: await getString('coursecontentcompetencyfilter', 'format_mtubebs'),
        outcome: await getString('coursecontentoutcomefilter', 'format_mtubebs'),
        tags: await getString('tags', 'core'),
        addtag: await getString('searchaddtag', 'format_mtubebs'),
        hint: await getString('searchfilterhint', 'format_mtubebs'),
        nomatches: await getString('searchnomatches', 'format_mtubebs'),
    };

    $(document).on('click', '#search-toggle', async function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const courseid = M.cfg.courseId;

        if (searchmodal) {
            if (searchmodal.getRoot().hasClass('show')) {
                return;
            }
            searchroot = searchmodal.getRoot();
        } else {
            searchmodal = await ModalFactory.create({
                body: `<div class="search-filters border-bottom px-3 py-2 d-none">
                        <div class="search-filter-row">
                            ${buildFilterField(strings.competency, 'search-competency',
                                '<select class="form-select" data-field="search-competency"></select>')}
                            ${buildFilterField(strings.outcome, 'search-outcome',
                                '<select class="form-select" data-field="search-outcome"></select>')}
                            ${buildFilterField(strings.tags, 'search-tags',
                                `<div data-region="search-tag-chips" class="d-flex flex-wrap gap-1 mb-1"></div>
                                <div class="search-tag-field">
                                    <input type="text" class="form-control" data-field="search-tag"
                                     placeholder="${escapeHtml(strings.addtag)}" autocomplete="off">
                                    <div class="list-group search-tag-suggestions d-none"
                                     data-region="search-tag-suggestions"></div>
                                </div>`)}
                        </div>
                    </div>
                    <p class="search-filter-hint text-muted text-center py-4 mb-0">${escapeHtml(strings.hint)}</p>
                    <div class="search-results-wrapper"></div>`,
                show: false,
                removeOnClose: false,
                isVerticallyCentered: true,
                large: true,
            });

            searchroot = searchmodal.getRoot();
            searchroot.attr('id', 'search-wrapper');
            searchroot.find('.modal-dialog').addClass('modal-lg');
            searchroot.find('.modal-body').addClass('p-0');
            emptyModalHeader(searchroot)
                .append(`<div class="d-flex align-items-center w-100">
                        <div class="input-group search-input-group">
                            <select class="form-select d-none" data-field="search-mod" data-region="search-mod"
                             aria-label="${escapeHtml(strings.activitytype)}"></select>
                            <input type="text" class="form-control" data-field="search-query"
                             placeholder="${escapeHtml(strings.search)}">
                        </div>
                        <button class="btn-icon icons-collapse-expand btn ms-2" type="button"
                         data-action="toggle-search-filters" aria-expanded="true">
                        <i class="fa fa-filter"></i>
                        </button>
                        <button class="btn-icon icons-collapse-expand btn ms-2 p-0" type="button" data-action="hide">
                        <i class="fa fa-times"></i></button>
                    </div>`);

            searchroot.on(ModalEvents.shown, function() {
                animateModal(searchroot, 'in');
                syncModalUrl({modal: 'search'});
            });
        }

        searchroot.off(ModalEvents.hidden + '.searchrestore').on(ModalEvents.hidden + '.searchrestore', function() {
            animateModal(searchroot, 'out');
            clearModalUrl();
        });

        searchmodal.show();

        try {
            const searchablecms = await fetchSearchableCms(courseid);
            if (searchablecms) {
                await renderSearchResults(searchroot, searchablecms, strings);
                applyFilters(searchroot, strings);
            } else {
                searchroot.find('.search-results-wrapper').html('');
                searchroot.find('.search-filters').addClass('d-none');
                searchroot.find('[data-action="toggle-search-filters"]').addClass('d-none')
                    .attr('aria-expanded', 'false');
            }
        } catch (err) {
            notifyError(err);
        }
    });

    $(document).on('click', '#search-wrapper [data-action="toggle-search-filters"]', function(e) {
        e.preventDefault();
        const $panel = $('#search-wrapper .search-filters');
        $panel.toggleClass('d-none');
        $(this).attr('aria-expanded', $panel.hasClass('d-none') ? 'false' : 'true');
    });

    $(document).on('input', '#search-wrapper [data-field="search-query"]', function(e) {
        e.preventDefault();
        applyFilters($('#search-wrapper'), strings);
    });

    $(document).on(
        'change',
        '#search-wrapper [data-field="search-mod"], #search-wrapper [data-field="search-competency"], '
        + '#search-wrapper [data-field="search-outcome"]',
        function() {
            applyFilters($('#search-wrapper'), strings);
        }
    );

    $(document).on('focus input', '#search-wrapper [data-field="search-tag"]', function() {
        renderTagSuggestions($('#search-wrapper'));
    });

    $(document).on('blur', '#search-wrapper [data-field="search-tag"]', function() {
        const root = $('#search-wrapper');
        window.setTimeout(() => {
            root.find('[data-region="search-tag-suggestions"]').addClass('d-none');
        }, 200);
    });

    $(document).on('click', '#search-wrapper [data-region="search-tag-suggestions"] [data-tagid]', function(e) {
        e.preventDefault();
        const root = $('#search-wrapper');
        const tagid = parseInt($(this).attr('data-tagid'), 10);
        const tags = root.find('[data-region="search-tags"]').data('tags') || [];
        const selected = new Set(selectedTagIds(root));
        selected.add(tagid);
        root.find('[data-region="search-tag-chips"]').html(tags.filter((tag) => selected.has(tag.id)).map((tag) => (
            `<button type="button" class="btn btn-sm btn-secondary search-tag-chip" data-tagid="${tag.id}">`
            + `${escapeHtml(tag.name)} <span aria-hidden="true">&times;</span></button>`
        )).join(''));
        root.find('[data-field="search-tag"]').val('');
        renderTagSuggestions(root);
        applyFilters(root, strings);
    });

    $(document).on('click', '#search-wrapper .search-tag-chip', function(e) {
        e.preventDefault();
        $(this).remove();
        const root = $('#search-wrapper');
        renderTagSuggestions(root);
        applyFilters(root, strings);
    });

    $(document).on('click', '.search-result-section[data-id]', function(e) {
        e.preventDefault();
        const sectionid = $(this).data('id');
        searchmodal.hide();
        elems.header.find(`[data-section="${sectionid}"]`).first().trigger('click');
    });

    registerModalType('search', {
        isAvailable: () => Boolean(document.getElementById('search-toggle')),
        launch: () => {
            const toggle = document.getElementById('search-toggle');
            if (!toggle) {
                return false;
            }
            toggle.click();
            return true;
        },
    });
};

export default {
    searchmodal,
};
