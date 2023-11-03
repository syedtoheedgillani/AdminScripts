<?php
/**
 * Admin Scripts to import Course Images.
 *
 * @package    local_adminscripts
 * @copyright  2007-2022 Mahtab Hussain, Syed {@link http://paktaleem.org}
 * @license    All rights reserved.
 */

require(__DIR__.'/../../config.php');
require_login();

// Modify the SQL query to sort by instanceid and remove duplicates
$sql = "SELECT id AS contextid, contextlevel, instanceid AS courseid
        FROM {context}
        WHERE contextlevel = 50
        GROUP BY instanceid
        ORDER BY instanceid";

$results = $DB->get_records_sql($sql);

// Get the File Storage instance
$fs = get_file_storage();

// Define an array of allowed file extensions
$allowedExtensions = array('png', 'jpg', 'jpeg', 'gif'); // Add more extensions as needed

// Initialize counters
$updatedCount = 0;
$createdCount = 0;

// Iterate through the results and create/update files for each context and course
foreach ($results as $result) {
    // Define the images path with courseid
    $imagespath = '';

    // Find the existing image file
    foreach ($allowedExtensions as $extension) {
        $potentialPath = $CFG->dataroot . '/repository/courseimages/' . $result->courseid . '.' . $extension;
        if (file_exists($potentialPath)) {
            $imagespath = $potentialPath;
            break;
        }
    }

    // Check if an image file was found
    if (!empty($imagespath)) {
        // Determine the file extension from the imagespath
        $fileExtension = pathinfo($imagespath, PATHINFO_EXTENSION);

        // Define the file info for the new file using contextid and courseid
        $newFile = array(
            'contextid' => $result->contextid,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $result->courseid . '.' . $fileExtension, // Use courseid in the filename
        );

        // Check if a file with the same name already exists in Moodle's file storage
        $existingFile = $fs->get_file($newFile['contextid'], $newFile['component'], $newFile['filearea'], $newFile['itemid'], $newFile['filepath'], $newFile['filename']);

        if ($existingFile) {
            // If the file exists, delete the old file
            $existingFile->delete();

            // Create a new file with the updated content
            $fs->create_file_from_pathname($newFile, $imagespath);
            echo "Course Image for Course ID: '{$result->courseid}' has been updated.<br>";
            $updatedCount++; // Increment the update counter
        } else {
            // If the file doesn't exist, create a new file
            $fs->create_file_from_pathname($newFile, $imagespath);
            echo "New Course Image has been uploaded for the Course ID: '{$result->courseid}'.<br>";
            $createdCount++; // Increment the creation counter
        }
    }
}

// Display the counts at the end
echo "<br>";
echo "Total Course Images Updated: $updatedCount<br>";
echo "Total Course Images Created: $createdCount<br>";
?>

