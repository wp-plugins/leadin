=== Leadin ===
Contributors: andygcook, nelsonjoyce
Tags:  crm, contacts, lead tracking, click tracking, visitor tracking, analytics, marketing automation, inbound marketing, subscription, marketing, lead generation, mailchimp, constant contact, newsletter, popup, popover, email list, email, contacts database, contact form, forms, form widget, popup form
Requires at least: 3.7
Tested up to: 4.0
Stable tag: 2.0.1

Leadin is an easy-to-use marketing automation and lead tracking plugin for WordPress that helps you better understand your web site visitors.

== Description ==

= Get personal with your leads =

<a href="http://leadin.com" alt="WordPress marketing automation and lead tracking plugin">Leadin</a> is an easy-to-use marketing automation and lead tracking plugin for WordPress that helps you better understand your web site visitors.

When a person submits a form on your WordPress site, you want to know more about them. What pages they've visited, when they return, and what social networks they’re on. Our WordPress marketing automation and lead tracking plugin gives you the details you need to make your next move. Because business isn’t business unless it’s personal.

= How does it work? =

-1. When you activate the WordPress plugin, Leadin will track each anonymous visitor to your site with a cookie.
-2. Leadin automatically identifies and watches each existing form on your site for submissions.
-3. Once someone fills out any other form on your site, Leadin will identify that person with their email address. and add them to your contact list.
-4. You'll also receive an email with a link to the new contact record with all of their visit history.

= Multisite Compatible =

Leadin is fully Multisite compatible. The plugin will all data to each site's installaion just fine without requiring any additional setup.

= Who's using Leadin? =

**Alan Perlman**: *“I can use Leadin to get a sense of how engaged certain contacts are, and I can learn more about their behavior on my website to better drive the conversation and understand what they’re interested in or looking for.”*

<a href="http://www.extremeinbound.com/leadin-wordpress-crm-inbound-plugin/">Read more from Alan</a>


**Adam W. Warner**: *“…the Leadin plugin has been very useful so far in giving us an idea of the actual visitor paths to our contact forms vs. the paths we’ve intended.”*

<a href="http://thewpvalet.com/wordpress-lead-tracking/">Read more from Adam</a>


= Note: =

Leadin collects usage information about this plugin so that we can better serve our customers and know what features to add. By installing and activating the Leadin for WordPress plugin you agree to these terms.

== Installation ==

1. Upload the 'leadin' folder to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add an email address under 'Leadin' in your settings panel

== Frequently Asked Questions ==

= How does Leadin integrate with my forms? =

Leadin automatically integrates with your contact and comment forms that contain an email address field on your web site. There's no setup required.

= Where are my contact submission stored? =

Leadin creates a new contact in your Contacts Tabke whenever an email address is detected in your visitor's form submission.

There is no limit to the number of contacts you can store in your Contacts Table.

= Which contact form building plugins are supported? =

Leadin is intended to work with any HTML form out of the box, but does not support forms created by Javascript or loaded through an iFrame. 

To ensure quality we've tested the most popular WordPress form builder plugins.

= Tested + supported: =

- Contact Form 7
- JetPack
- Fast Secure Contact Form
- Contact Form
- Gravity Forms
- Formidable
- Ninja Forms
- Contact Form Clean and Simple
- SumoMe
- HubSpot
- Native WordPress comment forms
- Most custom forms

= Tested + unsupported: =

- Wufoo
- Easy Contact Forms
- Disqus comments
- Forms contained in an iFrame

= Does Leadin work on Multisite? =

You betcha! Leadin should work just fine on Multisite right out-of-the-box without requiring any additional setup.

== Screenshots ==

1. See the visit history of each contact.
2. Get an email notification for every new lead.
3. Leadin stats show you where your leads are coming from.
4. Segment your contact list based on page views and submissinos.
5. Collect more contacts with the pop-up subscribe widget.
6. Create custom tagged lists, choose the form triggers to add contacts and sync your contacts to third-party email services

== Changelog ==

- Current version: 2.0.1
- Current version release: 2014-09-01

= 2.0.1 (2014.09.01) =
= Enhancements =
- Removed "Who read my post" widget analytics from the post editor
- Separated backend from frontend code to speed up ajax calls on both sides

- Bug fixes
- Fixed bug when deleting specifically selected contacts looked like all the contacts were deleted on the page refresh
- Organic traffic and paid traffic sources are now parsing more accurately
- Credit card forms will add to the timeline now but will block all credit card information
- Bulk edited tags now push contacts to ESP lists when added
- Lists with existing contacts retroactively push email addresses to corresponding ESP list
- Renamed MailChimp Contact Sync + Constant Contact Sync to MailChimp Connect + Constant Contact Connect
- Fixed returning contacts vs. new contacts in dashboard widget
- Contact export works again
- Fixed insecure content warning on SSL
- Non-administrators no longer can see the Leadin menu links or pages
- Settings link missing from plugins list page
- Line break contact notifications previews
- Setup a mailto link on the contact notification email in the details header

