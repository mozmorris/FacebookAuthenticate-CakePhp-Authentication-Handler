# FacebookAuthenticate - CakePhp Facebook Authentication Handler
* Author:  Moz Morris (moz@earthview.co.uk)
* version 1.1
* http://www.earthview.co.uk
* license: MIT

The purpose of the Facebook Authentication Handler is to provide Facebook Authentication for your CakePHP 2.0 or later based application. The handler has been built following Cake's recommended approach for building [custom authentication objects](http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html?#creating-custom-authentication-objects "Authentication &mdash; CakePHP Cookbook v2.0.0 documentation") for use with the built in AuthComponent.
The handler itself is part a *FacebookAuth* plugin, though this is essentially to make the handler easily redistributable with example configuration settings and an example controller detailing a typical use case scenario.

It should be noted that though this handler stores the access token returned by Facebook, it **does not** currently provide a means to makes calls to the Graph API. It's function is to provide authentication. Of course, you could use the access token and your preferred method to communicate with Facebook.

# Changelog

* 1.0 Sharing it with the world for the very first time.
* 1.1 Add email & name fields by default & ensure handler uses configuration settings

# Installation

## Get the code

### Via Git

First clone the repository into a new `app/Plugin/FacebookAuth` directory

    git clone git://github.com/MozMorris/FacebookAuthenticate-CakePhp-Authentication-Handler.git /path/to/your/app/Plugin/FacebookAuth

### Via Composer

Add a dependency to your `composer.json` file. (Looks like the CakePHP convention is to have the `composer.json` file located under `app/`)

    "require": {
      "moz-morris/cakephp-facebook-auth": "dev-master"
    }

Then `composer install` or `composer update`. You might need to add `"minimum-stability": "dev"` to you `composer.json` file.


## Facebook Application & App Configuration

1. Rename the example configuration *FacebookConfig.ini.example* to **FacebookConfig.ini**. It can be found under app/Plugin/FacebookAuth/Config/
2. Head on over to the [Facebook Developer App](https://developers.facebook.com/apps) and either setup a new application to get a App ID/API Key & App Secret, or note down the ones from your existing app. **NOTE: Your 'App Domain' must be the same host as you're accessing your site with to enable Auth.** Otherwise, when you attempt to authenticate, Facebook will return "API Error Code: 191 | API Error Description: The specified URL is not owned by the application | Error Message: Invalid redirect_uri: Given URL is not allowed by the Application configuration."
3. Update your FacebookConfig.ini with the App ID/API Key & App Secret.
4. Load the plugin and it's configuration in your bootstrap file *app/Config/bootstrap.php*
5. Configure the FacebookAuth handler at runtime

Loading the plugin and configuration (*bootstrap.php*):

    /**
     * Load custom configuration files using the IniReader class
     */
    App::uses('IniReader', 'Configure');
    Configure::config('default', new IniReader(APP . 'Plugin' . DS . 'FacebookAuth' . DS . 'Config' . DS));
    Configure::load('FacebookConfig', 'default');

    /**
     * Load Facebook Plugin
     */
    CakePlugin::load('FacebookAuth');

Configuring the handler at runtime:

    public function beforeFilter()
    {
      parent::beforeFilter();

      /**
       * Configure FacebookAuth handler
       */
      $this->Auth->authenticate['FacebookAuth.Facebook']['application'] = array(
       'id'     => Configure::read('facebook.app_id'),
       'secret' => Configure::read('facebook.app_secret')
      );
    }

## Database

Add `email`, `name`, `facebook_user_id`, `facebook_access_token` fields to your User model. In this example we're adding the fields to the _users_ table which is pretty much the standard for Cake apps using some kind of user authentication. If your app is slightly different, then make the relevant changes.

    ALTER TABLE `users` ADD `email` VARCHAR(255)  NOT NULL  DEFAULT '';
    ALTER TABLE `users` ADD `name` VARCHAR(255)  NOT NULL  DEFAULT '';
    ALTER TABLE `users` ADD `facebook_user_id` BIGINT  NULL  DEFAULT NULL;
    ALTER TABLE `users` ADD `facebook_access_token` VARCHAR(255)  NULL  DEFAULT NULL;


# Usage

Basic example:

    public $components = array(
      'Auth'=> array(
        'authenticate' => array(
          'FacebookAuth.Facebook'
        )
      )
    );

Slightly more interesting:

    public $components = array(
      'Auth'=> array(
        'loginAction' => array(
          'controller' => 'users',
          'action' => 'login'
        ),
        'loginRedirect' => array(
          'controller' => 'users',
          'action' => 'my_account'
        ),
        'authError' => 'Did you really think you are allowed to see that?',
        'authenticate' => array(
          'FacebookAuth.Facebook' => array(
            'fields' => array(
              'username' => 'email',
        			'password' => 'password'
            )
          )
        )
      )
    );

See the Cake Book for more ways to configure your [CakePHP AuthComponent](http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html "Authentication &mdash; CakePHP Cookbook v2.0.0 documentation").

There is also an example controller included within the plugin that shows how you could implement the handler.

    app/Plugin/FacebookAuth/Controller/ExampleController.php

