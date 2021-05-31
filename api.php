<?php

// Uncomment these for error reporting
//
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// You can leave this in the same dir for
// testing, but it really should be moved to
// a directory that's not web-acessible for
// security.
$db = new SQLite3('./photo-app.db');

// And uncomment for better DB error reporting
// $db->enableExceptions(true);

require "./inc/functions.php";

if($_REQUEST['action']) {
	switch($_REQUEST['action']) {
		case 'createAccount':
			$account = createAccount($_POST['email'], $_POST['password'], $_POST['friend_code']);
			if($account) {
				respondWith($account, 201);
			} else {
				respondWith('createAccount', 400);
			}
		break;

		case 'loginAccount':
			$account = loginAccount($_POST['email'], $_POST['password']);
			if($account) {
				respondWith($account, 200);
			} else {
				respondWith('loginAccount', 401);
			}
		break;

		case 'logoutAccount':
			$account = logoutAccount();
			respondWith($account, 204);
		break;

		case 'createGallery':
			$gallery = createGallery($_POST['name']);
			if($gallery) {
				respondWith($gallery, 201);
			} else {
				respondWith('createGallery', 400);
			}
		break;

		case 'showGallery':
			$gallery = showGallery($_GET['id']);
			if($gallery) {
				respondWith($gallery, 200);
			} else {
				respondWith('showGallery', 404);
			}
		break;

		case 'deleteGallery':
			$gallery = deleteGallery($_GET['id']);
			respondWith($gallery, 204);
		break;

		case 'addPhoto':
			$photo = addPhoto($_POST['gallery_id'], $_POST['name'], $_POST['description'], $_POST['url']);
			if($photo) {
				respondWith($photo, 201);
			} else {
				respondWith('createPhoto', 400);
			}
		break;

		case 'showPhoto':
			$photo = showPhoto($_GET['id']);
			if($photo) {
				respondWith($photo, 200);
			} else {
				respondWith('showPhoto', 404);
			}
		break;

		case 'deletePhoto':
			$photo = deletePhoto($_GET['id']);
			respondWith($photo, 204);
		break;
	}
} else {
	respondWith('ok', 200);
}