= 2.0.0 (2014.08.11) =
= Enhancements =
- Create a custom tagged list based on form submission rules
- Ability to sync tagged contacts to a specific ESP list
- Filter lists by form selectors

- Bug fixes
- Fix contact export for selected contacts
- Text area line breaks in the contact notifications now show properly
- Contact numbers at top of list did not always match number in sidebar - fixed

= 1.3.0 (2014.07.14) =
= Enhancements =
- Multisite compatibility

= 1.2.0 (2014.06.25) =
- Bug fixes
- Contacts with default "contact status" were not showing up in the contact list
- WordPress admin backends secured with SSL can now be used with Leadin
- Namespaced the referrer parsing library for the Sources widget

= Enhancements =
- Leadin VIP program

= 1.1.1 (2014.06.20) =
- Bug fixes
- Emergency bug fix on activation caused by broken SVN merging

= 1.1.0 (2014.06.20) =
- Bug fixes
- Leadin subscriber email confirmations were not sending
- Removed smart contact segmenting for leads

= Enhancements =
- Added more contact status types for contacted + customer
- Setup collection for form IDs + classes

= 1.0.0 (2014.06.12) =
- Bug fixes
- Fixed sort by visits in the contacts list

= Enhancements =
- Contacts filtering
- Stats dashboard
- Sources

= 0.10.0 (2014.06.03) =
- Bug fixes
- Fixed original referrer in contact timeline
- Fixed unncessary queries on contact timeline
- Only run the update check if the version number is different than the saved number
- Remove "fakepath" from file path text in uploaded file input types

= Enhancements =
- Expire the subscribe cookie after a few weeks
- Ability to disable a subscribe notification
- Added jQuery validation to the subscribe pop-up
- Multi-select input support
- Block forms with credit card fields from capturing contact information
- Updated contact timeline views
- Updated new contact notification emails

= 0.9.3 (2014.05.19) =
- Bug fixes
- Fix for duplicate values being stored in the active power-ups option

= 0.9.2 (2014.05.16) =

= Enhancements =
- Overhaul of settings page to make it easier to see which settings go with each power-up
- Launched Leadin Beta Program

= 0.9.1 (2014.05.14) =
- Bug fixes
- Fixed pop-up location dropdown not defualting to saved options value
- Hooked subscribe widget into get_footer action instead of loop_end filter

= 0.9.0 (2014.05.12) =
- Bug fixes
- Remove leadin-css file enqueue call

= Enhancements =
- Show faces of people who viewed a post/page in the editor
- Add background color to avatars so they are easier to see
- Various UI fixes

= 0.8.5 (2014.05.08) =
- Bug fixes
- Fixed broken contact notification emails

= 0.8.4 (2014.05.07) =
- Bug fixes
- Fixed HTML encoding of apostrophes and special characters in the database for page titles

= Enhancements =
- Added ability to toggle subscribe widget on posts, pages, archives or the home page
- Sort contacts by last visit

= 0.8.3 (2014.05.06) =
- Bug fixes
- Merge duplicate contacts into one record
- Remove url parameters from source links in contact list
- Downgrade use of singletons so classes are compatabile with PHP 5.2

= Enhancements =
- Swap out delete statements in favor of binary "deleted" flags to minimize data loss risk
- Sort contacts by last visit

= 0.8.2 (2014.05.02) =
- Bug fixes
- Removed namespace usage in favor or a low-tech work around to be compliant with PHP 5.2 and lower

= 0.8.1 (2014.04.30) =
- Bug fixes
- Namespaced duplicate classes

= 0.8.0 (2014.04.30) =
- Bug fixes
- Fix scrolling issue with subscribe pop-up
- Duplicate class bug fixes

= Enhancements =
- Add optional first name, last name and phone fields for subscribe pop-up
- Change out contact notification emails to be from settings email address
- Ability to disable contact notification emails
- Constant Contact list sync power-up
- Sync optional contact fields (name + phone) to email service provider power-ups

= 0.7.2 (2014.04.18) =
- Bug fixes
- Fix contact deletion bug
- Implement data recovery fix for contacts
- Bug fixes to contact merging


= 0.7.1 (2014.04.11) =
- Bug fixes
- SVN bug fix that did not add the MailChimp List sync power-up

= 0.7.0 (2014.04.10) =

= Enhancements =
- MailChimp List Sync power-up
- Added new themes (bottom right, bottom left, top and pop-up) to the WordPress Subscribe Widget power-up

= 0.6.2 (2014.04.07) =
- Bug fixes
- Fixed activation error for some installs by removing error ouput
- MySQL query optimizations
- Fixed bug with MySQL V5.0+ by adding default NULL values for insert statements on contacts table
- Changed title for returning lead email notifications
- Setting to change button label on 

