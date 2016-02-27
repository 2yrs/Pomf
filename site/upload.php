<?php
// Check if we can compress our output; if we can, we'll do it
if (ini_get('zlib.output_compression') !== 'Off'
	&& isset($_SERVER["HTTP_ACCEPT_ENCODING"])
	&& strpos($_SERVER["HTTP_ACCEPT_ENCODING"], 'gzip') !== false)
	    ob_start("ob_gzhandler");

session_start();
include_once 'classes/Response.class.php';
include_once 'classes/UploadException.class.php';
include_once 'classes/UploadedFile.class.php';
include_once 'includes/database.inc.php';

/**
 * Handles the uploading and db entry for a file
 *
 * @param  UploadedFile $file
 * @return array
 */
function upload_file ($file) {
	global $db;

	// Handle file errors
	if ($file->error) throw new UploadException($file->error);


    if (!file_exists(POMF_FILES_ROOT)) {
        mkdir(POMF_FILES_ROOT, 0777, true);
    }
    
    $adderdirfull = explode('/', $file->tempfile);
    $adderdir = $adderdirfull[count($adderdirfull) - 1];
    
    if (!file_exists(POMF_FILES_ROOT.$adderdir)) {
        mkdir(POMF_FILES_ROOT.$adderdir, 0777, true);
        
        if (move_uploaded_file($file->tempfile, POMF_FILES_ROOT.$adderdir.'/'.$file->name)) {
        //if (move_uploaded_file($file->tempfile, escapeshellarg(POMF_FILES_ROOT.$adderdir.'/'.$file->name) )) {
        	exec('HOME=/home/www-data ipfs add -rq '.POMF_FILES_ROOT.$adderdir, $ipfsout);
        	
	    //what a horrible scary line...
	    exec('rm -rf '.POMF_FILES_ROOT.$adderdir);

	        //$ipfsprocess = explode(' ', $ipfsout[count($ipfsout) - 1]);
	        $ipfsdirhash = $ipfsout[count($ipfsout) - 1];
	        $ipfshash = $ipfsout[0];
            
	        // Generate a name for the file
	        //$newname = generate_name($file);
	        $newname = $ipfsdirhash.'/'.$file->name;

	        //file_put_contents ("/tmp/hurrr.txt", "Added thing to ipfs, result was: " . count($ipfsout) . "\n", FILE_APPEND);
	        //for ($muhi = 0; $muhi < count($ipfsout); $muhi++)
	        //	file_put_contents ("/tmp/hurrr.txt", "thing $muhi was " . $ipfsout[$muhi] . "\n", FILE_APPEND);

	        // Check if a file with the same hash and size (a file which is the same) does already exist in
	        // the database; if it does, delete the file just uploaded and return the proper link and data.
	        /*$q = $db->prepare('SELECT filename, COUNT(*) AS count FROM files WHERE hash = (:hash) ' .
	                          'AND size = (:size)');
	        //$q->bindValue(':hash', $file->get_sha1(), PDO::PARAM_STR);
	        $q->bindValue(':hash', $ipfshash, PDO::PARAM_STR);
	        $q->bindValue(':size', $file->size,       PDO::PARAM_INT);
	        $q->execute();
	        $result = $q->fetch();
	        if ($result['count'] > 0) {
		        //unlink($file->tempfile);
		        exec('rm -rf '.POMF_FILES_ROOT.$adderdir);
		        return array(
			        'hash' => $ipfshash,
			        'name' => $file->name,
			        'url'  => $result['filename'],
			        'size' => $file->size
		        );
	        }*/

	        // Add it to the database
            if (empty($_SESSION['id'])) {
                    // Query if user is NOT logged in
                    $q = $db->prepare('INSERT INTO files (hash, originalname, filename, size, date, ' .
                                      'expire, delid) VALUES (:hash, :orig, :name, :size, :date, ' .
                                      ':exp, :del)');
            } else {
                    // Query if user is logged in (insert user id together with other data)
                    $q = $db->prepare('INSERT INTO files (hash, originalname, filename, size, date, ' .
                                      'expire, delid, user) VALUES (:hash, :orig, :name, :size, ' .
                                      ':date, :expires, :delid, :user)');
                    $q->bindValue(':user', $_SESSION['id'], PDO::PARAM_INT);
            }

            // Common parameters binding
            //$q->bindValue(':hash', $file->get_sha1(),       PDO::PARAM_STR);
            $q->bindValue(':hash', $ipfshash,               PDO::PARAM_STR);
            $q->bindValue(':orig', strip_tags($file->name), PDO::PARAM_STR);
            $q->bindValue(':name', strip_tags($newname),    PDO::PARAM_STR);
            $q->bindValue(':size', $file->size,             PDO::PARAM_INT);
            $q->bindValue(':date', date('Y-m-d'),           PDO::PARAM_STR);
            $q->bindValue(':exp',  null,                    PDO::PARAM_STR);
            $q->bindValue(':del',  sha1($file->tempfile),   PDO::PARAM_STR);
            $q->execute();
            
            //unlink($file->tempfile);

            //'hash' => $file->get_sha1(),
            return array(
                    'hash' => $ipfshash,
                    'name' => $file->name,
                    'url'  => $newname,
                    'size' => $file->size
            );   
        } else {
		    throw new Exception('Failed to move file to destination', 500);
	    }
    } else {
        throw new Exception('Race condition detected, please try again.', 500);
    }
}

/**
 * Reorder files array by file
 *
 * @param  $_FILES
 * @return array
 */
function diverse_array ($files) {
	$result = array();
	foreach ($files as $key1 => $value1)
		foreach ($value1 as $key2 => $value2)
			$result[$key2][$key1] = $value2;

	return $result;
}

/**
 * Reorganize the $_FILES array into something saner
 *
 * @param  $_FILES
 * @return array
 */
function refiles ($files) {
	$result = array();
	$files  = diverse_array($files);

	foreach ($files as $file) {
		$f = new UploadedFile();
		$f->name     = $file['name'];
		$f->mime     = $file['type'];
		$f->size     = $file['size'];
		$f->tempfile = $file['tmp_name'];
		$f->error    = $file['error'];
		// 'expire' doesn't exist neither in $_FILES nor in UploadedFile;
		// commented out for future implementation
		//$f->expire   = $file['expire'];
		$result[] = $f;
	}

	return $result;
}



$type = isset($_GET['output']) ? $_GET['output'] : 'json';
$response = new Response($type);
if (isset($_FILES['files'])) {
	$uploads = refiles($_FILES['files']);
	try {
		foreach ($uploads as $upload)
			$res[] = upload_file($upload);
		$response->send($res);
	} catch (Exception $e) {
		$response->error($e->getCode(), $e->getMessage());
	}
} else {
	$response->error(400, 'No input file(s)');
}

?>
