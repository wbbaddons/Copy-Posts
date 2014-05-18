<?php
namespace wbb\data\thread; 

use wbb\data\post\PostCopyAction; 
use wbb\data\post\PostList; 
use wbb\data\board\Board;
use wcf\system\exception\UserInputException; 
use wcf\system\request\LinkHandler; 

/**
 * Executes thread-related actions (including copy).
 * 
 * @author	Joshua Rüsweg
 * @package	de.joshsboard.copy
 * @subpackage	data.thread
 * @category	Burning Board
 */
class ThreadCopyAction extends ThreadAction {
	
	/**
	 * validates the copy action
	 */
	public function validateCopy() {
		$this->readObjects(); 
		
		$this->readInteger('boardID');
		
		$this->parameters['board'] = new Board($this->parameters['boardID']);
		
		if ($this->parameters['board']->getObjectID() == 0) {
			throw new UserInputException('boardID');
		}
	}
	
	/**
	 * copy a thread (without labels!)
	 * @return array<mixed>
	 */
	public function copy() {
		foreach ($this->objects as $object) {
			$posts = new PostList(); 
			$posts->getConditionBuilder()->add('post.threadID = ?', array($object->threadID));
			$posts->readObjects(); 
			
			$action = new PostCopyAction($posts->getObjects(), 'copyToNewThread', array('subject' => $object->getTitle(), 'boardID' => $this->parameters['boardID']));
			$action->validateAction(); 
			$action->executeAction();
		}
		
		// unmark clipboard items
		$this->unmarkItems($this->objectIDs);
		
		return array(
		    'boardID' => $this->parameters['board']->getObjectID(),
		    'thread' => $this->parameters['board'], 
		    'redirectURL' => LinkHandler::getInstance()->getLink('Board', array(
			'application' => 'wbb',
			'object' => $this->parameters['board']
		    ))
		);
	}
}