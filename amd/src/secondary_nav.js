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
 * Helpers for scraping Moodle core secondary navigation into MooTube Basic UI.
 *
 * @module     format_mtubebs/secondary_nav
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

/** Link selectors covering legacy moremenu markup and Moodle 5 React Nav. */
const LINK_SELECTORS = [
    'ul.more-nav > li.nav-item:not(.dropdownmoremenu) a[role="menuitem"]',
    '[data-region="moredropdown"] a.dropdown-item[role="menuitem"]',
    'li.dropdown:not(.dropdownmoremenu) .dropdown-menu a[role="menuitem"]',
];

/**
 * Collect secondary navigation links from a core nav container, without the course home tab.
 *
 * @param {HTMLElement|JQuery} root Secondary navigation root (e.g. .secondary-navigation).
 * @returns {{name: string, href: string}[]}
 */
export const collectSecondaryNavLinks = (root) => {
    const $root = $(root);
    const seen = new Set();
    const links = [];

    LINK_SELECTORS.forEach((selector) => {
        $root.find(selector).each(function() {
            const $link = $(this);
            const href = $link.attr('href');
            if (!href || href.startsWith('#')) {
                return;
            }

            if ($link.closest('li[data-key]').data('key') === 'coursehome') {
                return;
            }
            if (seen.has(href)) {
                return;
            }

            seen.add(href);
            links.push({
                name: $link.text().trim(),
                href,
            });
        });
    });

    return links;
};

/**
 * Render collected links as dropdown menu list items.
 *
 * @param {{name: string, href: string}[]} links Collected navigation links.
 * @returns {string} HTML for <li> dropdown items.
 */
export const renderSecondaryNavDropdown = (links) => links.map((link) =>
    `<li><a class="dropdown-item" href="${link.href}">${link.name}</a></li>`
).join('');
