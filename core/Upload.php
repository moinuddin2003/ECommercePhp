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

    /**
     * THE ONE IMAGE SIZE RULE
     * ------------------------------------------------
     * This is the single place that decides how big a single image may be.
     * It matches PHP's upload_max_filesize (set to 5M in php.ini and in
     * public/.user.ini). If you change one, change the other.
     */
    const MAX_IMAGE_BYTES = 5242880; // 5 MB

    /**
     * postLimitExceeded() = did PHP throw the whole request away?
     *
     * PHP has a hard limit called post_max_size: the maximum size of the
     * WHOLE form submission. When the images + form fields together go
     * over it, PHP discards the entire request WITHOUT a warning --
     * $_POST and $_FILES both come b   /ack empty and the page looks like it
     * did nothing at all. That silent failure is the single most
     * confusing thing about image uploads, so we detect it and say so.
     *
     * Detection: on a POST, if $_FILES AND $_POST are both empty while the
     * browser said it sent a body, the only explanation is post_max_size.
     */
    public static function postLimitExceeded()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        // If we got ANY data back, the request was fine.
        if (!empty($_FILES) || !empty($_POST)) {
            return false;
        }

        // Browser sent a body but PHP parsed nothing => body was rejected.
        return (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    }

    /** Human friendly "The total upload was too large..." message. */
    public static function postLimitMessage()
    {
        return 'Your upload was too large in total and the server discarded the whole request, '
            . 'so nothing was saved (not even the other form fields). '
            . 'Please upload fewer images at once, or use smaller images (max '
            . self::formatBytes(self::MAX_IMAGE_BYTES) . ' each).';
    }

    /** Turns 5242880 into "5 MB" so the messages read nicely. */
    public static function formatBytes($bytes)
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * productMaxHint() = the wording used in "you may only add N more"
     *
     * Upload::images() is given the REMAINING slots, not the product
     * maximum, so this reads the real cap from ProductImages (when that
     * class is loaded) to keep the message truthful.
     */
    private static function productMaxHint()
    {
        if (class_exists('ProductImages')) {
            return ProductImages::MAX_IMAGES;
        }
        return 'the allowed number';
    }

    /**
     * phpMaxFileBytes() = PHP's own upload_max_filesize, in bytes
     *
     * PHP rejects any file bigger than this BEFORE our code runs, so we
     * must never claim to accept a larger file. Returns a huge number if
     * the setting is unlimited ("-1") so it never blocks anything.
     */
    public static function phpMaxFileBytes()
    {
        $value = trim((string) ini_get('upload_max_filesize'));

        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $bytes = (int) $value;
        $unit  = strtolower(substr($value, -1));
        $number = (int) $value;

        switch ($unit) {
            case 'g':
                $bytes = $number * 1024 * 1024 * 1024;
                break;
            case 'm':
                $bytes = $number * 1024 * 1024;
                break;
            case 'k':
                $bytes = $number * 1024;
                break;
        }

        return $bytes > 0 ? $bytes : PHP_INT_MAX;
    }

    /**
     * effectiveMaxBytes() = the size limit actually in force
     *
     * Whichever is SMALLER wins: our own rule, or PHP's limit. This keeps
     * the message we show identical to the limit that is enforced, so the
     * admin is never told "5 MB" and then have a 5 MB file rejected.
     */
    public static function effectiveMaxBytes($appLimit = self::MAX_IMAGE_BYTES)
    {
        return min($appLimit, self::phpMaxFileBytes());
    }

    public static function image($fieldName, $destinationDir, $maxSizeBytes = self::MAX_IMAGE_BYTES)
    {
        // No file chosen — not an error, the caller decides what that means
        if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return ['filename' => null, 'error' => null];
        }

        $file = $_FILES[$fieldName];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                return ['filename' => null, 'error' => 'Image is too large. Please upload an image smaller than '
                    . self::formatBytes($maxSizeBytes) . '.'];
            }

            if ($file['error'] === UPLOAD_ERR_PARTIAL) {
                return ['filename' => null, 'error' => 'The image upload was interrupted. Please try again.'];
            }

            return ['filename' => null, 'error' => 'The image failed to upload. Please try again.'];
        }

        if ($file['size'] > $maxSizeBytes) {
            return ['filename' => null, 'error' => 'Image is too large. Please upload an image smaller than '
                . self::formatBytes($maxSizeBytes) . '.'];
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

    /**
     * images() = upload SEVERAL files from one <input type="file" multiple>
     *
     * Use this when the HTML input is:
     *
     *   <input type="file" name="images[]" multiple>
     *
     * The [] in the name is REQUIRED. It tells PHP to collect every
     * chosen file into an array under $_FILES['images'] instead of
     * just one file under $_FILES['images'].
     *
     * RETURNS:
     *   [
     *     'saved'  => ['12-1.jpg', '12-2.jpg'],  // files written OK
     *     'errors' => ['File 3: only JPG, PNG, WEBP or GIF allowed'],
     *   ]
     *
     * One bad file does NOT stop the good ones -- that is the whole
     * point. You get the good files saved AND a list of complaints.
     *
     * $maxFiles is the number of files THIS CALL may save. For the
     * "max 5 per PRODUCT" rule the caller must pass the product's
     * REMAINING slots (see ProductImages::remainingSlots()), not the
     * total maximum -- otherwise an existing product can be pushed
     * past the limit by uploading in several batches.
     */
    public static function images($fieldName, $destinationDir, $maxFiles = 10, $maxSizeBytes = self::MAX_IMAGE_BYTES)
    {
        $saved = [];
        $errors = [];

        // The request was so large PHP threw the whole thing away.
        // Nothing arrived, so say that instead of silently doing nothing.
        if (self::postLimitExceeded()) {
            return ['saved' => $saved, 'errors' => [self::postLimitMessage()]];
        }

        // Did the visitor choose anything at all?
        if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]['name'])) {
            return ['saved' => $saved, 'errors' => $errors];
        }

        // No room left on this product -- refuse before saving anything,
        // otherwise the extra files would be written and then orphaned.
        if ($maxFiles <= 0) {
            return ['saved' => $saved, 'errors' => ['This product already has the maximum number of images.']];
        }

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        // PHP gives us the files "column by column":
        //   $_FILES['images']['name'][0], ['name'][1], ...
        //   $_FILES['images']['tmp_name'][0], ...
        // We loop over the index (0, 1, 2...) and read each column.
        $total = count($_FILES[$fieldName]['name']);

        // The size limit actually in force: whichever is SMALLER, our own
        // rule or PHP's upload_max_filesize. PHP rejects oversized files
        // before our code even runs, so promising a bigger size than the
        // server allows would be a lie the admin discovers too late.
        $limitBytes = self::effectiveMaxBytes($maxSizeBytes);

        for ($i = 0; $i < $total; $i++) {

            // Skip the empty slots the browser leaves when you pick
            // 3 files but then deselect one.
            if ($_FILES[$fieldName]['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (count($saved) >= $maxFiles) {
                $errors[] = 'Only ' . $maxFiles . ' more image(s) could be saved '
                    . '(this product can hold at most ' . self::productMaxHint() . ' in total).';
                break;
            }

            $error = $_FILES[$fieldName]['error'][$i];

            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                $errors[] = 'Image ' . ($i + 1) . ' is larger than '
                    . self::formatBytes($limitBytes) . ' and was skipped.';
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = 'Image ' . ($i + 1) . ' failed to upload and was skipped.';
                continue;
            }

            if ($_FILES[$fieldName]['size'][$i] > $limitBytes) {
                $errors[] = 'Image ' . ($i + 1) . ' is larger than '
                    . self::formatBytes($limitBytes) . ' and was skipped.';
                continue;
            }

            $tmpName = $_FILES[$fieldName]['tmp_name'][$i];

            // The temp file can be gone even though $_FILES still lists
            // it: move_uploaded_file() CONSUMES the file, so if this
            // method is called twice in the same request (or the temp
            // dir was cleared) the path no longer points at anything.
            // Without this check mime_content_type() throws a warning
            // and we would report a misleading "wrong file type".
            if (!is_uploaded_file($tmpName) && !is_file($tmpName)) {
                $errors[] = 'Image ' . ($i + 1) . ' was already processed or could not be read, so it was skipped.';
                continue;
            }

            $mime = @mime_content_type($tmpName);

            if ($mime === false || !isset(self::$allowedMimes[$mime])) {
                $errors[] = 'Image ' . ($i + 1) . ' is not a JPG, PNG, WEBP or GIF, so it was skipped.';
                continue;
            }

            $filename = bin2hex(random_bytes(10)) . '.' . self::$allowedMimes[$mime];
            $destination = rtrim($destinationDir, '/') . '/' . $filename;

            if (move_uploaded_file($tmpName, $destination)) {
                $saved[] = $filename;
            } else {
                $errors[] = 'Image ' . ($i + 1) . ' could not be saved. Check folder permissions.';
            }
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    /**
     * renameTo() = give an uploaded file a name that means something
     *
     * Random names are great for safety, but useless to a human
     * browsing the uploads folder. This renames a file to the
     * project's convention: PRODUCTID-NUMBER.ext, e.g. "12-3.jpg".
     * So you can look at the folder and instantly tell which product
     * an image belongs to and which one it is.
     */
    public static function renameTo($oldPath, $newFilename)
    {
        if (!file_exists($oldPath)) {
            return false;
        }

        $newPath = dirname($oldPath) . '/' . $newFilename;

        if (file_exists($newPath)) {
            unlink($newPath);   // never silently overwrite
        }

        return rename($oldPath, $newPath);
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