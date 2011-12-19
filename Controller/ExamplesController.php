<?php
/**
* Sample ExamplesController 
*
* Demonstrate a typical use case scenario.å
*
* @author Moz Morris <moz@earthview.co.uk>
* @link http://www.earthview.co.uk
* @copyright (c) 2011 Moz Morris
* @license MIT License - http://www.opensource.org/licenses/mit-license.php
 */

App::uses('AppController', 'Controller');
/**
 * Examples Controller
 */
class ExamplesController extends AppController {
  
  /**
   * Load AuthComponent and FacebookAuthenticate handler
   */
  public $components = array(
    'Auth'=> array(
      'loginAction' => array(
        'controller' => 'examples',
        'action'     => 'login'
      ),
      'loginRedirect' => array(
        'controller' => 'examples',
        'action'     => 'my_account'
      ),
      'authError' => 'Did you really think you are allowed to see that?',
      'authenticate' => array(
        'FacebookAuth.Facebook'
      )
    )
  );
  
  public function beforeFilter()
  {
    parent::beforeFilter();
    
    /**
     * Setup FacebookAuth handler
     */
    $this->Auth->authenticate['FacebookAuth.Facebook']['application'] = array(
      'id'     => Configure::read('facebook.app_id'),
      'secret' => Configure::read('facebook.app_secret')
    );
    
    $this->Auth->allowedActions = array_merge($this->Auth->allowedActions, array('login'));
  }
  
  /**
   * Typical login handling
   */
  public function login()
  {
    if (!$this->Auth->login()) {
      /**
       * Get config for Facebook redirect
       */
      $clientId    = Configure::read('facebook.app_id');
      $permissions = implode(',', Configure::read('facebook.permissions'));
      $redirect    = Router::url(false, true);
      $csrfToken   = CakeSession::read('FacebookAuthCSRF');
      
      /**
       * Redirect
       */
      $this->redirect(Configure::read('facebook.oauth_dialg_url') . '?client_id=' . $clientId . '&redirect_uri=' . $redirect . '&scope=' . $permissions . '&state=' . $csrfToken);
    } else {
      $this->redirect(array('action' => 'my_account'));
    }
  }
  
  /**
   * Current Auth user
   */
  public function my_account()
  {
    var_dump($this->Auth->user());
    die();
  }
  
}
