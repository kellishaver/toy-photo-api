<?php

function createAccount($email, $password, $friendCode) {
	$db = $GLOBALS['db'];

	$count = $db->querySingle("SELECT COUNT(*) as count FROM users");
	$lookupFriend = NULL;
	$lookupUser = NULL;

	if($count > 0) {
		$sql = sprintf("SELECT id FROM users WHERE email = '%s'", $db->escapeString($email));
		$lookupUser = $db->querySingle($sql, true);

		$sql = sprintf("SELECT id FROM users WHERE friend_code = '%s'", $db->escapeString($friendCode));
		$lookupFriend = $db->querySingle($sql, true);
	}

	// you need a friend code to create an account
	// do other validations, too
	if(!preg_match("/([\w\-]+\@[\w\-]+\.[\w\-]+)/", $email) || 
		$lookupUser != NULL ||
		($count > 0 && $lookupFriend == NULL) ||
		!validatePassword($password)) {
		return false;
	} else {
		$date = new DateTime(); // obj
		$now = $date->format('Y-m-d H:i:s'); // string
		$newFriendCode = generateRandomToken();
		$authPrefix = generateRandomToken();
		$authKey = md5($authPrefix.$now);

		// md5 hashes are not a good way to store passwords, but this is mainly a toy API that I built to
		// have something to act as a back end for a React learning project
		$sql = sprintf("INSERT INTO users (email, password, friend_code, auth_key, created_at) VALUES(
			'%s', '%s', '%s', '%s', '%s')", $db->escapeString($email), md5($password), $newFriendCode, $authKey, $now);
		$stm = $db->prepare($sql);

		if($stm->execute()) {
			$result = (object) [
				'email' => $email,
				'auth_key' => $authKey, // cache client-side and use for subsequent requests
				'friend_code' => $newFriendCode
			];
			return $result;
		} else {
			return false;
		}
	}
}

function loginAccount($email, $password) {
	$db = $GLOBALS['db'];
	$sql = sprintf("SELECT * FROM users WHERE email = '%s' AND password = '%s'", $db->escapeString($email), md5($db->escapeString($password)));
	$lookupUser = $db->querySingle($sql, true);
	if($lookupUser) {
		$user = (object) $lookupUser;
		$date = new DateTime();
		$now = $date->format('Y-m-d H:i:s');
		$authPrefix = generateRandomToken();
		$authKey = md5($authPrefix.$now);

		// this makes the auth method more vulnerable to MITM and cache-based attacks, but see above re: toy
		$sql = sprintf("UPDATE users SET auth_key = '%s' WHERE id=%d", $authKey, $user->id);
		$stm = $db->prepare($sql);
		$stm->execute();

		$result = (object) [
			'email' => $user->email,
			'auth_key' => $authKey
		];

		return $result;
	} else {
		return false;
	}
}

function logoutAccount() {
	if(!authAccount()) { return false; }
	$db = $GLOBALS['db'];
	$sql = sprintf("UPDATE users set auth_key = NULL WHERE auth_key = '%s'", $db->escapeString($_REQUEST['auth_key']));
	$stm = $db->prepare($sql);
	$stm->execute();
	$result = (object) ['logout' => 'ok'];

	return $result; // delete client side regardless of result
}

function authAccount() {
	$db = $GLOBALS['db'];

	$sql = sprintf("SELECT id FROM users WHERE auth_key = '%s'", $db->escapeString($_REQUEST['auth_key']));
	$lookupUser = $db->querySingle($sql, true);
	if($lookupUser) { // token-based auth, a record was found
		return $lookupUser['id'];
	} else { // or not...
		return false;
	}
}

function createGallery($albumTitle) {
	$userId = authAccount();
	if(!$userId) { return false; }
	$db = $GLOBALS['db'];
	$sql = sprintf("INSERT INTO galleries (name, user_id) VALUES('%s', '%s')", $db->escapeString($albumTitle), $userId);
	$stm = $db->prepare($sql); // no uniqueness necessary

	if($stm->execute()) {
		$result = (object) [
			'name' => $albumTitle,
			'photos' => []
		];

		return $result;
	} else {
		return false;
	}
}

