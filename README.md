Gallery to Koken
================

Simple PHP scripts to migrate from Gallery (http://galleryproject.org) to Koken (http://koken.me).

This repo holds 2 files. One for Gallery1 and one for Gallery3.

Both scripts are intended to be used in command line (*php -f g1_to_koken.php)*. The PHP scripts creates an SQL file
and a bash script. Running the Bash script copies the images and runs the SQL againsts the Koken database.

A third file is created, it holds the RewriteMap for Apache2.

# Setup

Copy the files to the root folder of your Gallery installation.

## Configuration

Change the constants and variables at the beginning of *common.php* to reflect your installation.

A file named *foreign_chars.php* can be used to configure cleanup of slugs. It must return an array with pairs
of characters to replace being the key and the replacement being the value. For example:

	<?php
	
	// list characters that should be replaced
	return array(
		//'ä' => 'a'
	);

Place the file into the same folder as the migration script.

# Usage

* From the terminal, execute the php script matching your Gallery version:

    * For Gallery 1 use *php -f g1_to_koken.php*)
    * For Gallery 3 use *php -f g3_to_koken.php*)

* Run the Bash script located at /tmp/koken.sh
