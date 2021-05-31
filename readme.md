Simple photo app REST API.
=====================================

This is a toy API I built to serve as a back-end for a React learning project. 

It's a basic photo sharing application, like Imgur or somilar. Basically, create an account, make some galleries, and add some photos.

About creating accounts:
---

You need a friend code to create any account other than the first one. Creating an account gives you a friend code.

Your friends can then use that code to create accounts (and refer others via) their friend code, etc.

There's no tracking who referred who.


About adding photos:
----

This API doesn't handle photo uploads or do any image processing.

My intent was to use direct to S3 uploads using AWS the JavaScript AWS SDK, Cognito, etc. and then process them with a Lambda function, so the only thing the API accepts is a URL, which would be the URL for the original photo upload returned by the SDK.


About tests:
---

I didn't write any autoamted tests, but there are several Rested.app test files to facilitate manual testing. You just need to set the URL.

Some of the tests will have `auth_key` params that you'll need to update, so it's easiest to start with `loginAccount.request` and grab an auth key from there for subsequent tests.

These tests are for the happy path, but you could modify them easily to test error cases.

Recommended order:

- loginAccount
- createAccount
- createGallery
- addPhoto (twice, using the gallery you just made)
- showGallery
- showPhoto
- deletePhoto
- deleteGallery
- logoutAccount