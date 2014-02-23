<?php
/**
 * @package		jVArcade
 * @version		2.1
 * @date		2014-01-12
 * @copyright		Copyright (C) 2007 - 2014 jVitals Digital Technologies Inc. All rights reserved.
 * @license		http://www.gnu.org/copyleft/gpl.html GNU/GPLv3 or later
 * @link		http://jvitals.com
 */


// no direct access
defined('_JEXEC') or die('Restricted access');

//jimport('joomla.application.component.model');

class jvarcadeModelCommon extends JModelLegacy {
	private $dbo;
	var $_pagination = null;
	var $_conf = null;
	var $_confobj = null;
	var $_orderby = null;
	var $_orderdir = null;
	var $_searchfields = null;
	
	function __construct() {
		parent::__construct();
		$this->dbo = JFactory::getDBO();
		$mainframe = JFactory::getApplication('site');
        global $option;
 
        // Get pagination request variables
		$limit = $mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
        $limitstart = JRequest::getVar('limitstart', 0, '', 'int');
 
        // In case limit has been changed, adjust it
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);
 
        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

	}
	
	function getDBerr() {
		$mainframe = JFactory::getApplication('site');
		$mainframe->enqueueMessage($this->dbo->getErrorMsg(), 'error');
	}
	
	function getTotal(){
		$this->dbo->setQuery('SELECT FOUND_ROWS();');
		$result = $this->dbo->loadResult();
		return $result;
	}
	
	function getPagination() {
		// Load the content if it doesn't already exist
		if (empty($this->_pagination)) {
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}
		return $this->_pagination;
	}
	
	function setOrderBy($orderby) {
		$this->_orderby = $orderby;
	}

	function setOrderDir($orderdir) {
		$this->_orderdir = $orderdir;
	}

	function setSearchFields($searchfields) {
		foreach ($searchfields as $key => $value) {
			$this->_searchfields[str_replace('filter_', '', $key)] = $value;
			//var_dump($value);
		}
	}
	
	function getConf() {
		if (!$this->_conf) {
			$this->_loadConf();
		}
		return $this->_conf;
	}
	
	private function _loadConf() {
		if (!$this->_conf) {
			$this->dbo->setQuery("SELECT * FROM #__jvarcade_settings ORDER BY " . $this->dbo->quoteName('group') . ", " . $this->dbo->quoteName('ord') . "");
			$this->_conf = $this->dbo->loadAssocList();
			return (boolean)$this->_conf;
		}
		return true;
	}
	
	function getConfObj() {
		if (!$this->_confobj) {
			$this->_loadConfObj();
		}
		return $this->_confobj;
	}
	
	private function _loadConfObj() {
		static $loadedconf;
		
		if (!$loadedconf) {
			$my = JFactory::getUser();
			$app = JFactory::getApplication();
			$this->dbo->setQuery("SELECT * FROM #__jvarcade_settings ORDER BY " . $this->dbo->quoteName('group') . ", " . $this->dbo->quoteName('ord') . "");
			$res = $this->dbo->loadObjectList();
			$obj = new stdClass();
			if (count($res)) {
				foreach ($res as $row) {
					$optname = $row->optname;
					$obj->$optname = $row->value;
				}
			}
			
			// TIMEZONE - if user is logged in we use the user timezone, if guest - we use timezone in global settings
			$obj->timezone = ((int)$my->guest ? $app->getCfg('offset') : $my->getParam('timezone', $app->getCfg('offset')));
			
			// DIFF BETWEEN SERVER AND USER TIMEZONE - date already contains the server timezone offset so we subtract it
			if (JVA_COMPATIBLE_MODE == '15') {
				$obj->tz_diff = ($obj->timezone*3600 - date('Z'))/3600;
			} else {
				$dateTimeZone = new DateTimeZone($obj->timezone);
				$obj->tz_diff = ($dateTimeZone->getOffset(new DateTime("now", $dateTimeZone)) - (int)date('Z'))/3600;
			}
			
			$this->_confobj = $loadedconf = $obj;
			return (boolean)$this->_confobj;
		} else {
			$this->_confobj = $loadedconf;
		}
		return true;
	}
	
	function configSave() {
		$mainframe = JFactory::getApplication('site');
		$config_save = (int)JRequest::getVar('config_save', 0);
		if ($config_save) {
			$confdb = $this->getConf();
			$conf = array();
			foreach ($confdb as $obj) {
				$confvalue = JRequest::getVar($obj['optname'], '', 'POST', 'none', 2);
				if ($obj['optname'] == 'TagPerms' && is_array($confvalue)) $confvalue = implode(',', $confvalue);
				if ($obj['optname'] == 'DloadPerms' && is_array($confvalue)) $confvalue = implode(',', $confvalue);
				if (strpos($obj['optname'],'alias') !== false) $confvalue = str_replace(array(' '), array(''), trim($confvalue));
				if (strlen(trim($confvalue))) {
					$conf[$obj['optname']] = trim($confvalue);
				} else {
					$conf[$obj['optname']] = $obj['value'];
				}
			}
			
			foreach ($conf as $optname => $value) {
				$this->dbo->setQuery("UPDATE #__jvarcade_settings SET " . $this->dbo->quoteName('value') . " = " . $this->dbo->Quote($value) . " 
					WHERE " . $this->dbo->quoteName('optname') . " = " . $this->dbo->Quote($optname) . "");
				$this->dbo->query();
			}
			$mainframe->redirect('index.php?option=com_jvarcade&task=settings');
			exit;
		}
	}
	
	function getContentRatingList() {
		$this->dbo->setQuery('SELECT id, name FROM #__jvarcade_contentrating ORDER BY id');
		return $this->dbo->loadObjectList();
	}
	
	function getAcl() {
		$query = (JVA_COMPATIBLE_MODE == '15') ? 'SELECT id, name FROM #__core_acl_aro_groups' : 'SELECT id, title as name FROM #__usergroups';
		$this->dbo->setQuery($query);
		return $this->dbo->loadAssocList('id');
	}
	
	function getGamesCount() {
		$this->dbo->setQuery('SELECT count(*) as count FROM #__jvarcade_games');
		return $this->dbo->loadResult();
	}

	function getScoresCount() {
		$this->dbo->setQuery('SELECT count(*) as count FROM #__jvarcade');
		return $this->dbo->loadResult();
	}

	function getScores() {
	
		if ($this->_orderby) {
			$orderby = ' ORDER BY ' . $this->_orderby . ' ' . ($this->_orderdir ? $this->_orderdir : '');
		} else {
			$orderby = 'ORDER BY p.date DESC';
		}
		
		$where = array();
		$wherestr = '';
		
		if (isset($this->_searchfields) && is_array($this->_searchfields) && count($this->_searchfields) > 0) {
			foreach ($this->_searchfields as $name => $value) {
				if ($value != '') {
					$escaped = $this->dbo->Quote( '%'.$this->dbo->getEscaped($value, true ).'%', false );
					$where[] = $name . ' LIKE ' . $escaped;
				}
			}
		}
		$wherestr = (count($where) ? ' WHERE ( ' . implode( ' ) AND ( ', $where ) . ' )' : '' );
		
		$query = "SELECT SQL_CALC_FOUND_ROWS p.*, u.username, g.title " . 
					"FROM #__jvarcade p " . 
						"LEFT JOIN #__users u ON u.id = p.userid " . 
						"JOIN #__jvarcade_games g ON g.id = p.gameid " . 
					$wherestr . ' ' . 
					$orderby;
		$this->dbo->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
		return $this->dbo->loadObjectList();
	}

	function getLatestScores() {
		$query = "SELECT p.userid, p.score, u.username, g.title " . 
					"FROM #__jvarcade p " . 
						"JOIN #__jvarcade_games g ON g.id = p.gameid " . 
						"LEFT JOIN #__users u ON u.id = p.userid " . 
				"ORDER by p.date DESC LIMIT 5 ";
		$this->dbo->setQuery($query);
		return $this->dbo->loadObjectList();
	}

	function getLatestGames() {
		$query = "SELECT title, numplayed FROM #__jvarcade_games ORDER by id DESC LIMIT 5 ";
		$this->dbo->setQuery($query);
		return $this->dbo->loadObjectList();
	}


	function deleteScore() {
		$mainframe = JFactory::getApplication('site');
		$id = JRequest::getVar('cid', 'scores');
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		$query = "DELETE FROM #__jvarcade WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		$this->dbo->query();
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=manage_scores');
	}
	
	function scorePublish($published) {
		$mainframe = JFactory::getApplication('site');
		$id = JRequest::getVar('cid');
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		$query = "UPDATE #__jvarcade SET " . $this->dbo->quoteName('published') . " = " . $this->dbo->Quote((int)$published) . "
			WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		$this->dbo->query();
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=manage_scores');

	}
	
	function getFolders($id = 0) {
	
		if ($this->_orderby) {
			$orderby = ' ORDER BY ' . $this->_orderby . ' ' . ($this->_orderdir ? $this->_orderdir : '');
		} else {
			$orderby = 'ORDER BY f.name DESC';
		}
		
		$where = array();
		$wherestr = '';
		
		if ((int)$id) $where[] = 'f.id = ' . (int)$id;
		
		if (isset($this->_searchfields) && is_array($this->_searchfields) && count($this->_searchfields) > 0) {
			foreach ($this->_searchfields as $name => $value) {
				if ($value != '') {
					$escaped = $this->dbo->Quote( '%'.$this->dbo->getEscaped($value, true ).'%', false );
					$where[] = $name . ' LIKE ' . $escaped;
				}
			}
		}
		$wherestr = (count($where) ? ' WHERE ( ' . implode( ' ) AND ( ', $where ) . ' )' : '' );
		
		$query = "SELECT SQL_CALC_FOUND_ROWS f.*, p.name as parentname " . 
					"FROM #__jvarcade_folders f " . 
						"LEFT JOIN #__jvarcade_folders p ON p.id = f.parentid " . 
					$wherestr . ' ' . 
					$orderby;
		$this->dbo->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
		$return  = $this->dbo->loadObjectList();
		return $return;
	}
	
	function getFolderList() {
		$this->dbo->setQuery('SELECT id, name FROM #__jvarcade_folders ORDER BY id');
		return $this->dbo->loadObjectList();
	}
	
	function deleteFolder() {
		$mainframe = JFactory::getApplication('site');
		$id = $mainframe->input->get('cid', null, 'folders', array());
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		$query = "DELETE FROM #__jvarcade_folders WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		$this->dbo->query();
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=manage_folders');
	}
	
	function folderPublish($published) {
		$mainframe = JFactory::getApplication('site');
		$id = $mainframe->input->get('cid', null, null);
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		$query = "UPDATE #__jvarcade_folders SET " . $this->dbo->quoteName('published') . " = " . $this->dbo->Quote((int)$published) . "
			WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		$this->dbo->query();
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=manage_folders');

	}
	
	function saveFolder() {
		$mainframe = JFactory::getApplication('site');
	
		$task = $mainframe->input->get('task');
		$post = JRequest::get('post');
		$viewpermissions = JRequest::getVar('viewpermissions', array());
		$imgfile = JRequest::getVar('image', null, 'files', 'array');
		$uploaderr = '';
		$post['alias'] = isset($post['alias']) && $post['alias'] ? $post['alias'] : $post['name'];
		$post['alias'] = str_replace(array(' '), array(''), trim($post['alias']));
		
		// Process data
		
		if ((int)$post['id']) {
			$folderid = (int)$post['id'];
			$query = "UPDATE #__jvarcade_folders SET 
				" . $this->dbo->quoteName('name') . " = " . $this->dbo->Quote($post['name']) . ",
				" . $this->dbo->quoteName('alias') . " = " . $this->dbo->Quote($post['alias']) . ",
				" . $this->dbo->quoteName('description') . " = " . $this->dbo->Quote($post['description']) . ",
				" . $this->dbo->quoteName('published') . " = " . $this->dbo->Quote((int)$post['published']) . ",
				" . $this->dbo->quoteName('parentid') . " = " . $this->dbo->Quote((int)$post['parentid']) . ",
				" . $this->dbo->quoteName('viewpermissions') . " = " . $this->dbo->Quote(implode(',', $viewpermissions)) . "
			WHERE " . $this->dbo->quoteName('id') . " = " . (int)$post['id'];
			$this->dbo->setQuery($query);
			$this->dbo->query();
		} else {
			$query = "INSERT INTO #__jvarcade_folders " . 
						"(" . $this->dbo->quoteName('name') . ", " . $this->dbo->quoteName('alias') . ", " . $this->dbo->quoteName('description') . ", " . $this->dbo->quoteName('published') . ", " . 
							$this->dbo->quoteName('parentid') . ", " . $this->dbo->quoteName('viewpermissions') . ") " . 
					"VALUES (" . $this->dbo->Quote($post['name']) . "," . $this->dbo->Quote($post['alias']) . "," . $this->dbo->Quote($post['description']) . "," . $this->dbo->Quote((int)$post['published']) . "," . 
							$this->dbo->Quote((int)$post['parentid']) . "," . $this->dbo->Quote(implode(',', $viewpermissions)) . ")";
			$this->dbo->setQuery($query);
			$this->dbo->query();
			$folderid = (int)$this->dbo->insertid();
		}
		
		// Process folder image upload
		if ((int)$folderid && is_array($imgfile) && $imgfile['size'] > 0) {
		
			$imgext = substr($imgfile['name'], strrpos($imgfile['name'], '.'));
			list($imgwith, $imgheight) = @getimagesize($imgfile['tmp_name']);

			if (!$uploaderr && $imgfile['error']) {
				$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR', $imgfile['name']);
			}
			if (!$uploaderr && (strpos($imgfile['type'], 'image') === false)) {
				$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_NOT_IMAGE', $imgfile['name']);
			}
			if (!$uploaderr && ($imgwith > 64 || $imgheight > 64)) {
				$uploaderr = JText::_('COM_JVARCADE_UPLOAD_BIGGER_DIMS');
			}
			if (!$uploaderr) {
				jimport('joomla.filesystem.file');
				$uploaded = JFile::upload($imgfile['tmp_name'], JVA_IMAGES_INCPATH . 'folders/' . $folderid . $imgext);
				if ($uploaded) {
					$this->dbo->setQuery('UPDATE #__jvarcade_folders SET ' . 
											$this->dbo->quoteName('imagename') . ' = ' . $this->dbo->Quote($folderid . $imgext) . 
										' WHERE ' . $this->dbo->quoteName('id') . ' = ' . (int)$folderid);
					$this->dbo->query();
				} else {
					$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR_MOVING', $imgfile['name']);
				}
			} 
			if ($uploaderr) $mainframe->enqueueMessage($uploaderr, 'notice');
		}
		
		if ($task == 'applyfolder') {
			$url = 'index.php?option=com_jvarcade&task=editfolder&id=' . (int)$folderid;
		} else {
			$url = 'index.php?option=com_jvarcade&task=manage_folders';
		}
		
		$mainframe->redirect($url, JText::_('COM_JVARCADE_FOLDERS_SAVE_SUCCESS'));
	}

	function getGames($id = 0) {
	
		if ($this->_orderby) {
			$orderby = ' ORDER BY ' . $this->_orderby . ' ' . ($this->_orderdir ? $this->_orderdir : '');
		} else {
			$orderby = 'ORDER BY g.id DESC';
		}
		
		$where = array();
		$wherestr = '';
		
		if ((int)$id) $where[] = 'g.id = ' . (int)$id;
		
		if (isset($this->_searchfields) && is_array($this->_searchfields) && count($this->_searchfields) > 0) {
			foreach ($this->_searchfields as $name => $value) {
				if ($value != '') {
					$escaped = $this->dbo->Quote( '%'.$this->dbo->escape($value, true ).'%', true );
					$where[] = $name . ' LIKE ' . $escaped;
				}
			}
		}
		$wherestr = (count($where) ? ' WHERE ( ' . implode( ' ) AND ( ', $where ) . ' )' : '' );
		
		$query = "SELECT SQL_CALC_FOUND_ROWS g.*, f.name " . 
					"FROM #__jvarcade_games g " . 
						"LEFT JOIN #__jvarcade_folders f ON f.id = g.folderid " . 
					$wherestr . ' ' . 
					$orderby;
		$this->dbo->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
		$return = $this->dbo->loadObjectList();
		return $return;
	}

	function getGameTitles($id = array()) {
		if (is_array($id) && count($id)) {
			$query = 'SELECT title FROM #__jvarcade_games WHERE id IN (' . implode(',', $id) . ') ORDER BY id DESC';
			$this->dbo->setQuery($query);
			$return = $this->dbo->loadColumn();
			return is_array($return) && count($return) ? $return : array() ;
		}
		return array();
	}

	function getGameIdTitles() {
		$query = 'SELECT id, title FROM #__jvarcade_games ORDER BY id DESC';
		$this->dbo->setQuery($query);
		$return = $this->dbo->loadObjectList();
		return is_array($return) && count($return) ? $return : array() ;

	}
	
	function deleteGame() {
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		$mainframe = JFactory::getApplication('site');
		$id = JRequest::getVar('cid', 'games');
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		
		// Delete the game file and image as well as the gamedata folder if exists
		$this->dbo->setQuery('SELECT filename, imagename, gamename FROM #__jvarcade_games WHERE ' . $this->dbo->quoteName('id') . ' IN (' . implode(',', $id) . ')');
		$games = $this->dbo->loadObjectList();
		foreach($games as $game) {
			if (JFile::exists(JVA_GAMES_INCPATH . '/' . $game->filename)) @JFile::delete(JVA_GAMES_INCPATH . $game->filename);
			if (JFile::exists(JVA_IMAGES_INCPATH . 'games/' . $game->imagename)) @JFile::delete(JVA_IMAGES_INCPATH . 'games/' . $game->imagename);
			if (JFolder::exists(JPATH_SITE . '/arcade/gamedata/' . $game->gamename)) {
				@JFolder::delete(JPATH_SITE . '/arcade/gamedata/' . $game->gamename);
			}
		}
		
		$query = "DELETE FROM #__jvarcade_games WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		
		if ($this->dbo->query()) {
			$this->dbo->setQuery("DELETE FROM #__jvarcade WHERE " . $this->dbo->quoteName('gameid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			$this->dbo->setQuery("DELETE FROM #__jvarcade_contestgame WHERE " . $this->dbo->quoteName('gameid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			$this->dbo->setQuery("DELETE FROM #__jvarcade_contestscore WHERE " . $this->dbo->quoteName('gameid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			$this->dbo->setQuery("DELETE FROM #__jvarcade_faves WHERE " . $this->dbo->quoteName('gid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			$this->dbo->setQuery("DELETE FROM #__jvarcade_gamedata WHERE " . $this->dbo->quoteName('gameid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			$this->dbo->setQuery("DELETE FROM #__jvarcade_lastvisited WHERE " . $this->dbo->quoteName('gameid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			$this->dbo->setQuery("DELETE FROM #__jvarcade_ratings WHERE " . $this->dbo->quoteName('gameid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			$this->dbo->setQuery("DELETE FROM #__jvarcade_tags WHERE " . $this->dbo->quoteName('gameid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();

		}
		
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=manage_games');
	}
	
	function gamePublish($published) {
		$mainframe = JFactory::getApplication('site');
		$id = JRequest::getVar('cid');
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		$query = "UPDATE #__jvarcade_games SET " . $this->dbo->quoteName('published') . " = " . $this->dbo->Quote((int)$published) . "
			WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		$this->dbo->query();
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=manage_games');

	}
	
	function saveGame() {
		$mainframe = JFactory::getApplication('site');
		$task = JRequest::getVar('task');
		$post = JRequest::get('post');
		// here we take the raw result because we want to preserve the html code
		$description = JRequest::getVar('description', '', 'post', 'none', 2);
		//~ $viewpermissions = JRequest::getVar('viewpermissions', array());
		$imgfile = JRequest::getVar('image', null, 'files', 'array');
		$gamefile = JRequest::getVar('file', null, 'files', 'array');
		$uploaderr = '';
		$uploaderr2 = '';
		
		// Process data
		
		if ((int)$post['id']) {
			$gameid = (int)$post['id'];
			$query = "UPDATE #__jvarcade_games SET 
				" . $this->dbo->quoteName('title') . " = " . $this->dbo->Quote($post['title']) . ",
				" . $this->dbo->quoteName('description') . " = " . $this->dbo->Quote($description) . ",
				" . $this->dbo->quoteName('height') . " = " . $this->dbo->Quote((int)$post['height']) . ",
				" . $this->dbo->quoteName('width') . " = " . $this->dbo->Quote((int)$post['width']) . ",
				" . $this->dbo->quoteName('numplayed') . " = " . $this->dbo->Quote((int)$post['numplayed']) . ",
				" . $this->dbo->quoteName('background') . " = " . $this->dbo->Quote($post['background']) . ",
				" . $this->dbo->quoteName('published') . " = " . $this->dbo->Quote((int)$post['published']) . ",
				" . $this->dbo->quoteName('reverse_score') . " = " . $this->dbo->Quote((int)$post['reverse_score']) . ",
				" . $this->dbo->quoteName('scoring') . " = " . $this->dbo->Quote((int)$post['scoring']) . ",
				" . $this->dbo->quoteName('folderid') . " = " . $this->dbo->Quote((int)$post['folderid']) . ",
				" . $this->dbo->quoteName('window') . " = " . $this->dbo->Quote((int)$post['window']) . ",
				" . $this->dbo->quoteName('contentratingid') . " = " . $this->dbo->Quote((int)$post['contentratingid']) . ",
				" . $this->dbo->quoteName('ajaxscore') . " = " . $this->dbo->Quote((int)$post['ajaxscore']) . ",
				" . $this->dbo->quoteName('mochi') . " = " . $this->dbo->Quote((int)$post['mochi']) . "
			WHERE " . $this->dbo->quoteName('id') . " = " . (int)$post['id'];
			$this->dbo->setQuery($query);
			if (!$this->dbo->query()) $this->getDBerr();
		} else {
			$query = "INSERT INTO #__jvarcade_games " . 
					  "(" . $this->dbo->quoteName('gamename') . ", " . $this->dbo->quoteName('title') . ", " . $this->dbo->quoteName('description') . ", " . 
							$this->dbo->quoteName('height') . ", " . $this->dbo->quoteName('width') . ", " . $this->dbo->quoteName('numplayed') . ", " . 
							$this->dbo->quoteName('background') . ", " . $this->dbo->quoteName('published') . ", " . $this->dbo->quoteName('reverse_score') . ", " . 
							$this->dbo->quoteName('scoring') . ", " . $this->dbo->quoteName('folderid') . ", " . $this->dbo->quoteName('window') . ", " . 
							$this->dbo->quoteName('contentratingid') . ", " . $this->dbo->quoteName('ajaxscore') . ", " . $this->dbo->quoteName('mochi'). ") " . 
					"VALUES (" . $this->dbo->Quote($post['gamename']) . "," . $this->dbo->Quote($post['title']) . "," . $this->dbo->Quote($post['description']) . "," . 
								$this->dbo->Quote((int)$post['height']) . "," . $this->dbo->Quote((int)$post['width']) . "," . $this->dbo->Quote((int)$post['numplayed']) . "," . 
								$this->dbo->Quote($post['background']) . "," . $this->dbo->Quote((int)$post['published']) . "," . $this->dbo->Quote((int)$post['reverse_score']) . "," . 
								$this->dbo->Quote((int)$post['scoring']) . "," . $this->dbo->Quote((int)$post['folderid']) . "," . $this->dbo->Quote((int)$post['window']) . "," . 
								$this->dbo->Quote((int)$post['contentratingid']) . "," . $this->dbo->Quote((int)$post['ajaxscore']) . "," . $this->dbo->Quote((int)$post['mochi']) . ")";
			$this->dbo->setQuery($query);
			if (!$this->dbo->query()) $this->getDBerr();
			$gameid = (int)$this->dbo->insertid();
		}
		
		// Process game image upload
		if ((int)$gameid && is_array($imgfile) && $imgfile['size'] > 0) {
		
			list($imgwith, $imgheight) = @getimagesize($imgfile['tmp_name']);

			if (!$uploaderr && $imgfile['error']) {
				$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR', $imgfile['name']);
			}
			if (!$uploaderr && (strpos($imgfile['type'], 'image') === false)) {
				$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_NOT_IMAGE', $imgfile['name']);
			}
			if (!$uploaderr && ($imgwith > 50 || $imgheight > 50)) {
				$uploaderr = JText::_('COM_JVARCADE_UPLOAD_BIGGER_DIMS2');
			}
			if (!$uploaderr) {
				jimport('joomla.filesystem.file');
				$uploaded = JFile::upload($imgfile['tmp_name'], JVA_IMAGES_INCPATH . 'games/' . $gameid . '_' . $imgfile['name']);
				if ($uploaded) {
					$this->dbo->setQuery('UPDATE #__jvarcade_games SET ' . 
											$this->dbo->quoteName('imagename') . ' = ' . $this->dbo->Quote($gameid . '_' . $imgfile['name']) . 
										' WHERE ' . $this->dbo->quoteName('id') . ' = ' . (int)$gameid);
					if (!$this->dbo->query()) $this->getDBerr();
				} else {
					$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR_MOVING', $imgfile['name']);
				}
			} 
			if ($uploaderr) $mainframe->enqueueMessage($uploaderr, 'notice');
		}
		
		// Process game file upload
		if ((int)$gameid && is_array($gamefile) && $gamefile['size'] > 0) {
		
			if (!$uploaderr2 && $gamefile['error']) {
				$uploaderr2 = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR', $gamefile['name']);
			}
			if (!$uploaderr2) {
				jimport('joomla.filesystem.file');
				$uploaded = JFile::upload($gamefile['tmp_name'], JVA_GAMES_INCPATH . $gamefile['name']);
				if ($uploaded) {
					$this->dbo->setQuery('UPDATE #__jvarcade_games SET ' . 
											$this->dbo->quoteName('filename') . ' = ' . $this->dbo->Quote($gamefile['name']) . 
										' WHERE ' . $this->dbo->quoteName('id') . ' = ' . (int)$gameid);
					if (!$this->dbo->query()) $this->getDBerr();
				} else {
					$uploaderr2 = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR_MOVING', $gamefile['name']);
				}
			} 
			if ($uploaderr2) $mainframe->enqueueMessage($uploaderr2, 'notice');
		}
		
		if ($task == 'applygame') {
			$url = 'index.php?option=com_jvarcade&task=editgame&id=' . (int)$gameid;
		} else {
			$url = 'index.php?option=com_jvarcade&task=manage_games';
		}
		
		$mainframe->redirect($url, JText::_('COM_JVARCADE_GAMES_SAVE_SUCCESS'));
	}

	function getContests($id = 0) {
	
		if ($this->_orderby) {
			$orderby = ' ORDER BY ' . $this->_orderby . ' ' . ($this->_orderdir ? $this->_orderdir : '');
		} else {
			$orderby = 'ORDER BY id DESC';
		}
		
		$where = array();
		$wherestr = '';
		
		if ((int)$id) $where[] = 'id = ' . (int)$id;
		
		if (isset($this->_searchfields) && is_array($this->_searchfields) && count($this->_searchfields) > 0) {
			foreach ($this->_searchfields as $name => $value) {
				if ($value != '') {
					$escaped = $this->dbo->Quote( '%'.$this->dbo->getEscaped($value, true ).'%', true );
					$where[] = $name . ' LIKE ' . $escaped;
				}
			}
		}
		$wherestr = (count($where) ? ' WHERE ( ' . implode( ' ) AND ( ', $where ) . ' )' : '' );
		
		$query = "SELECT SQL_CALC_FOUND_ROWS * " . 
					"FROM #__jvarcade_contest " . 
					$wherestr . ' ' . 
					$orderby;
		$this->dbo->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
		$return = $this->dbo->loadObjectList();
		return $return;
	}
	
	function contestPublish($published) {
		$mainframe = JFactory::getApplication('site');
		$id = JRequest::getVar('cid');
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		$query = "UPDATE #__jvarcade_contest SET " . $this->dbo->quoteName('published') . " = " . $this->dbo->Quote((int)$published) . "
			WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		$this->dbo->query();
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=contests');

	}
	
	function deleteContest() {
		$mainframe = JFactory::getApplication('site');
		$id = JRequest::getVar('cid', 'contests');
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		
		$query = "DELETE FROM #__jvarcade_contest WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		
		if ($this->dbo->query()) {
			$this->dbo->setQuery("DELETE FROM #__jvarcade_contestgame WHERE " . $this->dbo->quoteName('contestid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			$this->dbo->setQuery("DELETE FROM #__jvarcade_contestmember WHERE " . $this->dbo->quoteName('contestid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			$this->dbo->setQuery("DELETE FROM #__jvarcade_contenstscore WHERE " . $this->dbo->quoteName('contestid') . " IN (" . implode(',', $id) . ")");
			$this->dbo->query();
			
			$this->dbo->setQuery("SELECT id FROM #__jvarcade_leaderboard WHERE " . $this->dbo->quoteName('contestid') . " IN (" . implode(',', $id) . ")");
			$ids = $this->dbo->loadResultArray();
			if (is_array($ids) && count($ids)) {
				$this->dbo->setQuery("DELETE FROM #__jvarcade_leaderboard WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $ids) . ")");
				$this->dbo->query();
				$this->dbo->setQuery("DELETE FROM #__jvarcade_leaderboarddetail WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $ids) . ")");
				$this->dbo->query();
			}
		}
		
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=manage_games');
	}
	
	function saveContest() {
		$mainframe = JFactory::getApplication('site');
		$task = JRequest::getVar('task');
		$post = JRequest::get('post');
		$imgfile = JRequest::getVar('image', null, 'files', 'array');
		$uploaderr = '';
		
		// Process data
		
		if ((int)$post['id']) {
			$contestid = (int)$post['id'];
			$query = "UPDATE #__jvarcade_contest SET 
				" . $this->dbo->quoteName('name') . " = " . $this->dbo->Quote($post['name']) . ",
				" . $this->dbo->quoteName('description') . " = " . $this->dbo->Quote($post['description']) . ",
				" . $this->dbo->quoteName('startdatetime') . " = " . $this->dbo->Quote($post['startdatetime']) . ",
				" . $this->dbo->quoteName('enddatetime') . " = " . $this->dbo->Quote($post['enddatetime']) . ",
				" . $this->dbo->quoteName('islimitedtoslots') . " = " . $this->dbo->Quote((int)$post['islimitedtoslots']) . ",
				" . $this->dbo->quoteName('published') . " = " . $this->dbo->Quote((int)$post['published']) . ",
				" . $this->dbo->quoteName('hasadvertisedstarted') . " = " . $this->dbo->Quote((int)$post['hasadvertisedstarted']) . ",
				" . $this->dbo->quoteName('hasadvertisedended') . " = " . $this->dbo->Quote((int)$post['hasadvertisedended']) . ",
				" . $this->dbo->quoteName('maxplaycount') . " = " . $this->dbo->Quote((int)$post['maxplaycount']) . "
			WHERE " . $this->dbo->quoteName('id') . " = " . (int)$post['id'];
			$this->dbo->setQuery($query);
			$this->dbo->query();
		} else {
			$query = "INSERT INTO #__jvarcade_contest " . 
					  "(" . $this->dbo->quoteName('name') . ", " . $this->dbo->quoteName('description') . ", " . $this->dbo->quoteName('startdatetime') . ", " . 
							$this->dbo->quoteName('enddatetime') . ", " . $this->dbo->quoteName('islimitedtoslots') . ", " . $this->dbo->quoteName('published') . ", " . 
							$this->dbo->quoteName('hasadvertisedstarted') . ", " . $this->dbo->quoteName('hasadvertisedended') . ", " . $this->dbo->quoteName('maxplaycount') . ") " . 
					"VALUES (" . $this->dbo->Quote($post['name']) . "," . $this->dbo->Quote($post['description']) . "," . $this->dbo->Quote($post['startdatetime']) . "," . 
								$this->dbo->Quote($post['enddatetime']) . "," . $this->dbo->Quote((int)$post['islimitedtoslots']) . "," . $this->dbo->Quote((int)$post['published']) . "," . 
								$this->dbo->Quote((int)$post['hasadvertisedstarted']) . "," . $this->dbo->Quote((int)$post['hasadvertisedended']) . "," . $this->dbo->Quote((int)$post['maxplaycount']) . ")";
			$this->dbo->setQuery($query);
			$this->dbo->query();
			$contestid = (int)$this->dbo->insertid();
		}
		
		// Process contet image upload
		if ((int)$contestid && is_array($imgfile) && $imgfile['size'] > 0) {
		
			list($imgwith, $imgheight) = @getimagesize($imgfile['tmp_name']);

			if (!$uploaderr && $imgfile['error']) {
				$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR', $imgfile['name']);
			}
			if (!$uploaderr && (strpos($imgfile['type'], 'image') === false)) {
				$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_NOT_IMAGE', $imgfile['name']);
			}
			if (!$uploaderr && ($imgwith > 150 || $imgheight > 150)) {
				$uploaderr = JText::_('COM_JVARCADE_UPLOAD_BIGGER_DIMS3');
			}
			if (!$uploaderr) {
				jimport('joomla.filesystem.file');
				$uploaded = JFile::upload($imgfile['tmp_name'], JVA_IMAGES_INCPATH . 'contests/' . $contestid . '_' . $imgfile['name']);
				if ($uploaded) {
					$this->dbo->setQuery('UPDATE #__jvarcade_contest SET ' . 
											$this->dbo->quoteName('imagename') . ' = ' . $this->dbo->Quote($contestid . '_' . $imgfile['name']) . 
										' WHERE ' . $this->dbo->quoteName('id') . ' = ' . (int)$contestid);
					$this->dbo->query();
				} else {
					$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR_MOVING', $imgfile['name']);
				}
			} 
			if ($uploaderr) $mainframe->enqueueMessage($uploaderr, 'notice');
		}
		
		if ($task == 'applycontest') {
			$url = 'index.php?option=com_jvarcade&task=editcontest&id=' . (int)$contestid;
		} else {
			$url = 'index.php?option=com_jvarcade&task=contests';
		}
		
		$mainframe->redirect($url, JText::_('COM_JVARCADE_CONTESTS_SAVE_SUCCESS'));
	}
	
	function addGameToContest($game_ids = array(), $contest_ids = array()) {
		if (is_array($game_ids) && count($game_ids) && is_array($contest_ids) && count($contest_ids)) {
			$query = 'INSERT INTO #__jvarcade_contestgame (' . $this->dbo->quoteName('gameid') . ', ' . $this->dbo->quoteName('contestid') . ') VALUES ';
			$q = array();
			foreach ($game_ids as $game_id) {
				foreach ($contest_ids as $contest_id) {
					$this->dbo->setQuery('SELECT gameid FROM #__jvarcade_contestgame WHERE ' . $this->dbo->quoteName('gameid') . ' = ' . (int)$game_id . ' AND ' . $this->dbo->quoteName('contestid') . ' = ' . (int)$contest_id);
					if (!(int)$this->dbo->loadResult()) {
						$q[] = '(' . (int)$game_id . ',' . (int)$contest_id . ')';
					}
				}
			}
			if (!count($q)) {
				return true;
			}
			$query .= implode(', ',$q);
			$this->dbo->setQuery($query);
			if($this->dbo->query()) {
				return true;
			}
		}
		return false;
	}
	
	function getContestGames($contest_id) {
		$this->dbo->setQuery('SELECT g.id, g.title, g.numplayed ' . 
							' FROM #__jvarcade_contestgame cg ' .
							'	LEFT JOIN #__jvarcade_games g ON cg.gameid = g.id ' .
							' WHERE cg.' . $this->dbo->quoteName('contestid') . ' = ' . (int)$contest_id . 
							' ORDER BY g.id DESC'
							);
		return $this->dbo->loadObjectList();

	}
	
	function getGameContests($game_id) {
		$this->dbo->setQuery('SELECT c.* ' . 
							' FROM #__jvarcade_contestgame cg ' .
							'	LEFT JOIN #__jvarcade_contest c ON cg.contestid = c.id ' .
							' WHERE cg.' . $this->dbo->quoteName('gameid') . ' = ' . (int)$game_id . 
							' ORDER BY c.startdatetime DESC'
							);
		return $this->dbo->loadObjectList();

	}
	
	function deleteGameFromContest($game_ids = array(), $contest_ids = array()) {
		$return = true;
		if (is_array($game_ids) && count($game_ids) && is_array($contest_ids) && count($contest_ids)) {
			foreach ($game_ids as $game_id) {
				foreach ($contest_ids as $contest_id) {
					$this->dbo->setQuery('DELETE FROM #__jvarcade_contestgame WHERE ' . $this->dbo->quoteName('gameid') . ' = ' . (int)$game_id . ' AND ' . $this->dbo->quoteName('contestid') . ' = ' . (int)$contest_id);
					if (!(int)$this->dbo->query()) {
						$return = false;
					}
				}
			}
		} else {
			$return = false;
		}
		return $return;
	}


	function getContentRatings($id = 0) {
	
		if ($this->_orderby) {
			$orderby = ' ORDER BY ' . $this->_orderby . ' ' . ($this->_orderdir ? $this->_orderdir : '');
		} else {
			$orderby = 'ORDER BY id DESC';
		}
		
		$where = array();
		$wherestr = '';
		
		if ((int)$id) $where[] = 'id = ' . (int)$id;
		$wherestr = (count($where) ? ' WHERE ( ' . implode( ' ) AND ( ', $where ) . ' )' : '' );
		
		$query = "SELECT SQL_CALC_FOUND_ROWS * " . 
					"FROM #__jvarcade_contentrating " . 
					$wherestr . ' ' . 
					$orderby;
		$this->dbo->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
		$return = $this->dbo->loadObjectList();
		return $return;
	}
	
	function contentratingPublish($published) {
		$mainframe = JFactory::getApplication('site');
		$id = JRequest::getVar('cid');
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		$query = "UPDATE #__jvarcade_contentrating SET " . $this->dbo->quoteName('published') . " = " . $this->dbo->Quote((int)$published) . "
			WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		$this->dbo->query();
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=content_ratings');

	}
	
	function saveContentRating() {
		$mainframe = JFactory::getApplication('site');
		$task = JRequest::getVar('task');
		$post = JRequest::get('post');
		$imgfile = JRequest::getVar('image', null, 'files', 'array');
		$uploaderr = '';
		
		// Process data
		
		if ((int)$post['id']) {
			$contentratingid = (int)$post['id'];
			$query = "UPDATE #__jvarcade_contentrating SET 
				" . $this->dbo->quoteName('name') . " = " . $this->dbo->Quote($post['name']) . ",
				" . $this->dbo->quoteName('description') . " = " . $this->dbo->Quote($post['description']) . ",
				" . $this->dbo->quoteName('warningrequired') . " = " . $this->dbo->Quote((int)$post['warningrequired']) . ",
				" . $this->dbo->quoteName('published') . " = " . $this->dbo->Quote((int)$post['published']) . "
			WHERE " . $this->dbo->quoteName('id') . " = " . $contentratingid;
			$this->dbo->setQuery($query);
			$this->dbo->query();
		} else {
			$query = "INSERT INTO #__jvarcade_contentrating " . 
					  "(" . $this->dbo->quoteName('name') . ", " . $this->dbo->quoteName('description') . ", " . 
							$this->dbo->quoteName('warningrequired') . ", " . $this->dbo->quoteName('published') . ") " . 
					"VALUES (" . $this->dbo->Quote($post['name']) . "," . $this->dbo->Quote($post['description']) . "," .
								$this->dbo->Quote((int)$post['warningrequired']) . "," . $this->dbo->Quote((int)$post['published']) . ")";
			$this->dbo->setQuery($query);
			$this->dbo->query();
			$contentratingid = (int)$this->dbo->insertid();
		}
		
		// Process image upload
		if ((int)$contentratingid && is_array($imgfile) && $imgfile['size'] > 0) {
		
			list($imgwith, $imgheight) = @getimagesize($imgfile['tmp_name']);

			if (!$uploaderr && $imgfile['error']) {
				$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR', $imgfile['name']);
			}
			if (!$uploaderr && (strpos($imgfile['type'], 'image') === false)) {
				$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_NOT_IMAGE', $imgfile['name']);
			}
			if (!$uploaderr && ($imgwith > 150 || $imgheight > 150)) {
				$uploaderr = JText::_('COM_JVARCADE_UPLOAD_BIGGER_DIMS3');
			}
			if (!$uploaderr) {
				jimport('joomla.filesystem.file');
				$uploaded = JFile::upload($imgfile['tmp_name'], JVA_IMAGES_INCPATH . 'contentrating/' . $contentratingid . '_' . $imgfile['name']);
				if ($uploaded) {
					$this->dbo->setQuery('UPDATE #__jvarcade_contentrating SET ' . 
											$this->dbo->quoteName('imagename') . ' = ' . $this->dbo->Quote($contentratingid . '_' . $imgfile['name']) . 
										' WHERE ' . $this->dbo->quoteName('id') . ' = ' . (int)$contentratingid);
					$this->dbo->query();
				} else {
					$uploaderr = JText::sprintf('COM_JVARCADE_UPLOAD_ERROR_MOVING', $imgfile['name']);
				}
			} 
			if ($uploaderr) $mainframe->enqueueMessage($uploaderr, 'notice');
		}
		
		if ($task == 'applycontentrating') {
			$url = 'index.php?option=com_jvarcade&task=editcontentrating&id=' . (int)$contentratingid;
		} else {
			$url = 'index.php?option=com_jvarcade&task=content_ratings';
		}
		
		$mainframe->redirect($url, JText::_('COM_JVARCADE_CONTENT_RATINGS_SAVE_SUCCESS'));
	}
	
	function deleteContentRating() {
		$mainframe = JFactory::getApplication('site');
		$id = JRequest::getVar('cid');
		if (!is_array($id)) $id = array($id);
		JArrayHelper::toInteger($id, array(0));
		
		$query = "DELETE FROM #__jvarcade_contentrating WHERE " . $this->dbo->quoteName('id') . " IN (" . implode(',', $id) . ")";
		$this->dbo->setQuery($query);
		$this->dbo->query();
		$mainframe->redirect('index.php?option=com_jvarcade&c&task=content_ratings');
	}
	
	function regenerateLeaderBoard($contest_id = 0) {
		//First clear out the old data
		$query = 'DELETE FROM #__jvarcade_leaderboard WHERE ' . $this->dbo->quoteName('contestid') . ' = ' . (int)$contest_id;
		$this->dbo->setQuery($query);
		if (!$this->dbo->query()){
			return false;
		}
		
		// Setup our point values
		$points = array(
			1 => 20,
			2 => 19,
			3 => 18,
			4 => 17,
			5 => 16,
			6 => 15,
			7 => 14,
			8 => 13,
			9 => 12,
			10 => 11,
			11 => 10,
			12 => 9,
			13 => 8,
			14 => 7,
			15 => 6,
			16 => 5,
			17 => 4,
			18 => 3,
			19 => 2,
			20 => 1,
		);

		$table  = (int)$contest_id ? ' #__jvarcade_contestscore ' : ' #__jvarcade ' ;
		$where  = (int)$contest_id ? ' WHERE contestid = ' . $contest_id . ' ' : '' ;

		$this->dbo->setQuery('SELECT * FROM ' . $table . $where . ' ORDER BY gameid DESC, score DESC, date ASC ');
		$scores = $this->dbo->loadObjectList();
		
		$all_scores = array();
		$user_score = array();
		$tmp = array();
		$pos = 0;
		
		// first calculate the placement
		$i = 0;
		for($i = 0; $i < count($scores); $i++) {
			if(!array_key_exists($scores[$i]->gameid, $tmp)) {
				$tmp[$scores[$i]->gameid] = $scores[$i]->gameid; 
				$pos = 1;
			} else {
				$pos++;
			}
			if ($pos <= 20) {
				// here we give + 1 point classement
				$all_scores[] = array('points' => $points[$pos]+1, 'uid' => $scores[$i]->userid);
			}
		}
		
		// calculate per user points
		$i = 0;
		for($i = 0; $i < count($all_scores); $i++) {
			if(!array_key_exists($all_scores[$i]['uid'], $user_score)) {
				$user_score[$all_scores[$i]['uid']] = $all_scores[$i]['points'];
			} else {
				$user_score[$all_scores[$i]['uid']] = $user_score[$all_scores[$i]['uid']] + $all_scores[$i]['points'];
			}
		}
		arsort($user_score);
		
		$qarr = array();
		foreach($user_score as $key => $value) {
			$qarr[] = '(' . $this->dbo->Quote((int)$contest_id) . ', ' . $this->dbo->Quote((int)$key) . ', ' . $this->dbo->Quote((int)$value) . ')';
		}

		$this->dbo->setQuery('INSERT INTO #__jvarcade_leaderboard(' . $this->dbo->quoteName('contestid') . ', ' . $this->dbo->quoteName('userid') . ', ' . $this->dbo->quoteName('points') . ') VALUES ' . implode(',', $qarr));
		if (!count($qarr) || $this->dbo->query()) {
			$global_conf = JFactory::getConfig();
			$path = $global_conf->get('tmp_path') . '/' . 'lb_' . $contest_id . '.txt';
			if (file_exists($path)) unlink($path);
			return true;
		} else {
			return false;
		}
	}
	
	function showDiagnostics() {
		$msg = array();
		$conf = $this->getConfObj();
		$safemode = (@ini_get('safe_mode') ? JText::_('COM_JVARCADE_MAINTENANCE_PHPSAFEMODE_YES') : JText::_('COM_JVARCADE_MAINTENANCE_PHPSAFEMODE_NO'));
		
		$tables = array(
			'#__jvarcade_contentrating',
			'#__jvarcade_contest',
			'#__jvarcade_contestgame',
			'#__jvarcade_contestmember',
			'#__jvarcade_contestscore',
			'#__jvarcade_faves',
			'#__jvarcade_folders',
			'#__jvarcade_gamedata',
			'#__jvarcade_games',
			'#__jvarcade_lastvisited',
			'#__jvarcade_leaderboard',
			'#__jvarcade_ratings',
			'#__jvarcade_settings',
			'#__jvarcade_tags'
		);

		$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_JVAVERSION', JVA_VERSION);
		$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_JOOMLAVERSION', JVA_JOOMLA_VERSION);
		$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_PHPVERSION', phpversion());
		$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_PHPINTERFACE', php_sapi_name());
		$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_PHPSAFEMODE',  $safemode);
		$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_DBVERSION', $this->dbo->getVersion());
		
		$msg[] = '<br><strong>' . JText::_('COM_JVARCADE_MAINTENANCE_SCOREFILES') . '</strong><br/>';
		
		$filelist = array ('newscore.php', 'arcade.php');
		foreach($filelist as $file) {
			$filename = JPATH_SITE . '/' . $file ;
			if (file_exists($filename)) {
				$permresult = substr(sprintf('%o', fileperms($filename)), -4);
				$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_FILEHASPERMS', $filename, $permresult);
			} else {
				$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_FILENOTEXISTS', $filename);
			}
		}
		
		$msg[] = '<br><strong>' . JText::_('COM_JVARCADE_MAINTENANCE_ANALYZE') . '</strong><br/>';
		
		foreach ($tables as $table) {
			$this->dbo->setQuery('ANALYZE TABLE ' . $table);
			$result = $this->dbo->loadObject();
			$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_ANALYZETABLE', $table, $result->Msg_text);
		}
		
		$msg[] = '<br><strong>' . JText::_('COM_JVARCADE_MAINTENANCE_OPTIMIZE') . '</strong><br/>';
		
		foreach ($tables as $table) {
			$this->dbo->setQuery('OPTIMIZE TABLE ' . $table);
			$result = $this->dbo->loadObject();
			$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_OPTIMIZETABLE', $table, $result->Msg_text);
		}
		
		$msg[] = '<br><strong>' . JText::_('COM_JVARCADE_MAINTENANCE_PLUGINS') . '</strong><br/>';

		if (JPluginHelper::isEnabled('system', 'jvarcade')) {
			$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_PLUGIN', 'jVArcade System Plugin', JText::_('COM_JVARCADE_MAINTENANCE_PLUGIN_ENABLED'));
		}
		$plugins = JPluginHelper::getPlugin('puarcade');
		foreach($plugins as $plugin) {
			$enabled = JPluginHelper::isEnabled('puarcade', $plugin->name) ? JText::_('COM_JVARCADE_MAINTENANCE_PLUGIN_ENABLED') : JText::_('COM_JVARCADE_MAINTENANCE_PLUGIN_DISABLED');
			$msg[] = JText::sprintf('COM_JVARCADE_MAINTENANCE_PLUGIN', $plugin->name, $enabled);
		}

		if (count($msg)) {
			return implode('<br/>', $msg); 
		}
		return '';
	}
	
	function doMaintenance($service, $context, $gameid, $contestid) {
		$result = -1;
		$message = '';
		$sql = '';
		$where = '';
		$and = '';
		$langstr = '';
		$table = '';
		
		if ($context == 'game') {
			$where = ' WHERE ' . $this->dbo->quoteName('gameid') . ' = ' . (int)$gameid;
			$and = ' AND ' . $this->dbo->quoteName('gameid') . ' = ' . (int)$gameid;
			$langstr = 'GAME_';
		} elseif ($context == 'contest') {
			$where = ' WHERE ' . $this->dbo->quoteName('contestid') . ' = ' . (int)$contestid;
			$and = ' AND ' . $this->dbo->quoteName('contestid') . ' = ' . (int)$contestid;
			$langstr = 'CONTEST_';
			$table = '_contestscore';
		}
		switch ($service) {
			case 'deleteallscores':
				$sql = 'DELETE FROM #__jvarcade' . $table . ' ' . $where;
				break;
			case 'deleteguestscores':
				$sql = 'DELETE FROM #__jvarcade' . $table . ' WHERE ' . $this->dbo->quoteName('userid') . ' = 0 ' . $and;
				break;
			case 'deletezeroscores':
				$sql = 'DELETE FROM #__jvarcade' . $table . ' WHERE ' . $this->dbo->quoteName('score') . ' = 0 ' . $and;
				break;
			case 'deleteblankscores':
				$sql = 'DELETE FROM #__jvarcade' . $table . ' WHERE (' . $this->dbo->quoteName('score') . ' = \'\' OR ' . $this->dbo->quoteName('score') . ' IS NULL) ' . $and;
				break;
			case 'clearallratings':
				$sql = 'DELETE FROM #__jvarcade_ratings ' . $where;
				break;
			case 'deletealltags':
				$sql = 'DELETE FROM #__jvarcade_tags ' . $where;
				break;
		}
		if ($sql) {
			$this->dbo->debug(0);
			$this->dbo->setQuery($sql);
			if ($this->dbo->query()) {
				$result = 1;
			}
		}
		if ($service == 'recalculateleaderboard' && $this->regenerateLeaderBoard((int)$contestid)) {
			$result = 1;
		}
		
		if ($result == 1) {
			//~ $message = '<span style="color: green;">' . JText::_('COM_JVARCADE_MAINTENANCE_' . $langstr . strtoupper($service) . '_SUCCESS') . '</span>';
			$message = '<span style="color: green;">' . JText::_('COM_JVARCADE_MAINTENANCE_' . $langstr . strtoupper($service) . '_SUCCESS') . '<br/>' . $sql . '</span>';
		} elseif ($result == -1) {
			$message = JText::_('COM_JVARCADE_MAINTENANCE_' . $langstr . strtoupper($service) . '_FAILED');
			if ($this->dbo->getErrorMsg()) {
				$message .= '<br>' . $this->dbo->getErrorMsg();
			}
			$message = '<span style="color: red;">' . $message . '</span>';
		}
		
		if ($service == 'supportdiagnostics') {
			$result = 1;
			$message = $this->showDiagnostics();
		}
		
		return array('status' => $result, 'msg' => $message);
	}

	// method to get the changelog file
	function getChangeLog() {
		//jimport('joomla.utilities.simplexml');
		

		// Load changelog
		$xmlfile = $this->loadChangelogFile();
		$xml = simplexml_load_file($xmlfile);
		
		$output = '<dl class="changelog">';
		foreach ($xml->version as $version) {
			$attr = $version->attributes();
			
			$output .= '<dt>';
			$output .= '<h4>' . JText::_('COM_JVARCADE_CHANGELOG_VERSION') . ': ' . $attr['number'] . '</h4>';
			$output .= '<b>' . JText::_('COM_JVARCADE_CHANGELOG_DATE') . ':</b> ' . $version->date[0] . '<br/>';
			$output .= '<b>' . JText::_('COM_JVARCADE_CHANGELOG_DESCRIPTION') . ':</b> ' . $version->description[0];
			$output .= '</dt>';
			$output .= '<dd><ul>';
			/*if(isset($version->list) && is_array($version->list) && is_array($version->list[0]->item)) {*/
				foreach ($version->list[0]->item as $item) {
					$itemAttr = $item->attributes();
					$output .= '<li><span class="' . $itemAttr['type'] . '">' . $itemAttr['type'] . '</span> ' . $item . '</li>';
				}
			//}
			$output .= '</ul></dd>';
		}
		$output .= '</dl>';

		return $output;
	}
	
	function loadChangelogFile() {
		$config = JFactory::getConfig();
		$tmp_path = $config->get('tmp_path');
		$filename = 'jvarcade-changelog.xml';
		$tmpfile = $tmp_path . '/' . $filename;
		$default_file = JPATH_ROOT . '/' . 'administrator' . '/' . 'components' . '/' . 'com_jvarcade' . '/' . 'changelog.xml';
		
		$dorequest = false;
		$filefound = false;
		
		if (is_file($tmpfile)) {
			$filefound = true;
			if ((filemtime($tmpfile) + (60 * 60 * 24)) < time()) {
				// only once per day
				$dorequest = true;
			}
		}
		
		if (!$filefound) $dorequest = true;
		
		if ($dorequest) {
			
			$http = JHttpFactory::getHttp();
			$response = $http->get('http://www.jvitals.com/index.php?option=com_jvitalsversions&task=changelog&format=raw&com=jvarcade', array(), 90);
			$response = $response->body;
			
			if ($response->code != 200) {
				return false;
					
			}
			
			$fp = @fopen($tmpfile, "wb");
			if ($fp) {
				@flock($fp, LOCK_EX);
				$len = strlen($response);
				@fwrite($fp, $response, $len);
				@flock($fp, LOCK_UN);
				@fclose($fp);
				$written = true;
			}
			// Data integrity check
			if ($written && (file_get_contents($tmpfile))) {
				// nothing to do
			} else {
				unlink($tmpfile);
			}
		}
		
		return (is_file($tmpfile) ? $tmpfile : $default_file);
	}


}

?>
