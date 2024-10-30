=== Plugin Name ===
Contributors: itmatio
Donate link: https://www.interserve.org/
Tags: rss, jobs, stories
Stable tag: trunk
Requires at least: 3.5.1
Tested up to: 5.2.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Interserve Job Openings, office contact details, stories, and other goodies automatically pulled from
our data feed and displayed on your site.

== Description ==

Creates custom post types for the various data feeds from https://data.interserve.org and automatically
imports data from those feeds once a day.

Shortcodes

- [isdata_contact_list link="type"] shows an unordered list of office names in alpha order.
- [isdata_contact_map] shows a google map with pins for each office, linking to the office detail page
- [isdata_contact_nearest link="type"] shows a link to an office in the same country as the user IP address. Needs the geoip extension installed on the server.
- [isdata_job_related n="10" location="locations" profession="professions" duration="durations"] shows a table of jobs that are similar to the current displayed job or search terms, or if there are no current terms it shows the newest priority jobs. Each job links to its detail page. Each taxonomy term links to a related search.
- [isdata_job_list n="10" location="locations" profession="professions" duration="durations"] shows a table of jobs exactly matching the currently displayed job or search terms. If there are no current terms, it shows the newest priority jobs. Each job links to its detail page. Each taxonomy term links to a related search.
- [isdata_job_search] shows a search form with location, profession, duration and a free text field. It is aware of any pre-set taxonomy terms and will automatically set them in the field values.
  Use this with an [isdata_job_list] field to implement a job search page.
- [isdata_story_related n="10" location="locations" profession="professions" theme="themes"] ditto for stories
- [isdata_story_list n="10" location="locations" profession="professions" theme="themes"] ditto for stories
- [isdata_story_search] ditto for stories
- [isdata_profession_list] shows an unordered list of the profession taxonomy, with counts
- [isdata_location_list] shows an unordered list of the location taxonomy, with counts
- [isdata_duration_list] shows an unordered list of the duration taxonomy, with counts
- [isdata_theme_list] shows an unordered list of the theme taxonomy, with counts
- [isdata_statistics] shows a two column table of random stats about Interserve.
  Use [isdata_statistics name="Updated"] to show the date and time the stats were last updated on data.interserve.org.
  Use name="Jobs" to return just the number for the count of job openings. This works for any of the other numbers reported by the bare tag.
- [isdata_child_pages] shows the pages that are children of this page. It is not related to any of the isdata data sets: it is included as a utility / tool to help build navigation of your site.

In the above, shortcode parameters are

- n="10" means restrict the number of items in the list to 10. Range 1..50
- location="locations" means display only items matching the taxonomy slugs for locations. You can use a comma
  separated list like location="central-asia,india" if you want more than one location.
  If it doesn't work as expected, check the spelling of the shortcode slug by hovering over a link to that
  term on the public part of the site. Omit this parameter to show all locations.
  A slug is a computer friendly rendering of the human friendly name eg the slug for "Central Asia" is "central-asia"
- profession="professions" as above eg profession="education,other"
- duration="durations" as above eg duration="elective"
- theme="themes" as above eg theme="Prayer"
- contact link="type": type can be "direct", which makes a direct link to the office web site (if provided); "donate", which links to the donations link (if provided). Anything else (or omitting the argument) links to the contact detail page / post for the office.

Widgets

- Job Openings: shows a context aware list of related job openings with links to view them. You can set the number of openings to display.
- Stories: shows a context aware list of related stories with links to view them. You can set the number of stories to display.
- Child Pages: shows pages that are children of the current page

Settings

The Plugin settings page is in the Settings menu: Settings: Interserve Data

Styling

The html tag surrounding each content type has a isdata_job_meta, isdata_story_meta etc class applied to it to help you customise styling. Where relevant, inner elements also have isdata_* classes you can use to further refine your styles.

== Installation ==

Requires at least PHP7

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Edit wp-config.php and add define("DISABLE_WP_CRON", true);
1. Set up a cron job to call the cron.php (see the codex for wp_cron)

The feed update takes a long time: several minutes on first load, and 20+ seconds for each refresh so it needs to be run as a real system cron job that calls wp-cron.php once every 12 hours at the most. See [Why WP-Cron Sucks](http://www.lucasrolff.com/wordpress/why-wp-cron-sucks/).
Note: the feed data is only updated once a day, so calling more often than once every 12 hours is a waste of CPU time and bandwidth. Repeat abusers will be blacklisted.

The plugin settings page (in the Settings main menu) has two fields for each custom post type. Use this to insert raw html or a shortcode into the page above or below a singular job posting, story, contact etc.

If a google maps API key is provided, a google map showing the location of each office will be displayed underneath the office contact details. Sign up for the Google Maps platform before your first sync / update to ensure offices are geocoded correctly.

== Frequently Asked Questions ==

- You must not have a page with a permalink the same as one of the isdata post types, or undefined weirdness will occur. So a page named /job/ or /story/ etc will not work as expected. Try using the plural form or making some other change to the permalink eg /jobs/ or /openings/ instead of /job/; /our-stories/ instead of /story/; /where-we-work/ instead of /locations/ etc
- Typical use on a page would be just a blank page with two related shortcodes eg [isdata_job_search] [isdata_job_list] which provide a search form and a search results page
- The widgets show related jobs or stories. They are smart: they know what other jobs / stories are currently displayed on the page so they adapt to show similar ones with the same location / duration / profession / etc
- iThemes Security plugin may cause the search form to stop working. When you click the Search button it will give a 403 forbidden message. To fix it, disable "Filter Suspicious Query Strings in the URL" in the System Tweaks of the iThemes Security settings.

== Changelog ==

= 0.1 24 March 2013 =
* Initial release ok

= 0.1.1 23 Oct 2014 =
* Bug fix: curl problems with sslverify and http 1.1
* Bug fix: searching for job IDs was broken

= 1.0 29 Oct 2014 =
* Release to wordpress.org as an official plugin (for easier distribution of updates)

= 1.1 31 Jan 2017 =
* Fixes for PHP7.1 to remove php warnings
* search by job and story id in the search forms
* improvements to [isdata_statistics] so that single values can be used by themselves eg in big numbers
* add [isdata_contact_nearest] to display link to nearest office

= 1.1.5 21 Aug 2018 =
* add link="type" to isdata_contact shortcodes to allow direct linking to offices and donations pages

= 1.1.6 25 Aug 2018 =
* php 5.5 compatibility

= 1.1.7 15 Dec 2018 =
* Check for Wordpress 5.0.1 compatibility
* Add check for valid Google Maps API key before trying to geocode office addresses on sync

= 1.1.8 13 Jan 2019 =
* Prevent exception thrown if google geocode fails: so that the rest of the import can continue

= 1.2 1 Oct 2019 =
* Migrate to v3 data feeds
* Deleted Vision and Publication: data is no longer available
* Added Theme taxonomy to Stories

= to do =
* use Schema.org for job openings, organisation addresses, stories
