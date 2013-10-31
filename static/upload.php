<?php
include_once 'classes/UploadedFile.class.php';
include_once 'includes/settings.inc.php';
include_once 'includes/database.inc.php';

/**
 * Generates a name for the file, retrying until we get an unused one 
 *
 * @param UploadedFile $file
 * @returns string
 */
function generate_name ($file) {
	// We start at N retries, and --N until we give up
	$tries = POMF_FILES_RETRIES;
	// We rip out the extension using pathinfo
	// TODO: figure out a solution for .tar.gz and similar files?
	$ext = pathinfo($file->name, PATHINFO_EXTENSION);
	// Take the first 3 chars of the CRC32 checksum
	$hashchunk = substr($file->get_crc32(), 0, 3);
	do {
		// If we run out of tries, throw an exception.  Should be caught and JSONified.
		if ($tries-- == 0) throw new Exception('Gave up trying to find an unused name');

		// TODO: come up with a better name generating algorithm
		$newname  = '';                                  // Filename Generator:
		$newname .= chr(mt_rand(ord("a"), ord("z")));    // + random lowercase letter
		$newname .= $hashchunk;                          // + first 3 of crc32b checksum
		$newname .= chr(mt_rand(ord("a"), ord("z")));    // + random lowercase letter
		$newname .= '.' . $ext;                          // + '.' + extension

	} while (file_exists(POMF_FILES_ROOT . $newname)); // TODO: check the database instead?

	return $newname;
}

/**
 * Handles the uploading and db entry for a file
 *
 * @param UploadedFile $file
 * @returns array
 */
function upload_file ($file) {
	global $db;

	// If the file has an error attached, we just throw it as an exception.
	if ($file->error) throw new Exception($file->error);

	// Check if we have a file with that hash in the db
	$q = $db->prepare("SELECT hash, filename, size FROM files WHERE hash = (:hash)");
	$q->bindValue('hash', $file->get_sha1());
	$q->execute();
	$result = $q->fetch();

	// If we found a file with the same checksums, then we can assume it's a dupe
	// so we don't bother with it, and just unlink (delete) the tmpfile and return
	// the previous data.
	if ($result['hash'] === $file->get_sha1()) {
		unlink($file->tempfile);
		return $result;
	} else {
		// Generate a name for the file
		$newname = generate_name($file);

		// Attempt to move it to the static directory
		if (move_uploaded_file($file->tempfile, POMF_FILES_ROOT . $newname)) {
			// Add it to the database
			$q = $db->prepare('INSERT INTO files (hash, orginalname, filename, size, date, expire, delid)' .
			                  'VALUES (:hash, :orig, :name, :size, :date, :expires, :delid)');
			$q->bindValue(':hash', $file->get_sha1());
			$q->bindValue(':orig', $file->name);
			$q->bindValue(':name', $newname);
			$q->bindValue(':size', $file->size);
			$q->bindValue(':date', date('Y-m-d'));
			$q->bindValue(':expires', null);
			$q->bindValue(':delid', sha1($file->tempfile));
			$q->execute();

			return array(
				'hash' => $file->get_sha1(),
				'name' => $file->name,
				'url' => $newname,
				'size' => $file->size
			);
		} else {
			throw new Exception('Failed to move file to destination');
		}
	}
}

/**
 * Reorganize the $_FILES array into something saner
 *
 * @param $_FILES
 */
function refiles ($files) {
	$out = array();
	for ($i = 0, $n = count($files['name']); $i < $n; ++$i) {
		// We create a new UploadedFile instance
		$file = new UploadedFile();
		// And fill it with our shit
		$file->name = $files['name'][$i];
		$file->mime = $files['type'][$i];
		$file->tempfile = $files['tmp_name'][$i];
		$file->error = $files['error'][$i];
		$file->size = $files['size'][$i];
		$out[] = &$file;
	}
	return $out;
}

/**
 * Responds to a request in JSON form.
 */
function respond ($code, $res = null) {
	if (is_int($code)) { // If the code is an integer, we assume it's a response code
		http_response_code($code);
	} else { // Otherwise we just assume you left off the code and use the default and shift
		http_response_code(200);
		$res = $code;
	}

	// Now we send the response based on the type
	if ($res instanceof Exception) {
		// If it's an Exception, we put the message in the error field
		echo json_encode(array(
			'success' => false,
			'error' => $res->getMessage()
		));
	} elseif (is_array($res)) {
		// If it's an Array, we merge it with 'success' => true.
		// Note that $res is later in the list, so you can override that.
		echo json_encode(array_merge(array(
			'success' => true,
			'error' => null
		), $res));
	}
}

if (isset($_FILES['files'])) {
	try {
		$uploads = refiles($_FILES['files']);
		foreach ($uploads as $upload) {
			$out[] = upload_file($upload);
		}
		respond(array(
			'files' => $out
		));
	} catch (Exception $e) {
		respond(500, $e);
	}
} else {
	respond(500, new Exception('Nigga what you doin\' here?'));
}
?>
