<?php
namespace wbb\data\post;

use wbb\data\thread\Thread;
use wbb\data\post\PostAction;
use wcf\data\attachment\GroupedAttachmentList;
use wcf\system\request\LinkHandler;
use wcf\data\attachment\AttachmentAction;
use wbb\data\thread\ThreadEditor;
use wcf\system\exception\UserInputException; 
use wcf\system\user\activity\point\UserActivityPointHandler; 
use wbb\data\board\Board; 
use wbb\data\board\BoardCache; 
use wbb\data\board\BoardEditor; 
use wbb\data\post\PostList; 

/**
 * Executes post-related actions (including copy).
 * 
 * @author	Joshua Rüsweg
 * @package	de.joshsboard.copy
 * @subpackage	data.post
 * @category	Burning Board
 */
class PostCopyAction extends PostAction {

	/**
	 * validate copy posts to a new thread
	 */
	public function validateCopyToNewThread() {
		if (empty($this->objects)) {
			$this->readObjects();
			
			if (empty($this->objects)) {
				throw new UserInputException('objectIDs');
			}
		}
		
		$this->readString('subject');
		$this->readInteger('boardID');
		
		$this->parameters['board'] = new Board($this->parameters['boardID']);
		
		if ($this->parameters['board']->getObjectID() == 0) {
			throw new UserInputException('boardID');
		}
	}
	
	/**
	 * copy posts to a new thread
	 * @return array<mixed>
	 */
	public function copyToNewThread() {
		$oldest = self::getOldestPost($this->objects);

		// cannot use create action, because we want to copy the post with the action
		$data = array(
		    'boardID' => $this->parameters['boardID'],
		    'topic' => $this->parameters['subject'],
		    'hasLabels' => 0,
		    'username' => $oldest->getUsername(),
		    'userID' => $oldest->getUserID(),
		    'time' => $oldest->getTime(),
		    'lastPosterID' => $oldest->getUserID(), 
		    'lastPostTime' => $oldest->getTime(), 
		    'lastPoster' => $oldest->getUsername()
		);

		$thread = ThreadEditor::create($data); 
		
		$editor = new BoardEditor(BoardCache::getInstance()->getBoard($this->parameters['boardID']));
		$editor->updateCounters(array(
		    'threads' =>  1
		));
		
		UserActivityPointHandler::getInstance()->fireEvent('com.woltlab.wbb.activityPointEvent.thread', $thread->threadID, $thread->userID);
		
		$action = new self($this->objects, 'copyToExistingThread', array('thread' => $thread));
		$action->executeAction(); 
		
		return array(
		    'threadID' => $thread->threadID,
		    'thread' => $thread, 
		    'redirectURL' => LinkHandler::getInstance()->getLink('Thread', array(
			'application' => 'wbb',
			'object' => $thread
		    ))
		);
	}

	/**
	 * validates copy posts 
	 */
	public function validateCopyToExistingThread() {
		$this->prepareObjects();

		if ((!isset($this->parameters['thread']) || !($this->parameters['thread'] instanceof Thread)) && !isset($this->parameters['threadID']) ) {
			throw new UserInputException('thread');
		}
	}

