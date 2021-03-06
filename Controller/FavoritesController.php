<?php
/**
 * Place at top of extended controller
 * $refuseInit = true; require_once(ROOT.DS.'app'.DS.'Plugin'.DS.'Favorites'.DS.'Controller'.DS.'FavoritesController.php');
 * 
 */
 
 
/**
 * Copyright 2009-2010, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2009-2010, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Favorites Controller
 *
 * @package favorites
 * @subpackage favorites.controllers
 */
class AppFavoritesController extends FavoritesAppController {

/**
 * Controller name
 *
 * @var string
 */
	public $name = 'Favorites';

/**
 * Models to load
 *
 * @var array
 */
	public $uses = array('Favorites.Favorite');

/**
 * Allowed Types of things to be favorited
 * Maps types to models so you don't have to expose model names if you don't want to.
 *
 * @var array
 */
	public $favoriteTypes = array();

/**
 * beforeFilter callback
 *
 * @return void
 */
	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->deny($this->Auth->allowedActions);
		$favorites = (unserialize(__FAVORITES_FAVORITES_SETTINGS));
		$types = $favorites['types'];
		if (!empty($types)) {
			$this->favoriteTypes = array();
			// Keep only key / values (type / model)
			foreach ((array) $types as $key => $type) {
				if (is_string($type)) {
					$this->favoriteTypes[$key] = $type;
				} elseif (is_array($type) && array_key_exists('model', $type)) {
					$this->favoriteTypes[$key] = $type['model'];
				}
			}
		}
		$this->set('authMessage', __d('favorites', 'Authentification required'));
	}

/**
 * Create a new favorite for the specific type.
 *
 * @param string $type
 * @param string $foreignKey
 * @return void
 */
	public function add($type = null, $foreignKey = null) {
		$status = 'error';
		
		if (!isset($this->favoriteTypes[$type])) {
			$message = __d('favorites', 'Invalid object type.');
		} else {
		    
            $model = $this->favoriteTypes[$type];
            if($this->favoriteTypes[$type] == 'Feed') {
                 $model = $model.ucfirst(array_pop(explode('__', $foreignKey)));
            }
			
			$Subject = ClassRegistry::init(ZuhaInflector::pluginize($model) . '.' . $model);
			
			$Subject->id = $foreignKey;
			$this->Favorite->model = $model;
			//$this->Favorite->model = $type;
			if (!$Subject->exists()) {
				$message = __d('favorites', 'Invalid identifier');
			} else {
				try {
					$result = $Subject->saveFavorite($this->Session->read('Auth.User.id'), $Subject->name, $type, $foreignKey);
					if ($result) {
						$status = 'success';
						$message = __d('favorites', 'Successfully added');
					} else {
						$message = __d('favorites', 'Could not be added');
					}
				} catch (Exception $e) {
					$message = __d('favorites', 'Could not be added') . ' ' . $e->getMessage();
				}
			}
		}
		$this->set(compact('status', 'message', 'type', 'foreignKey'));
		if (!empty($this->request->params['isJson'])) {
			return $this->render();
		} else {
			$this->Session->setFlash($message);
			$this->redirect($this->referer());
		}
	}

/**
 * Delete a favorite by Id
 *
 * @param mixed $id Id of favorite to delete.
 * @return void
 */
	public function delete($id = null) {
		$status = 'error';
		if (($message = $this->_isOwner($id)) !== true) {
			// Message defined
		} else if ($this->Favorite->deleteRecord($id)) {
			$status = 'success';
			$message = __d('favorites', 'Removed');
		} else {
			$message = __d('favorites', 'Error, please try again');
		}
		
		$this->set(compact('status', 'message'));
		$this->Session->setFlash($message);
		$this->redirect($this->referer(), -999);
	}

/**
 * Get a list of favorites for a User by type.
 *
 * @param string $type
 * @return void
 */
	public function short_list($type = null) {
		$type = Inflector::underscore($type);
		if (!isset($this->favoriteTypes[$type])) {
			$this->Session->setFlash(__d('favorites', 'Invalid object type.'));
			return;
		}
		$userId = $this->Auth->user('id');
		$favorites = $this->Favorite->getByType($userId);
		$this->set(compact('favorites', 'type'));
		$this->render('list');
	}

/**
 * Get all favorites for a specific user and $type
 *
 * @param string $type Type of favorites to get
 * @return void
 */
	public function list_all($type = null) {
		$type = strtolower($type);
		if (!isset($this->favoriteTypes[$type])) {
			$this->Session->setFlash(__d('favorites', 'Invalid object type.'));
			return;
		}
		$userId = $this->Session->read('Auth.User.id');
		$favorites = $this->Favorite->getByType($userId, array('limit' => 100, 'type' => $type));
		$this->set(compact('favorites', 'type'));
		$this->render('list');
	}

/**
 * Move a favorite up or down a position.
 *
 * @param mixed $id Id of favorite to move.
 * @param string $direction direction to move (only up and down are accepted)
 * @return void
 */
	public function move($id = null, $direction = 'up') {
		$status = 'error';
		$direction = strtolower($direction);
		if (($message = $this->_isOwner($id)) !== true) {
			
		} else if ($direction !== 'up' && $direction !== 'down') {
			$message = __d('favorites', 'Invalid direction');
		} else if ($this->Favorite->move($id, $direction)) {
			$status = 'success';
			$message = __d('favorites', 'Favorite positions updated.');
		} else {
			$message = __d('favorites', 'Unable to change favorite position, please try again');
		}
		$this->set(compact('status', 'message'));
		return $this->redirect($this->referer());
	}

/**
 * Overload Redirect.  Many actions are invoked via Xhr, most of these
 * require a list of current favorites to be returned.
 *
 * @param string $url
 * @param unknown $code
 * @param boolean $exit
 * @return void
 */
	public function redirect($url, $code = null, $exit = true) {
		if ($code == -999) {
			parent::redirect($url, null, $exit);
		}
		if (!empty($this->viewVars['authMessage']) && !empty($this->request->params['isJson'])) {
			$this->RequestHandler->renderAs($this, 'json');
			$this->set('message', $this->viewVars['authMessage']);
			$this->set('status', 'error');
			echo $this->render('add');
			$this->_stop();
		}
		if (!empty($this->request->params['isAjax']) || !empty($this->request->params['isJson'])) {
			return $this->setAction('short_list', $this->Favorite->model);
		} else if (isset($this->viewVars['status']) && isset($this->viewVars['message'])) {
			$this->Session->setFlash($this->viewVars['message'], 'default', array(), $this->viewVars['status']);
		} elseif (!empty($this->viewVars['authMessage'])) {
			$this->Session->setFlash($this->viewVars['authMessage']);
		}

		parent::redirect($url, $code, $exit);
	}

/**
 * Checks that the favorite exists and that it belongs to the current user.
 *
 * @param mixed $id Id of Favorite to check up on.
 * @return boolean true if the current user owns this favorite.
 */
	protected function _isOwner($id) {
		$this->Favorite->id = $id;
		$favorite = $this->Favorite->read();
		$this->Favorite->model = $favorite['Favorite']['model'];
		if (empty($favorite)) {
			return __d('favorites', 'Does not exist.');
		}
		if ($favorite['Favorite']['user_id'] != $this->Session->read('Auth.User.id')) {
			return __d('favorites', 'That does not belong to you.');
		}
		return true;
	}
}

if (!isset($refuseInit)) {
    class FavoritesController extends AppFavoritesController {}
}