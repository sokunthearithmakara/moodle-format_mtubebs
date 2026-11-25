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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace format_mtubebs\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_form\filetypes_util;
use moodle_exception;
use moodle_url;

/**
 * Replace the course overview image from an uploaded file.
 *
 * @package    format_mtubebs
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_course_image extends external_api {
    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'filename' => new external_value(PARAM_FILE, 'File name'),
            'filecontent' => new external_value(PARAM_RAW, 'Base64 encoded file content'),
        ]);
    }

    /**
     * Save a new course overview image.
     *
     * @param int $courseid Course id.
     * @param string $filename Uploaded file name.
     * @param string $filecontent Base64 encoded file content.
     * @return array
     */
    public static function execute(int $courseid, string $filename, string $filecontent): array {
        global $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/format/mtubebs/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'filename' => $filename,
            'filecontent' => $filecontent,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);
        require_capability('moodle/course:changesummary', $context);

        $overviewfilesoptions = course_overviewfiles_options($params['courseid']);
        if ($overviewfilesoptions === null) {
            throw new moodle_exception('courseimageuploaderror', 'format_mtubebs');
        }

        $filename = clean_param($params['filename'], PARAM_FILE);
        if ($filename === '') {
            throw new moodle_exception('courseimageuploaderror', 'format_mtubebs');
        }

        $util = new filetypes_util();
        if (!$util->is_allowed_file_type($filename, format_mtubebs_course_image_allowed_extensions())) {
            throw new moodle_exception('courseimageuploaderror', 'format_mtubebs');
        }

        $content = base64_decode($params['filecontent'], true);
        if ($content === false || $content === '') {
            throw new moodle_exception('courseimageuploaderror', 'format_mtubebs');
        }

        $maxbytes = $overviewfilesoptions['maxbytes'] ?? 0;
        if (empty($maxbytes)) {
            $maxbytes = $CFG->maxbytes;
        }
        if ($maxbytes > 0 && strlen($content) > $maxbytes) {
            throw new moodle_exception('maxbytes', 'error');
        }

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'course', 'overviewfiles', 0);

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ];
        $file = $fs->create_file_from_string($filerecord, $content);

        \cache::make('core', 'course_image')->delete($params['courseid']);

        $imageurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            null,
            $file->get_filepath(),
            $file->get_filename()
        )->out(true);

        return [
            'success' => true,
            'imageurl' => $imageurl,
        ];
    }

    /**
     * Webservice return shape.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the image was saved'),
            'imageurl' => new external_value(PARAM_URL, 'URL of the saved course image'),
        ]);
    }
}