	/**
	 * copy posts
	 * @return array<mixed>
	 */
	public function copyToExistingThread() {

		$thread = (isset($this->parameters['thread']) ? $this->parameters['thread'] : new Thread($this->parameters['threadID']));

		foreach ($this->objects AS $post) {
			// reset parameters
			$parameters = array();

			$parameters['thread'] = $thread;
			$parameters['subscribeThread'] = false;

			if ($post->isDisabled)
				$parameters['data']['isDisabled'] = 1;
			if ($post->isDeleted)
				$parameters['data']['isDeleted'] = 1;

			if ($thread->isDisabled)
				$parameters['data']['isDisabled'] = 1;
			if ($thread->isDeleted)
				$parameters['data']['isDeleted'] = 1;

			$parameters['data']['threadID'] = $thread->threadID;
			$parameters['data']['subject'] = $post->subject;
			$parameters['data']['isClosed'] = $post->isClosed;
			$parameters['data']['message'] = $post->message;
			$parameters['data']['time'] = $post->time;
			$parameters['data']['userID'] = $post->userID;
			$parameters['data']['username'] = $post->username;
			$parameters['data']['enableBBCodes'] = $post->enableBBCodes;
			$parameters['data']['enableHtml'] = $post->enableHtml;
			$parameters['data']['enableSmilies'] = $post->enableSmilies;
			$parameters['data']['showSignature'] = $post->showSignature;
			$parameters['data']['attachments'] = $post->attachments;
			$parameters['data']['ipAddress'] = $post->ipAddress;
			
			$action = new PostAction(array(), 'create', $parameters);
			$basicPost = $post;
			$post = $action->executeAction();
			$post = $post['returnValues'];
			// now copy attachments
			if (MODULE_ATTACHMENT && $basicPost->attachments) {
				$attachmentList = new GroupedAttachmentList('com.woltlab.wbb.post');
				$attachmentList->getConditionBuilder()->add('attachment.objectID IN (?)', array($basicPost->postID));
				$attachmentList->readObjects();

				$newAttachments = array();

				$replacement = array();

				foreach ($attachmentList->getGroupedObjects($basicPost->postID) as $attach) {
					$action = new AttachmentAction(array(), 'create', array(
					    'data' => array(
						'objectTypeID' => $attach->objectTypeID,
						'objectID' => $post->postID,
						'userID' => $attach->userID,
						'filename' => $attach->filename,
						'filesize' => $attach->filesize,
						'fileType' => $attach->fileType,
						'fileHash' => $attach->fileHash,
						'isImage' => $attach->isImage,
						'width' => $attach->width,
						'height' => $attach->height,
						'uploadTime' => $attach->uploadTime,
						'showOrder' => $attach->showOrder
					    )
					));
					$nA = $action->executeAction();

					copy($attach->getLocation(), $nA['returnValues']->getLocation());

					$newAttachments[] = $nA['returnValues'];

					$replacement[$attach->attachmentID] = $nA['returnValues']->attachmentID;
				}

				// because its easier to create new thumbnails (long live the idleness)
				$thumbnails = new AttachmentAction($newAttachments, 'generateThumbnails', array());
				$thumbnails->executeAction();

				$oldMessage = $post->message;

				foreach ($replacement as $old => $new) {
					$post->message = str_replace('[attach=' . $old . '][/attach]', '[attach=' . $new . '][/attach]', $post->message);
				}

				if (md5($oldMessage) != md5($post->message)) {
					// update 
					$action = new PostAction(array($post), 'update', array('data' => array(
						'message' => $post->message
					)));
					$action->executeAction();
				}
			}
		}
		
		$threadEditor = new ThreadEditor($thread);
		
		// we should correct the first poster
		$posts = new PostList(); 
		$posts->getConditionBuilder()->add('post.threadID = ?', array($thread->threadID));
		$posts->sqlLimit = 1; // we only need the first post
		$posts->sqlOrderBy = 'post.time ASC, post.postID ASC';
		$posts->readObjects(); 
		$objects = $posts->getObjects();
		
		foreach ($objects as $firstPost) {
			if ($thread->firstPostID != $firstPost->postID) {
				$threadEditor->update(array(
				    'firstPostID' => $firstPost->postID, 
				    'time' => $firstPost->time, 
				    'userID' => $firstPost->userID,
				    'username' => $firstPost->username,
				    'cumulativeLikes' => $firstPost->cumulativeLikes
				));
			}
		}
		
		// rebuild
		ThreadEditor::rebuildThreadData(array($thread->threadID));
		
		// unmark clipboard items
		$this->unmarkItems($this->objectIDs);

		return array(
		    'threadID' => $thread->threadID,
		    'redirectURL' => LinkHandler::getInstance()->getLink('Thread', array(
			'application' => 'wbb',
			'object' => $thread
		    ))
		);
	}

	/**
	 * fetch the oldest post
	 * @param array<\wbb\data\post\Post> $objects a list of object
	 * @return \wbb\data\post\Post 
	 */
	public static function getOldestPost(array $objects) {
		$oldestPost = null;
		
		foreach ($objects as $object) {
			if ($object instanceof \wbb\data\post\PostEditor) {
				if ($oldestPost === null) {
					$oldestPost = $object;
					continue;
				}

				if ($object->getTime() < $oldestPost->getTime()) {
					$oldestPost = $object;
				}
			}
		}
		
		return $oldestPost;
	}

}
