<?php
/**
* FacebookAuthenticate handler that works with AuthComponent 
*
* Database-less implementation of the FacebookAuthenticate handler
* See README for detailed installation and usage instructions
*
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
	  'urls' => array(
	    'api'          => 'https://graph.facebook.com',
  	  'access_token' => '/oauth/access_token',
  	  'user'         => '/me'
	  )
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
       * Call to Facebook to get access token
       */
      if (($response = $this->_connect($tokenRequest)) !== false) {
     	  parse_str($response, $response);
      }      
      
      /**
       * Something went wrong! todo
       */
      if (empty($response['access_token'])) {
        return false;
      }
      
      /**
       * Find & return the user from Facebook
       */
      return $this->_findUser($response['access_token']);
      
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

    /**
     * Make request to Facebook
     */     
    $response = $this->_connect($userRequest);

    if (!$response) {
      return false;
    }
    
    $user = json_decode($response, true);
    
    if (!$user) {
      return false;
    }
    
    return $user;
  }
  
  /**
   * Make a connection to given url using cURL if enabled.
   * Falls back to file_get_contents if cURL isn't enabled.
   *
   * @param string $url 
   * @return Mixed Either false on failure, or string of returned page
   */
  public function _connect($url)
  {
    $response = false;
    /**
     * Check to see if cURL is enabled 
     */
    if (is_callable('curl_init')) {
      
      $curl = curl_init($url);

      if (is_resource($curl) === true) {
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        /**
         * Attempt to execute the session until we get a result OR
         * the number of maximum attempts has been reached
         */
        $attempts = 3; 
        while (($response === false) && (--$attempts > 0)) {
          $response = curl_exec($curl);
        }
        
        curl_close($curl);
      } else {
        return false;
      }
    
    /**
     * If cURL is not enabled, we use file_get_contents
     */
    } else {
      $response = @file_get_contents($url);
    }
    
    return $response;
  }

}
