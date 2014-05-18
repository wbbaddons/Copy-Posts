<?php
namespace wbb\system\clipboard\action;

use wbb\data\board\BoardCache;
use wbb\data\thread\Thread;
use wcf\data\clipboard\action\ClipboardAction;
use wcf\system\clipboard\ClipboardHandler;
use wcf\system\WCF;

/**
 * Prepares clipboard editor items for posts (including copy)
 * 
 * @author	Joshua Rüsweg
 * @package	de.joshsboard.copyposts
 * @subpackage	system.clipboard.action
 * @category	Burning Board
 */
class PostCopyClipboardAction extends PostClipboardAction {

	/**
	 * @see	\wcf\system\clipboard\action\AbstractClipboardAction::$actionClassActions
	 */
	protected $actionClassActions = array('copyToExistingThread');
	
	/**
	 * @see	wcf\system\clipboard\action\AbstractClipboardAction::$supportedActions
	 */
	protected $supportedActions = array('copyToExistingThread', 'copyToNewThread');
	
	/**
	 * @see	wcf\system\clipboard\action\IClipboardAction::execute()
	 */
	public function execute(array $objects, ClipboardAction $action) {
		if (empty($this->posts)) {
			$this->posts = $this->loadThreads($objects);
		}
		
		$item = parent::execute($objects, $action);
		
		if ($item === null) {
			return null;
		}

		// handle actions
		switch ($action->actionName) {
			case 'copyToExistingThread':
				$item->addInternalData('parameters', array(
					'threadID' => ClipboardHandler::getInstance()->getPageObjectID()
				));
			break;
		
			case 'copyToNewThread':
				$item->addParameter('template', WCF::getTPL()->fetch('postCopyToNewThread', 'wbb'));
			break;
		}
		
		return $item;
	}
	
	/**
	 * @see	wcf\system\clipboard\action\IClipboardAction::getClassName()
	 */
	public function getClassName() {
		return 'wbb\data\post\PostCopyAction';
	}
	
	/**
	 * @see	wcf\system\clipboard\action\IClipboardAction::getTypeName()
	 */
	public function getTypeName() {
		return 'com.woltlab.wbb.post';
	}
	
	/**
	 * Validates posts to copy them into a new thread.
	 * 
	 * @return	array<integer>
	 */
	public function validateCopyToExistingThread() {
		$postIDs = $this->__validateMove();
		
		if (!empty($postIDs)) {
			// validate permissions for target thread
			$thread = new Thread(ClipboardHandler::getInstance()->getPageObjectID());
			if (!$thread->threadID || !$thread->getBoard()->getModeratorPermission('canMergePost')) {
				$postIDs = array();
			}
		}
		
		return $postIDs;
	}
	
	/**
	 * Validates posts to copy them into a new thread.
	 * 
	 * @return	array<integer>
	 */
	public function validateCopyToNewThread() {
		$postIDs = $this->__validateMove();
		
		if (!empty($postIDs)) {
			// validate permissions for target board
			$board = BoardCache::getInstance()->getBoard(ClipboardHandler::getInstance()->getPageObjectID());
			if ($board === null || !$board->canStartThread()) {
				$postIDs = array();
			}
		}
		
		return $postIDs;
	}
}
