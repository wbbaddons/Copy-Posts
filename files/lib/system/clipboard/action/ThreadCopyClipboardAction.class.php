<?php
namespace wbb\system\clipboard\action;

use wbb\data\board\BoardCache;
use wcf\data\clipboard\action\ClipboardAction;
use wcf\system\clipboard\ClipboardHandler;

/**
 * Prepares clipboard editor items for threads (including copy)
 * 
 * @author	Joshua Rüsweg
 * @package	de.joshsboard.copyposts
 * @subpackage	system.clipboard.action
 * @category	Burning Board
 */
class ThreadCopyClipboardAction extends ThreadClipboardAction {

	/**
	 * @see	\wcf\system\clipboard\action\AbstractClipboardAction::$actionClassActions
	 */
	protected $actionClassActions = array('copy');
	
	/**
	 * @see	wcf\system\clipboard\action\AbstractClipboardAction::$supportedActions
	 */
	protected $supportedActions = array('copy');
	
	/**
	 * @see	wcf\system\clipboard\action\IClipboardAction::execute()
	 */
	public function execute(array $objects, ClipboardAction $action) {
		if (empty($this->threads)) {
			$this->threads = $this->loadBoards($objects);
		}
		
		$item = parent::execute($objects, $action);

		if ($item === null) {
			return null;
		}
		
		// handle actions
		switch ($action->actionName) {
			case 'copy':
				$item->addInternalData('parameters', array(
					'boardID' => ClipboardHandler::getInstance()->getPageObjectID()
				));
			break;
		}
		
		return $item;
	}
	
	/**
	 * @see	wcf\system\clipboard\action\IClipboardAction::getClassName()
	 */
	public function getClassName() {
		return 'wbb\data\thread\ThreadCopyAction';
	}
	
	/**
	 * @see	wcf\system\clipboard\action\IClipboardAction::getTypeName()
	 */
	public function getTypeName() {
		return 'com.woltlab.wbb.thread';
	}
	
	/**
	 * Validates copy posts
	 * 
	 * @return	array<integer>
	 */
	public function validateCopy() {
		$threadIDs = $this->validateMove();
		
		if (!empty($threadIDs)) {
			// validate permissions for target board
			$board = BoardCache::getInstance()->getBoard(ClipboardHandler::getInstance()->getPageObjectID());
			if ($board === null || !$board->canStartThread()) {
				$threadIDs = array();
			}
		}
		
		return $threadIDs;
	}
}