function showGallery($galleryId) {
	$db = $GLOBALS['db'];

	$sql = sprintf("SELECT id, name FROM galleries WHERE id = %d", $db->escapeString($galleryId));
	$lookupGallery = $db->querySingle($sql, true);

	if($lookupGallery) {
		$gallery = (object) $lookupGallery;

		$sql = sprintf("SELECT id,name,description,url FROM photos WHERE gallery_id = %d", $db->escapeString($galleryId));
		$lookupPhotos = $db->query($sql);
		$photos = [];
		while($row = $lookupPhotos->fetchArray(SQLITE3_ASSOC)) {
			$photos[] = (object) $row;
		}

		$result = (object) [
			'id' => $gallery->id,
			'name' => $gallery->name,
			'photos' => $photos
		];

		return $result;
	} else {
		return false;
	}
}

function deleteGallery($galleryId) {
	$userId = authAccount();
	if(!$userId) { return false; }
	$db = $GLOBALS['db'];

	$sql = sprintf("DELETE FROM galleries WHERE id = %d AND user_id = %d", $db->escapeString($galleryId), $userId);
	$lookupGallery = $db->query($sql);

	$sql = sprintf("DELETE FROM photos WHERE gallery_id = %d AND user_id = %d", $db->escapeString($galleryId), $userId);
	$lookupPhotos = $db->query($sql);

	$result = (object) ['deleted' => 'ok'];

	return $result;
}

function addPhoto($galleryId, $name, $description, $url) {
	$userId = authAccount();
	if(!$userId) { return false; }
	$db = $GLOBALS['db'];
	$sql = sprintf("SELECT id, name FROM galleries WHERE id = %d", $db->escapeString($galleryId));
	$lookupGallery = $db->querySingle($sql, true);

	if($lookupGallery) {
		$gallery = (object) $lookupGallery;

		$sql = sprintf("INSERT INTO photos (gallery_id, user_id, url, name, description) VALUES(
			'%s', '%s', '%s', '%s', '%s')",  $gallery->id, $userId, $db->escapeString($url),
			$db->escapeString($name), $db->escapeString($description));
		$stm = $db->prepare($sql);
		
		if($stm->execute()) {
			$result = (object) [
				'id' => $db->lastInsertRowID(),
				'url' => $url,
				'name' => $name,
				'description' => $description
			];

			return $result;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

function showPhoto($photoId) {
	$db = $GLOBALS['db'];
	$sql = sprintf("SELECT * FROM photos WHERE id = %d", $db->escapeString($photoId));
	$lookupPhoto = $db->querySingle($sql, true);

	if($lookupPhoto) {
		$result = (object) $lookupPhoto;
		return $result;
	} else {
		return false;
	}
}

function deletePhoto($photoId) {
	$userId = authAccount();
	if(!$userId) { return false; }
	$db = $GLOBALS['db'];

	$sql = sprintf("DELETE FROM photos WHERE id = %d AND user_id = %d", $db->escapeString($photoId), $userId);
	$lookupGallery = $db->query($sql);

	$result = (object) ['deleted' => 'ok'];

	return $result;
}

function generateRandomToken($length = 10) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, $length);
}

function validatePassword($password) {
	$valid = true;
    if (strlen($password) < 8) { // at least 8 characters long
        $valid = false;
    } elseif (!preg_match("#[0-9]+#", $password)) { // contains a number
        $valid = false;
    } elseif(!preg_match("#[A-Z]+#", $password)) { // contains a capital letter
        $valid = false;
    } elseif(!preg_match("#[a-z]+#", $password)) { // contains a lowercase letter
        $valid = false;
    }
    return $valid;
}

function errorMsg($errorCode) {
	switch($errorCode) {
		case 'createAccount':
			return "Error creating account. Make sure your password and friend code are valid.";
		break;

		case 'loginAccount':
			return 'Username or password incorrect.';
		break;

		case 'createGallery':
			return 'Error creating gallery. Did you name it?';
		break;

		case 'showGallery':
			return "Error displaying gallery contents.";
		break;

		case 'deleteGallery':
			return 'Error deleting gallery.';
		break;

		case 'addPhoto':
			return 'Error adding photo to gallery.';
		break;

		case 'showPhoto':
			return 'Error displaying photo';
		break;

		case 'deletePhoto':
			return 'Error deleting photo.';
		break;
	}
}

function respondWith($responseData, $statusCode) {
	if(is_object($responseData)) {
		$response = [
			'resposne' => $responseData,
			'error' => NULL,
			'status_code' => $statusCode
		];
	} else {
		$response = [
			'resposne' => NULL,
			'error' => errorMsg($responseData),
			'status_code' => $statusCode
		];
	}

	header('Content-Type: application/json');
	echo json_encode($response);
}
