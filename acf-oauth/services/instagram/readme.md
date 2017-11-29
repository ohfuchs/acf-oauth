# ACF OAuth Instagram Service

This Service allows you to generate an Access Token for the Instagram API through an ACF Field

------------------

### Requirements

This Service requires  __Client Id__ and __Client Secret__ to work.


### Example Usage


The following code shows how to use saved field data to make an Instagram API Call. In this Case `instagram_login` is a registered field, post authors can use to display their latest Instagrams under their post.

```php

add_action( 'the_content', function() {

  $instagram_api_credentials = get_field( 'instagram_login' );

  $request_url = 'https://api.instagram.com/v1/users/self/media/recent/';

  $request_url = add_query_arg(
    'access_token',
    urlencode( $instagram_api_credentials['access_token'] ),
    $request_url
  );

  $response = wp_remote_get( $request_url );

  $media = json_decode( wp_remote_retrieve_body( $response ) );

  foreach( $media -> data as $entry )
    printf( '<img src="%s"/>', $entry->images->thumbnail->url );

});

```
Be aware that this code is **not suitable** for production use.
