=== LeadIn ===
Contributors: andygcook, nelsonjoyce
Tags:  lead tracking, visitor tracking, analytics, crm, marketing automation, inbound marketing, subscription, marketing, lead generation, mailchimp
Requires at least: 3.7
Tested up to: 3.8.2
Stable tag: 0.7.0

LeadIn is an easy-to-use marketing automation and lead tracking plugin for WordPress that helps you better understand your web site visitors.

== Description ==

= Get personal with your leads =

<a href="http://leadin.com" alt="WordPress marketing automation and lead tracking plugin">LeadIn</a> is an easy-to-use marketing automation and lead tracking plugin for WordPress that helps you better understand your web site visitors.

When a person submits a form on your WordPress site, you want to know more about them. What pages they've visited, when they return, and what social networks they’re on. Our WordPress marketing automation and lead tracking plugin gives you the details you need to make your next move. Because business isn’t business unless it’s personal.

= How does it work? =

1. When you activate the WordPress plugin, LeadIn will track each anonymous visitor to your site with a cookie. If someone closes the pop-up or subscribes, we won't show them the pop-up again.
2. Once someone fills out the subscribe form or any other other form on your site, LeadIn will identify that person with their email address.
3. You'll receive an email with a link to the new contact record with all of their visit history.

= Who's using LeadIn? =


**Alan Perlman**: *“I can use LeadIn to get a sense of how engaged certain contacts are, and I can learn more about their behavior on my website to better drive the conversation and understand what they’re interested in or looking for.”*

<a href="http://www.extremeinbound.com/leadin-wordpress-crm-inbound-plugin/">Read more from Alan</a>


**Adam W. Warner**: *“…the LeadIn plugin has been very useful so far in giving us an idea of the actual visitor paths to our contact forms vs. the paths we’ve intended.”*

<a href="http://thewpvalet.com/wordpress-lead-tracking/">Read more from Adam</a>


= Note: =

LeadIn collects usage information about this plugin so that we can better serve our customers and know what features to add. By installing and activating the LeadIn for WordPress plugin you agree to these terms.

== Installation ==

1. Upload the 'leadin' folder to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add an email address under 'LeadIn' in your settings panel

== Frequently Asked Questions ==

= How does LeadIn integrate with my forms? =

LeadIn automatically integrates with your contact and comment forms that contain an email address field on your web site. There's no setup required.

= Where are my contact submission stored? =

LeadIn creates a new contact in your Contacts Tabke whenever an email address is detected in your visitor's form submission.

There is no limit to the number of contacts you can store in your Contacts Table.

= Which contact form building plugins are supported? =

LeadIn is intended to work with any HTML form out of the box, but does not support forms created by Javascript or loaded through an iFrame. 

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

= Tested + unsupported: =

- Wufoo
- HubSpot
- Easy Contact Forms
- Disqus comments

== Screenshots ==

1. Individual contact history
2. Contacts list
3. Sample email report

== Changelog ==

Current version: 0.7.0
Current version release: 2014-04-10

= 0.7.0 (2014.04.10) =

= Enhancements =
- MailChimp List Sync power-up
- Added new themes (bottom right, bottom left, top and pop-up) to the WordPerss Subscribe Widget power-up

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
- Confirmation email added for new subscribers to the LeadIn Subscribe Pop-up
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
- Saving an email no longer overwrites all the LeadIn options
- Added live chat support

= Enhancements =
- New power-ups page
- LeadIn Subscribe integrated into plugin as a power-up
- Improved contact history styling + interface
- Added visit, pageview and submission stats to the contact view
- Added Live Chat into the LeadIn WordPress admin screens
- New LeadIn icons for WordPres sidebar and admin nav menu

= 0.4.6 (2013.02.11) =
- Bug fixes
- Fix table sorting for integers
- Bug fixes to contact type headings
- Bug fix "Select All" export
- Bug fix for CSS "page views" hover triangle breaking to next line
- Backwards compability for < jQuery 1.7.0
- Add LeadIn link to admin bar

= Enhancements =
- New onboarding flow

= 0.4.5 (2013.01.30) =
= Enhancements =
- Integration with LeadIn Subscribe

= 0.4.4 (2013.01.24) =
- Bug fixes
- Bind submission tracking on buttons and images inside of forms instead of just submit input types

= Enhancements =
- Change out screenshots to obfiscate personal information

= 0.4.3 (2013.01.13) =
- Bug fixes
- Fixed LeadIn form submission inserts for comments
- Resolved various silent PHP warnings in administrative dashboard
- Fixed LeadIn updater class to be compatible with WP3.8
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
- Default the LeadIn menu to the contacts page

= 0.4.1 (2013.12.18) =
- Bug fixes
- Removed LeadIn header from the contact timeline view
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
- Created separate LeadIn menu in WordPress admin
- CRM list of all contacts
- Added ability to export list of contacts
- LeadIn now distinguishes between a contact requests and comment submissions
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