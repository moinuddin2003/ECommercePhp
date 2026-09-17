<?php
/**
 * core/Upload.php
 * ------------------------------------------------
 * Handles a single image upload with the security checks
 * from your project spec: validate the real MIME type (not
 * just the filename extension, which is easy to fake), enforce
 * a size limit, and generate a random filename so uploads can
 * never overwrite each other or be used for directory traversal.
 *
 * USAGE:
 *   $result = Upload::image('image', __DIR__ . '/../../public/uploads/categories');
 *   if ($result['error']) {
 *       $errors['image'] = $result['error'];
 *   } elseif ($result['filename']) {
 *       $imageFilename = $result['filename']; // save this to the DB
 *   }
 *   // If no file was chosen at all, $result['filename'] is null and
 *   // $result['error'] is also null — useful on an edit form where
 *   // leaving the image field empty means "keep the current image".
 */

class Upload
{
    private static $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public static function image($fieldName, $destinationDir, $maxSizeBytes = 2097152)
    {
        // No file chosen — not an error, the caller decides what that means
        if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return ['filename' => null, 'error' => null];
        }

        $file = $_FILES[$fieldName];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                return ['filename' => null, 'error' => 'Image is too large. Please upload an image smaller than 2MB.'];
            }

            if ($file['error'] === UPLOAD_ERR_PARTIAL) {
                return ['filename' => null, 'error' => 'The image upload was interrupted. Please try again.'];
            }

            return ['filename' => null, 'error' => 'The image failed to upload. Please try again.'];
        }

        if ($file['size'] > $maxSizeBytes) {
            return ['filename' => null, 'error' => 'Image is too large. Please upload an image smaller than 2MB.'];
        }

        // Check the ACTUAL file content, not just the filename extension
        // (a beginner mistake would be trusting .jpg in the name — that's
        // trivially fakeable; mime_content_type() reads the real file bytes).
        $mime = mime_content_type($file['tmp_name']);

        if (!isset(self::$allowedMimes[$mime])) {
            return ['filename' => null, 'error' => 'Only JPG, PNG, WEBP, or GIF images are allowed.'];
        }

        $extension = self::$allowedMimes[$mime];
        $filename = bin2hex(random_bytes(10)) . '.' . $extension;

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $destination = rtrim($destinationDir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['filename' => null, 'error' => 'Could not save the uploaded file. Check folder permissions.'];
        }

        return ['filename' => $filename, 'error' => null];
    }

    /** Deletes a previously uploaded file, e.g. when replacing an image or deleting a row. */
    public static function delete($destinationDir, $filename)
    {
        if (!$filename) {
            return;
        }

        $path = rtrim($destinationDir, '/') . '/' . $filename;

        if (file_exists($path)) {
            unlink($path);
        }
    }
}