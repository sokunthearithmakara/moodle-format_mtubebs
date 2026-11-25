# MooTube Basic

[![Moodle Plugin CI](https://github.com/sokunthearithmakara/moodle-format_mtubebs/actions/workflows/gha.yml/badge.svg)](https://github.com/sokunthearithmakara/moodle-format_mtubebs/actions/workflows/gha.yml)

MooTube Basic is a Moodle course format that shows a course one section at a time, with a course info panel and a menu for grades, deadlines and search, all without leaving the course page.

## Requirements

- Moodle 5.1 or later
- [Interactive Video](https://github.com/sokunthearithmakara/moodle-mod_interactivevideo) (`mod_interactivevideo`) 2026111000 or later

## Features

### Course page

- One section at a time, with a section menu and previous and next buttons that can stay at the top while scrolling.
- Choose what a section is called (chapter, unit, week and others), and show section numbers or short names.
- Pin the highlighted section to the top, with a label of your own.
- Reopen the section the learner visited last.
- Search the course's activities, filtered by activity type, outcome, competency and tag.
- Announcement at the top of the page, with an optional end date.
- Distraction-free mode for learners, or for everyone if the site administrator chooses so.

### Course info panel

- Course image, intro video and the content of the general section.
- Course contacts in the order you choose, each with a description such as a role or title.

### Side navigation

- News, Grade, Timeline, Badge and Completion items, plus your own links. Link URLs accept `{{userid}}` and `{{courseid}}`.
- Timeline: upcoming and overdue activities of the course.
- Grade: Moodle's user report, or a grade table with the status, due date, weight and feedback of each grade item.

### Activity pages

- Optional "Back to section" button and course index.
- From Moodle 5.3, optional activity navigation in the sticky footer.

### Section settings

- Short name, hide the section header from students, hide the description on the section page.
- Subsections can start collapsed or expanded, or never collapse.

### Themes and languages

- Works with Boost and includes adjustments for Academi, Adaptable, Moove, Nice and Trema.
- Language packs: Chinese (Simplified), English, French, German, Indonesian, Italian, Khmer, Portuguese (Brazil), Spanish (Mexico) and Vietnamese.

## Installation

1. Install `mod_interactivevideo` first.
2. Put this plugin's files in `course/format/mtubebs` (`public/course/format/mtubebs` on the server). With git, from the Moodle root:

   ```
   git clone https://github.com/sokunthearithmakara/moodle-format_mtubebs.git public/course/format/mtubebs
   ```

3. Go to **Site administration > Notifications**, or run `php admin/cli/upgrade.php`.

## Configuration

- **Site**: in the MooTube Basic settings under **Site administration > Plugins > Course formats**, set the default format options. If your theme uses its own page structure, also set the CSS selectors of the page nodes.
- **Course**: choose **MooTube Basic** as the course format in the course settings, then click the format options button. Options can be exported from one course and imported into another.

## Privacy

The plugin stores one user preference per course: the section the learner visited last. It is covered by the privacy API.

## Third-party libraries

[IconPicker](https://github.com/furcan/IconPicker) 1.5.0, MIT licence. See `thirdpartylibs.xml`.

## License

Copyright 2025 Sokunthearith Makara

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. See <https://www.gnu.org/licenses/>.
