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
 * Print helpers for embedded user grade reports.
 *
 * @module     format_mtubebs/features/grade_report_print
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Find the grade report container within a modal or tab root.
 *
 * @param {HTMLElement|Document|null} root Search root.
 * @return {HTMLElement|null}
 */
export const findGradeReportContainer = (root) => {
    if (!root) {
        return null;
    }

    const scope = root.querySelector ? root : document;
    return scope.querySelector('.mtubebs-grade-report-content .user-report-container')
        || scope.querySelector('[id^="user-report-"]');
};

/**
 * Build stylesheet tags for the print iframe from the current page.
 *
 * @return {string}
 */
const buildStylesheetMarkup = () => {
    const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map((link) => link.href)
        .filter(Boolean)
        .map((href) => `<link rel="stylesheet" href="${href}">`)
        .join('');
    const inline = Array.from(document.querySelectorAll('style'))
        .map((style) => `<style>${style.textContent}</style>`)
        .join('');
    const printOnly = `<style>
        body {
            background: #fff;
            color: #000;
            margin: 1rem;
        }
        .user-grade {
            width: 100%;
        }
        .user-grade th.category a.toggle-category,
        .user-grade td.column-grade .action-menu {
            display: none !important;
        }
        @media print {
            body {
                margin: 0;
            }
        }
    </style>`;

    return `${links}${inline}${printOnly}`;
};

/**
 * Escape text for use in a document title.
 *
 * @param {string} title Raw title.
 * @return {string}
 */
const escapeTitle = (title) => title.replace(/[<>&"']/g, '');

/**
 * Print a user grade report table in a clean document (no modal chrome).
 *
 * @param {HTMLElement|null} container Grade report root element.
 * @param {Object} [options] Print options.
 * @param {string} [options.title] Document title for the print job.
 */
export const printGradeReport = (container, {title = ''} = {}) => {
    if (!container) {
        return;
    }

    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = 'border:0;height:0;position:fixed;right:0;bottom:0;width:0;visibility:hidden;';
    document.body.appendChild(iframe);

    const printWindow = iframe.contentWindow;
    const doc = printWindow.document;
    const safeTitle = escapeTitle(title || document.title);

    // Print the custom grade table with every feedback shown.
    const printable = container.cloneNode(true);
    printable.querySelectorAll('details').forEach((details) => {
        details.open = true;
    });

    doc.open();
    doc.write(`<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>${safeTitle}</title>
${buildStylesheetMarkup()}
</head>
<body class="path-grade-report-user grade-report-user pagelayout-report">
${printable.outerHTML}
</body>
</html>`);
    doc.close();

    const cleanup = () => {
        iframe.remove();
        printWindow.removeEventListener('afterprint', cleanup);
    };

    const triggerPrint = () => {
        printWindow.addEventListener('afterprint', cleanup);
        printWindow.focus();
        printWindow.print();
        window.setTimeout(() => {
            if (iframe.parentNode) {
                cleanup();
            }
        }, 1000);
    };

    iframe.addEventListener('load', () => window.setTimeout(triggerPrint, 300), {once: true});
};
