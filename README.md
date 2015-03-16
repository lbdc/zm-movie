# zm-movie
Make zoneminder movie from stills, uses mencoder apache mysql and zoneminder. Tested on ubuntu 14.04 and zoneminder 1.28 installation from iconnor repository

PHP script to create videos between start/end dates from stills (Note I am not a programmer so code may not meet industry standards!!!)

Ensure the folder where the script resides is writable by www-data and mencoder is installed

Simply drop the script in /var/www/html somewhere and point your browser to it. Uses default ZM 1.28 paths and mysql credentials. The script will create a video in the folder where the script resides. Once mencoder has finished making the video, the script will display the video available for download on the script page (you have to refresh the page).

The script has 2 modes:
All = movies generated from all frames
Alarm = alarm frames only (with time buffer before and after). The intent of this is to be used with mocord.

The script also can be run from the command line (but watch for file zm_list.txt permissions created in /tmp foler when going back and forth from arguments/browser modes)

