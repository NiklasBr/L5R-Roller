<?php
// Set the source $file
$file = "probability.php";

// Set the $path to the source file, modify to suit your current host
$path = "/data/www/probability/";

if (file_exists($file)) {
    // If the $file exists it will execute the file and write to a static HTML file
    exec('/usr/bin/php ' . $path . $file . ' > index.html &');
}
