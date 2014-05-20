Chicago Socrata Capture
=======================

Copy "views" from the City of Chicago data portal to PostgreSQL

Given an 8-digit view ID, this script will:
* verify that the view exists on the City of Chicago data portal
* create a table for this view in your specified PostgreSQL database with appropriate datatypes
* copy the first 1,000 rows to that table

Needs
* some style
* cleaner error messages when you give it a view ID it already captured and it tries to create a duplicate table or rows
* a faux-cron system that will capture the next 1,000 rows (sync)

Credits
Paul Weinstein's Windy PHP class: http://pdw.weinstein.org/2011/09/accessing-chicago-cook-and-illinois-open-data-via-php.html
