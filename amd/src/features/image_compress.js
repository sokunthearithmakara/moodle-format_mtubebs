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
 * Client-side raster image compression via the Canvas API.
 *
 * @module     format_mtubebs/features/image_compress
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const JPEG_QUALITY = 0.85;
const COMPRESS_EXTENSIONS = ['png', 'jpe', 'jpeg', 'jpg'];

/**
 * Extract the lowercase file extension.
 *
 * @param {string} filename
 * @returns {string}
 */
export const getExtension = (filename) => {
    const parts = filename.split('.');
    return parts.length > 1 ? parts.pop().toLowerCase() : '';
};

/**
 * Read a blob as a base64 string without the data URL prefix.
 *
 * @param {Blob} blob
 * @returns {Promise<string>}
 */
export const blobToBase64 = (blob) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
        const result = String(reader.result || '');
        const base64 = result.includes(',') ? result.split(',')[1] : result;
        resolve(base64);
    };
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(blob);
});

/**
 * Load a file into an HTMLImageElement.
 *
 * @param {File|Blob} file
 * @returns {Promise<HTMLImageElement>}
 */
const loadImage = (file) => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
        URL.revokeObjectURL(url);
        resolve(img);
    };
    img.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Image load failed'));
    };
    img.src = url;
});

/**
 * Detect whether canvas pixels include transparency.
 *
 * @param {CanvasRenderingContext2D} ctx
 * @param {number} width
 * @param {number} height
 * @returns {boolean}
 */
const canvasHasAlpha = (ctx, width, height) => {
    const imageData = ctx.getImageData(0, 0, width, height);
    for (let i = 3; i < imageData.data.length; i += 4) {
        if (imageData.data[i] < 255) {
            return true;
        }
    }
    return false;
};

/**
 * Compute scaled dimensions for canvas output.
 *
 * @param {number} width
 * @param {number} height
 * @param {number} maxEdge Longest edge of the output.
 * @returns {{width: number, height: number}}
 */
const scaleDimensions = (width, height, maxEdge) => {
    const scale = Math.min(1, maxEdge / Math.max(width, height));
    return {width: Math.round(width * scale), height: Math.round(height * scale)};
};

/**
 * Draw an image onto a canvas, downscaling when needed.
 *
 * @param {HTMLImageElement} img
 * @param {number} maxEdge Longest edge of the output.
 * @returns {{canvas: HTMLCanvasElement, ctx: CanvasRenderingContext2D, width: number, height: number}}
 */
const drawToCanvas = (img, maxEdge) => {
    const {width, height} = scaleDimensions(img.naturalWidth, img.naturalHeight, maxEdge);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, width, height);

    return {canvas, ctx, width, height};
};

/**
 * Encode a canvas to a blob.
 *
 * @param {HTMLCanvasElement} canvas
 * @param {string} type
 * @param {number|null} quality
 * @returns {Promise<Blob>}
 */
const canvasToBlob = (canvas, type, quality = null) => new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
        if (blob) {
            resolve(blob);
            return;
        }
        reject(new Error('Canvas encoding failed'));
    }, type, quality);
});

/**
 * Compress a raster image before upload.
 *
 * @param {File} file
 * @param {number} maxEdge Longest edge of the output.
 * @returns {Promise<{blob: Blob, filename: string}>}
 */
const compressRasterFile = async(file, maxEdge) => {
    const ext = getExtension(file.name);
    const img = await loadImage(file);
    const {canvas, ctx, width, height} = drawToCanvas(img, maxEdge);
    const basename = file.name.replace(/\.[^.]+$/, '');

    if (ext === 'png' && canvasHasAlpha(ctx, width, height)) {
        const blob = await canvasToBlob(canvas, 'image/png');
        return {blob, filename: `${basename}.png`};
    }

    const blob = await canvasToBlob(canvas, 'image/jpeg', JPEG_QUALITY);
    return {blob, filename: `${basename}.jpg`};
};

/**
 * Prepare the upload payload, compressing supported raster types when possible.
 *
 * @param {File} file
 * @param {{maxEdge: number, onCompressError: Function}} options
 * @returns {Promise<{blob: Blob, filename: string}>}
 */
export const prepareUploadPayload = async(file, {maxEdge, onCompressError}) => {
    const ext = getExtension(file.name);
    if (!COMPRESS_EXTENSIONS.includes(ext)) {
        return {blob: file, filename: file.name};
    }

    try {
        return await compressRasterFile(file, maxEdge);
    } catch (error) {
        await onCompressError(error);
        return {blob: file, filename: file.name};
    }
};
