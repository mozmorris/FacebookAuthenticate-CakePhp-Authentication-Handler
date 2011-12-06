<?php
/**
* @author Moz Morris <moz@earthview.co.uk>
* @link http://www.earthview.co.uk
* @copyright (c) 2011 Moz Morris
* @license MIT License - http://www.opensource.org/licenses/mit-license.php
 */

App::uses('BaseAuthenticate', 'Controller/Component/Auth');

/**
 * Facebook Authentication class 
 *
 * @package FacebookAuth.Controller.Component.Auth.FacebookAuthenticate
 */
class FacebookAuthenticate extends BaseAuthenticate {

/**
 * Settings for this object.
 *
 * @var array
 */
	public $settings = array(
	  'application' => array(
      'id'     => null,
      'secret' => null
	  ),
	  'fields' => array(
			'username' => 'email',
			'password' => 'password'
		),
	  'scope' => array(),
	  'urls' => array(
	    'api'          => 'https://graph.facebook.com',
  	  'access_token' => '/oauth/access_token',
  	  'user'         => '/me'
	  ),		
	  'userModel' => 'User'
	);

/**
 * A Component collection, used to get more components.
 *
 * @var ComponentCollection
 */
	protected $_Collection;
	
	/**
	 * request object
	 *
	 * @var CakeRequest
	 */
  protected $_Request;
  
  /**
   * result object
   *
   * @var CakeResponse
   */
  protected $_Reponse;
  
  /**
   * Constructor
   *
   * @param ComponentCollection $collection The Component collection used on this request.
   * @param array $settings Array of settings to use.
   */
	public function __construct(ComponentCollection $collection, $settings) {   
		$this->_Collection = $collection;
		$this->_Request = $this->_Collection->getController()->request;
		$this->_Response = $this->_Collection->getController()->response;
		$this->settings = Set::merge($this->settings, $settings);
		
		if (empty($this->settings['application']['id'])) {
		  throw new InternalErrorException(__('Facebook application ID not configured.'));	  
		}
		
		if (empty($this->settings['application']['secret'])) {
		  throw new InternalErrorException(__('Facebook application secret not configured.'));
		}
	}
	
	/**
   * Authenticate a user based on the request information.
   *
   * @param CakeRequest $request Request to get authentication information from.
   * @param CakeResponse $response A response object that can have headers added.
   * @return mixed Either false on failure, or an array of user data on success.
   */
  public function authenticate(CakeRequest $request, CakeResponse $response) {
    /**
     * Check if the Facebook response code is present in the request
     */
		if ((!empty($this->_Request->query['code']) && !empty($this->_Request->query['state']))
		  && (CakeSession::read('FacebookAuthCSRF') == $this->_Request->query['state'])) {
		  
      /**
       * Create a token request url from the settings and the returned 'code'
       */
		  $tokenRequest = $this->settings['urls']['api']
		                  . $this->settings['urls']['access_token']
                      . "?client_id=" . $this->settings['application']['id'] 
                      . "&redirect_uri=" . urlencode(Router::url(false, true))
                      . "&client_secret=" . $this->settings['application']['secret'] 
                      . "&code=" . $this->_Request->query['code'];
      
      /**
       * Parse the response to get the access token
       */
      $response = @file_get_contents($tokenRequest);
      $params = null;
      parse_str($response, $params);
      
      /**
       * Something went wrong! todo
       */
      if (empty($params['access_token'])) {
        return false;
      }
      
      /**
       * Find, (update|create), & return the user
       */
      return $this->_findUser($params['access_token']);
      
		} elseif (!empty($this->_Request->query['error_reason'])) {
		  /*
		    TODO Handle Don't Allow
		  */
		}
		
	  /**
	   * CSRF value - used when a 'code' is returned from Facebook
	   */
    CakeSession::write('FacebookAuthCSRF', md5(uniqid(rand(), TRUE)));
		
    return false;
  }
  
  /**
   * Find a user record using the standard options.
   *
   * @param string $accessToken Facebook Access token
   * @return Mixed Either false on failure, or an array of user data.
   */
  protected function _findUser($accessToken) {
    
    $userRequest =  $this->settings['urls']['api']
  	                . $this->settings['urls']['user']
  	                . "?access_token=" 
                    . $accessToken;

    $user = json_decode(@file_get_contents($userRequest), true);
    
    if (empty($user)) {
      return false;
    }
    
    /**
     * Check that the user exists
     * A user's details are updated if they exist
     * else they are created
     */
    if (($existingUser = $this->_checkUser($user)) !== false) {
      return $this->_updateUser($existingUser, $user, $accessToken);
    } else {
      return $this->_createUser($user, $accessToken);
    }
  }
  
  /**
   * Check to see if the user exists
   *
   * @param array $user 
   * @return Mixed Either false on failure, or an array of user data.
   */
  protected function _checkUser($user)
  {
    /**
     * Some of this is lifted from BaseAuthenticate
     */
    $userModel = $this->settings['userModel'];
    list($plugin, $model) = pluginSplit($userModel);
    $fields = $this->settings['fields'];
    
    $conditions = array(
      $model . '.' . $fields['username'] => $user['email'],
    );
    
    if (!empty($this->settings['scope'])) {
      $conditions = array_merge($conditions, $this->settings['scope']);
    }
    
    $result = ClassRegistry::init($userModel)->find('first', array(
      'conditions' => $conditions,
      'recursive' => 0
    ));
    
    if (empty($result) || empty($result[$model])) {
      return false;
    }
    
    unset($result[$model][$fields['password']]);
    return $result[$model];    
    
  }
  
  /**
   * Creates a user with Facebook email, id & token
   *
   * @param array $user 
   * @param string $token 
   * @return Mixed Either false on failure, or an array of user data.
   */
  protected function _createUser($user, $token)
  {
    $userModel = $this->settings['userModel'];
    list($plugin, $model) = pluginSplit($userModel);
    $fields = $this->settings['fields'];
    
    $result = ClassRegistry::init($userModel)->save(array(
      'User' => array(
        'email'                 => $user['email'],
        'facebook_user_id'      => $user['id'],
        'facebook_access_token' => $token
      )
    ), false);
        
    if (empty($result) || empty($result[$model])) {
      return false;
    }
    
    return $result[$model];    
  }
  
  /**
   * Update an existing user with Facebook email, id & token
   *
   * @param array $user 
   * @param array $fbDetails 
   * @param string $token 
   * @return Mixed Either false on failure, or an array of user data.
   */
  protected function _updateUser($user, $fbDetails, $token)
  {
    $userModel = $this->settings['userModel'];
    list($plugin, $model) = pluginSplit($userModel);
    $fields = $this->settings['fields'];
    
    $result = ClassRegistry::init($userModel)->save(array(
      'User' => array(
        'id'                    => $user['id'],
        'email'                 => $fbDetails['email'],
        'facebook_user_id'      => $fbDetails['id'],
        'facebook_access_token' => $token
      )
    ), false);    
        
    if (empty($result) || empty($result[$model])) {
      return false;
    }
    
    return $result[$model];    
  }

}