= Enhancements =
- Added ability to change button label on subscribe widget

= 0.6.1 (2014.03.12) =
- Bug fixes
- Updated read me.txt file
- Updated screenshots

= 0.6.0 (2014.03.07) =
- Bug fixes
- Remove in-house plugin updating functionality
- Original referrer is always the server url, not the HTTP referrer
- Strip slashes from title tags
- Number of contacts does not equal leads + commenters + subscribers
- Modals aren't bound to forms after page load
- Fix bug with activating + reactivating the plugin overwriting the saved settings
- Override button styles for Subscribe Pop-up widget

= Enhancements =
- Improved readability on new lead notification emails
- Confirmation email added for new subscribers to the Leadin Subscribe Pop-up
- Updated screenshots
- Improved onboarding flow
- Deleted unused and deprecated files

= 0.5.1 (2014.03.03) =
- Bug fixes
- Fixed Subscribe Pop-up automatically enabling itself

= 0.5.0 (2014.02.25) =
- Bug fixes
- Add (blank page title tag) to emails and contact timeline for blank page titles
- Fix link on admin nav menu bar to link to contact list
- Ignore lead notifications and subscribe popup on login page
- Saving an email no longer overwrites all the Leadin options
- Added live chat support

= Enhancements =
- New power-ups page
- Leadin Subscribe integrated into plugin as a power-up
- Improved contact history styling + interface
- Added visit, pageview and submission stats to the contact view
- Added Live Chat into the Leadin WordPress admin screens
- New Leadin icons for WordPres sidebar and admin nav menu

= 0.4.6 (2013.02.11) =
- Bug fixes
- Fix table sorting for integers
- Bug fixes to contact type headings
- Bug fix "Select All" export
- Bug fix for CSS "page views" hover triangle breaking to next line
- Backwards compability for < jQuery 1.7.0
- Add Leadin link to admin bar

= Enhancements =
- New onboarding flow

= 0.4.5 (2013.01.30) =
= Enhancements =
- Integration with Leadin Subscribe

= 0.4.4 (2013.01.24) =
- Bug fixes
- Bind submission tracking on buttons and images inside of forms instead of just submit input types

= Enhancements =
- Change out screenshots to obfiscate personal information

= 0.4.3 (2013.01.13) =
- Bug fixes
- Fixed Leadin form submission inserts for comments
- Resolved various silent PHP warnings in administrative dashboard
- Fixed Leadin updater class to be compatible with WP3.8
- Improved contact merging logic to be more reliable

= Enhancements =
- Improved onboarding flow
- Optimized form submission catching + improved performance

= 0.4.2 (2013.12.30) =
- Bug fixes
- Change 'contact' to 'lead' in the contacts table
- Fixed emails always sending to the admin_email
- Tie historical events to new lead when an email is submitted multiple times with different tracking codes
- Select leads, commenters and subscribers on distinct email addresses
- Fixed timeline order to show visit, then a form submission, then subsequent visits

= Enhancements =
- Added url for each page views in the contact timeline
- Added source for each visit event
- Tweak colors for contact timeline
- Default the Leadin menu to the contacts page

= 0.4.1 (2013.12.18) =
- Bug fixes
- Removed Leadin header from the contact timeline view
- Updated the wording on the menu view picker above contacts list
- Remove pre-mp6 styles if MP6 plugin is activated
- Default totals leads/comments = 0 when leads table is empty instead of printing blank integer
- Legacy visitors in table have 0 visits because session support did not exist. Default to 1
- Update ouput for the number of comments to be equal to total_comments, not total_leads
- Added border to pre-mp6 timeline events

= 0.4.0 (2013.12.16) =
- Bug fixes
- Block admin comment replies from creating a contact
- Fixed faulty sorting by Last visit + Created on dates in contacts list

= Enhancements =
- Timeline view of a contact history
- New CSS styles for contacts table
- Multiple email address support for new lead/comment emails
- Integration + testing for popular WordPress form builder plugins
- One click updates for manually hosted plugin

= 0.3.0 (2013.12.09) =
- Bug fixes
- HTML encoded page titles to fix broken HTML characters
- Strip slashes from page titles in emails

= Enhancements =
- Created separate Leadin menu in WordPress admin
- CRM list of all contacts
- Added ability to export list of contacts
- Leadin now distinguishes between a contact requests and comment submissions
- Added link to CRM list inside each contact/comment email

= 0.2.0 (2013.11.26) =
- Bug fixes
- Broke up page view history by session instead of days
- Fixed truncated form submission titles
- Updated email headers

= Enhancements =
- Plugin now updates upon activation and keeps record of version
- Added referral source to each session
- Added link to page for form submissions
- Updated email subject line
- Added social media avatars to emails

= 0.1.0 (2013.11.22) =
- Plugin released