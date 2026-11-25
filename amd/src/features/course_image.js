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
 * Course overview image upload from the course header.
 *
 * @module     format_mtubebs/features/course_image
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {callWebservice} from 'format_mtubebs/ajax_retry';
import {add as addToast} from 'core/toast';
import {getString} from 'core/str';
import {notifyError} from 'format_mtubebs/error_toast';
import {blobToBase64, getExtension, prepareUploadPayload} from 'format_mtubebs/features/image_compress';

const MAX_EDGE = 1920;
const MAX_UPLOAD_BYTES = 8388608; // 8 MiB raw file before base64 (~11 MiB JSON payload).
const ACCEPT = '.gif,.jpe,.jpeg,.jpg,.png,.svg,.svgz,.webp';
const ALLOWED_EXTENSIONS = ['gif', 'jpe', 'jpeg', 'jpg', 'png', 'svg', 'svgz', 'webp'];

/**
 * Update all course image elements on the page.
 *
 * @param {string} imageurl
 */
const updateCourseImages = (imageurl) => {
    const url = `${imageurl}${imageurl.includes('?') ? '&' : '?'}rev=${Date.now()}`;
    document.querySelectorAll('#course-image, #course-image-background').forEach((node) => {
        node.src = url;
    });
};

/**
 * Upload a prepared file to the course overview image webservice.
 *
 * @param {number} courseid
 * @param {Blob} blob
 * @param {string} filename
 */
const uploadCourseImage = async(courseid, blob, filename) => {
    const filecontent = await blobToBase64(blob);
    const response = await callWebservice([{
        methodname: 'format_mtubebs_set_course_image',
        args: {
            courseid,
            filename,
            filecontent,
        },
    }]);

    if (!response?.success || !response.imageurl) {
        throw new Error(await getString('courseimageuploaderror', 'format_mtubebs'));
    }

    updateCourseImages(response.imageurl);
    await addToast(await getString('courseimageupdated', 'format_mtubebs'), {type: 'success'});
};

/**
 * Initialise course image editing controls.
 *
 * @param {number} courseid
 */
export const init = (courseid) => {
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = ACCEPT;
    fileInput.classList.add('visually-hidden');
    fileInput.setAttribute('aria-hidden', 'true');
    fileInput.tabIndex = -1;
    document.body.appendChild(fileInput);

    let uploading = false;

    fileInput.addEventListener('change', async() => {
        const file = fileInput.files?.[0];
        fileInput.value = '';

        if (!file || uploading) {
            return;
        }

        const ext = getExtension(file.name);
        if (!ALLOWED_EXTENSIONS.includes(ext)) {
            await addToast(await getString('courseimageuploaderror', 'format_mtubebs'), {type: 'danger'});
            return;
        }

        uploading = true;
        document.querySelectorAll('[data-action="edit-course-image"]').forEach((button) => {
            button.disabled = true;
            button.classList.add('course-image-edit-btn--loading');
        });

        try {
            const payload = await prepareUploadPayload(file, {
                maxEdge: MAX_EDGE,
                onCompressError: async() => {
                    await addToast(await getString('courseimagecompressfallback', 'format_mtubebs'), {type: 'warning'});
                },
            });
            if (payload.blob.size > MAX_UPLOAD_BYTES) {
                await addToast(await getString('courseimageuploaderror', 'format_mtubebs'), {type: 'danger'});
                return;
            }
            await uploadCourseImage(courseid, payload.blob, payload.filename);
        } catch (error) {
            if (error?.errorcode || (error?.message || '').trim()) {
                await notifyError(error);
            } else {
                await addToast(await getString('courseimageuploaderror', 'format_mtubebs'), {type: 'danger'});
            }
        } finally {
            uploading = false;
            document.querySelectorAll('[data-action="edit-course-image"]').forEach((button) => {
                button.disabled = false;
                button.classList.remove('course-image-edit-btn--loading');
            });
        }
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="edit-course-image"]');
        if (!button || uploading) {
            return;
        }
        event.preventDefault();
        fileInput.click();
    });
};

export default {
    init,
};
