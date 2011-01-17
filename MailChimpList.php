<?php

namespace kwd\helpers\mailchimp;

/**
 * Representation of a MailChimp list
 */
class MailChimpList {
	private $mc;
	private $listData = array();
	public $errors   = array();

	/**
	 * Construct a new MailChimpList
	 *
	 * @param MailChimp $mc
	 * @param array $data The internal listData array is set to this (be careful!). Should only be used by MailChimp-class
	 */
	public function __construct(MailChimp $mc, $data = array()) {
		$this->mc = $mc;
		$this->listData = $data;
	}

	public function init($id) {
		$found = false;
		$lists = $this->mc->lists(false);

		foreach ($lists as $list) {
			if ($list['id'] == $id) {
				$found = true;
				$this->listData = $list;
			}
		}

		if ($found == false) {
			$this->errors[] = 'List not found (id: ' . $id . ').';
		}

		return $this;
	}


	/**
	 * Get the id or web_id of the list
	 *
	 * @param bool $webId
	 * @return int/string
	 */
	public function getId($webId = false) {
		return ($webId) ? $this->listData['web_id'] : $this->listData['id'];
	}

	/**
	 * Get the name of the list
	 *
	 * @return string
	 */
	public function getName() {
		return $this->listData['name'];
	}

	/**
	 * Get the creation date of the list as a unix timestamp
	 *
	 * @return int
	 */
	public function getDateCreated() {
		return strtotime($this->listData['date_created']);
	}

	/**
	 * Get the number of active members in this list
	 *
	 * @param bool $sinceSend If true: The number of active members in this list since the last campaign was sent
	 * @return int
	 */
	public function getMemberCount($sinceSend = false) {
		return ($sinceSend) ? $this->listData['member_count_since_send'] : $this->listData['member_count'];
	}

	/**
	 * Get the number of members who have unsubscribed from this list
	 *
	 * @return int
	 */
	public function getUnsubscribeCount() {
		return $this->listData['unsubscribe_count'];
	}

	/**
	 * Get the number of members cleaned from this list
	 *
	 * @param bool $sinceSend If true: The number of members cleaned from this list since the last campaign was sent
	 * @return int
	 */
	public function getCleanedCount($sinceSend = false) {
		return ($sinceSend) ? $this->listData['cleaned_count_since_send'] : $this->listData['cleaned_count'];
	}

	/**
	 * Get the email type option: Whether or not the List supports multiple formats for emails or just HTML
	 *
	 * @return boolean
	 */
	public function getEmailTypeOption() {
		return $this->listData['email_type_option'];
	}

	/**
	 * Get the default From Name for campaigns using this list
	 *
	 * @return string
	 */
	public function getDefaultFromName() {
		return $this->listData['default_from_name'];
	}

	/**
	 * Get the default From Email for campaigns using this list
	 *
	 * @return string
	 */
	public function getDefaultFromEmail() {
		return $this->listData['default_from_email'];
	}

	/**
	 * Get the default Subject Line for campaigns using this list
	 *
	 * @return string
	 */
	public function getDefaultSubject() {
		return $this->listData['default_subject'];
	}

	/**
	 * Get the default Language for this list's forms
	 *
	 * @return string
	 */
	public function getDefaultLanguage() {
		return $this->listData['default_language'];
	}

	/**
	 * Get the auto-generated activity score for the list (0 - 5)
	 *
	 * @return double
	 */
	public function getListRating() {
		return $this->listData['list_rating'];
	}

	/**
	 * Get all of the list members for this list that are of a particular status.
	 * Each member in the array will be of the type MailChimpUser.
	 *
	 * @param boolean $getAsObject if true, each member in the result array will be expanded with all available info and returned as a MailChimpUser object
	 * @param string $status the status to get members for - one of(subscribed, unsubscribed, cleaned, updated), defaults to subscribed
	 * @param int $since optional pull all members whose status has changed since this date/time (unix timestamp)
	 * @param int $start optional for large data sets, the page number to start at - defaults to 1st page of data (page 0)
	 * @param int $limit optional for large data sets, the number of results to return - defaults to 25, upper limit set at 15000
	 * @return array
	 */
	public function getMembers($getAsObjects=false, $status=MailChimp::MEMBER_STATUS_SUBSCRIBED, $since=0, $start=0, $limit=25) {
		$since = ((int)$since > 0) ? date('Y-m-d H:m:s', $since) : null ;

		$result = $this->mc->listMembers($this->listData['id'], $status, $since, (int)$start, (int)$limit);

		if($getAsObjects && is_array($result)) {
			$members = array();

			foreach($result as $item) {
				$member = new MailChimpUser($this->mc);
				$member->init($item['email'], $this->listData['id']);
				$members[] = $member;
				unset($member);
			}

			unset($result);
			return $members;
		}

		return $result;
	}

	/**
	 * Get the list of merge tags for this list, including their name, tag, and required setting
	 *
	 * @section List Related
	 * @example xml-rpc_listMergeVars.php
	 *
	 * @param string $id the list id to connect to. Get by calling lists()
	 * @return array list of merge tags for the list
	 * @returnf string name Name of the merge field
	 * @returnf bool req Denotes whether the field is required (true) or not (false)
	 * @returnf string field_type The "data type" of this merge var. One of: email, text, number, radio, dropdown, date, address, phone, url, imageurl
	 * @returnf bool public Whether or not this field is visible to list subscribers
	 * @returnf bool show Whether the list owner has this field displayed on their list dashboard
	 * @returnf string order The order the list owner has set this field to display in
	 * @returnf string default The default value the list owner has set for this field
	 * @returnf string size The width of the field to be used
	 * @returnf string tag The merge tag that's used for forms and listSubscribe() and listUpdateMember()
	 * @returnf array choices For radio and dropdown field types, an array of the options available
	 */
	public function getMergeVars() {
		return $this->mc->listMergeVars($this->listData['id']);
	}
}
