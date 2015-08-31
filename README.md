# zm-movie
Make zoneminder movie from stills
snapshot(http://imgur.com/CumzeUz)

Skip frames mean the video creation will skip frames. For example, a setting of 2 will keep every other frame while a setting of 10 would keep every 1 of 10 frame (which would result in 10X the original speed (A skip frame of 10 with a speed of 50 would encode at 10x50=500 fps). The intent is timelapse for long duration (day, month)

zm_movie_cam_avconv_01.php uses avconv/ffmpeg apache mysql and zoneminder

Tested on ubuntu 14.04 and zoneminder 1.28.1 installation from iconnor repository

PHP script to create videos between start/end dates from stills (Note I am not a programmer so code may not meet industry standards!!!)

Ensure the folder where the script resides is writable by www-data

Simply drop the script in /var/www/html somewhere and point your browser to it. Uses default ZM 1.28 paths and reads mysql credentials from default path. The script will create a video in the folder where the script resides. Once ffmpeg/avconv has finished making the video, the script will display the video available for download on the script page.

The script has 2 modes:
All = movies generated from all frames
Alarm = alarm frames only (with time buffer before and after). The intent of this is to be used with mocord.

Due to zoneminder writing bulk frames to database in continuous recording the buffer time may not be exact in the video. For high frame rate recordings (30fps) buffer should be fairly accurate but for lower frame rates (5 fps for example) you may want to reduce the bulk frame interval in zoneminder settings (script to be adjusted later).
