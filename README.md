Gallery to Koken
================

Simple PHP scripts to migrate from Gallery (http://galleryproject.org) to Koken (http://koken.me).

This repo holds 2 files. One for Gallery1 and one for Gallery3.

Both scripts are intended to be used in command line (*php -f g1_to_koken.php)*. The PHP scripts creates an SQL file and a bash script. Running the Bash script copies the images and runs the SQL againsts the Koken database.

A third file is created, it holds the RewriteMap for Apache2.

## g1_to_koken.php
Allows migration from Gallery1 to Koken.
### Usage
* Copy the file to the root folder of your Gallery1 installation
* Change the constants at the beginning to reflect your installation
* From the terminal, execute the php script (*php -f g1_to_koken.php*)
* Run the Bash script located at /tmp/koken.sh

## g3_to_koken.php
Allows migration from Gallery3 to Koken.
### Usage
* Copy the file to the root folder of your Gallery3 installation
* Change the constants at the beginning to reflect your installation
* From the terminal, execute the php script (*php -f g3_to_koken.php*)
* Run the Bash script located at /tmp/koken.sh
