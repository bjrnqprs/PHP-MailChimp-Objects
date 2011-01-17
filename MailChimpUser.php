<?php

namespace kwd\helpers\mailchimp;

/**
 * Representation of a MailChimp user
 */
class MailChimpUser {
	private $mc;
	private $new = false; // Wether this object represents a new or an existing user
	private $listId;
	private $userData = array();

	public $errors = array();

	/**
	 * Construct a new MailChimpUser
	 *
	 * @param MailChimp $mc
	 * @param array $data The internal userData array is set to this (be careful!). Should only be used by MailChimp-class
	 */
	public function __construct(MailChimp $mc, $data = array()) {
		$this->mc = $mc;
		$this->userData = $data;
	}

	/**
	 * Initialize this class with a MailChimp user record,
	 * or create a new one when the email address does not exist in the mailchimp list yet.
	 *
	 * @param string $email
	 * @param string $listId
	 * @param boolean $new Wether to create a new user based on this info, or try to retrieve an existsing user
	 * @return MailChimpUser
	 */
	public function init($email, $listId) {
		unset($this->userData);

		$user = $this->mc->listMemberInfo($listId, $email);

		if (is_array($user)) {
			$this->new = false;
			$this->listId = $listId;
			$this->userData = $user;

			if(isset($this->userData['merges']) == false) {
				$this->userData['merges'] = array();
			} else {
				// Remove any alias-tags, aka MERGE0, MERGE1, etc
				foreach($this->userData['merges'] as $key => $value) {
					if(strtoupper(substr($key, 0, 5)) == 'MERGE') {
						unset($this->userData['merges'][$key]);
					}
				}
			}
		} else {
			$this->new = true;
			$this->listId = $listId;
			$this->userData = array('merges' => array());

			$this->setEmail($email);
			$this->setEmailType(MailChimp::EMAIL_TYPE_HTML);
		}

		return $this;
	}

	/**
	 * Initialize this class with a MailChimp user record, or create a new one
	 *
	 * @param string $email
	 * @param string $listId
	 * @param boolean $new Wether to create a new user based on this info, or try to retrieve an existsing user
	 * @return MailChimpUser
	 */
	public function init_old($email, $listId, $new = false) {
		unset($this->userData);

		if ($new) {
			$this->new = true;
			$this->listId = $listId;
			$this->userData = array('merges' => array());

			$this->setEmail($email);
			$this->setEmailType(MailChimp::EMAIL_TYPE_HTML);
		} else {
			$user = $this->mc->listMemberInfo($listId, $email);

			if (is_array($user)) {
				$this->listId = $listId;
				$this->userData = $user;

				if(isset($this->userData['merges']) == false) {
					$this->userData['merges'] = array();
				} else {
					// Remove any alias-tags, aka MERGE0, MERGE1, etc
					foreach($this->userData['merges'] as $key => $value) {
						if(strtoupper(substr($key, 0, 5)) == 'MERGE') {
							unset($this->userData['merges'][$key]);
						}
					}
				}
			} else {
				$this->errors[] = $this->mc->errorCode . ': ' . $this->mc->errorMessage;
			}
		}

		return $this;
	}

	/**
	 * Returns true when the current record will be new in MailChimp, or false when it
	 * already exists and only be updated in MailChimp.
	 *
	 * @return boolean
	 */
	public function isNew() {
		return $this->new;
	}

	/**
	 * Reload the data from MailChimp into this object
	 *
	 * @return MailChimpUser
	 */
	public function reload() {
		return $this->init($this->userData['email'], $this->listId);
	}

	/**
	 * Update data or create a new record in MailChimp
	 *
	 * @param boolean $doubleOptin Only used when adding a new record. Whether a double opt-in confirmation message is sent.
	 * @param boolean $sendWelcome Only used when adding a new record. When doubleOptin is false and this is true, we will send the lists Welcome Email if this subscribe succeeds.
	 * @return boolean
	 */
	public function save($doubleOptin = true, $sendWelcome = true) {
		if ($this->new) {
			if (empty($this->listId) == false && isset($this->userData['email']) && isset($this->userData['merges']) && isset($this->userData['email_type'])) {
				$result = $this->mc->listSubscribe(
					$this->listId, $this->userData['email'], $this->userData['merges'], $this->userData['email_type'],
					$doubleOptin, false, false, $sendWelcome
				);
			} else {
				$this->errors[] = 'ListID, email, email_type and/or additional merges where not set.';
			}
		} else {
			$result = $this->mc->listUpdateMember(
				$this->listId, $this->userData['email'], $this->userData['merges'], $this->userData['email_type']
			);
		}

		if ($result !== true) {
			$this->errors[] = $this->mc->errorCode . ': ' . $this->mc->errorMessage;
		}

		return $result;
	}

	/**
	 * Unsubscribe this MailChimp user from the list.
	 * The user will be marked 'unsubscribed' but will stay in the system.
	 *
	 * @param boolean $sendGoodByeMsg
	 * @param boolean $sendNotificationToAdmin
	 * @return boolean
	 */
	public function unsubscribe($sendGoodByeMsg = true, $sendNotificationToAdmin = true) {
		$result = $this->mc->listUnsubscribe(
			$this->listId, $this->userData['email'], false, $sendGoodByeMsg, $sendNotificationToAdmin
		);

		if ($result !== true) {
			$this->errors[] = $this->mc->errorCode . ': ' . $this->mc->errorMessage;
		}

		return $result;
	}

	/**
	 * Unsubscribe AND delete a MailChimp user from the system.
	 * NOTE: No e-mails can/will be send to the user or admin for notification.
	 *
	 * @return boolean
	 */
	public function delete() {
		$result = $this->mc->listUnsubscribe($this->listId, $this->userData['email'], true, false, false);

		if ($result !== true) {
			$this->errors[] = $this->mc->errorCode . ': ' . $this->mc->errorMessage;
		}

		return $result;
	}


	/**
	 * Get the unique (alpha numeric) Member id.
	 * If $webId=true: Get the numeric ID used in MailChimp, allows you to create a link directly to it.
	 *
	 * If not set / new record 0 is returned.
	 *
	 * @return string/integer
	 */
	public function getId($webId=false) {
		if($webId) {
			return (isset($this->userData['web_id'])) ? $this->userData['web_id'] : 0 ;
		}

		return (isset($this->userData['id'])) ? $this->userData['id'] : 0 ;
	}

	/**
	 * Get the list id the user is in
	 *
	 * @return string
	 */
	public function getListId() {
		return $this->listId;
	}

	// VIA $THIS->INIT()
//	public function setLisdId($listId, $newUser=true) {
//		if($newUser) {
//			$this->listId = $listId;
//		} else {
//
//		}
//	}

	/**
	 * Get the member email address, or empty string if not set.
	 *
	 * @return string
	 */
	public function getEmail() {
		return (isset($this->userData['email'])) ? $this->userData['email'] : '';
	}

	/**
	 * Set a new email address for this member
	 *
	 * @param  $new
	 * @return MailChimpUser
	 */
	public function setEmail($new) {
		$this->userData['email'] = $new;
		$this->userData['merges']['EMAIL'] = $new;
		return $this;
	}

	/**
	 * Get the type of emails this customer asked to get: html, text, or mobile. or empty string if not set.
	 *
	 * @return string
	 */
	public function getEmailType() {
		return (isset($this->userData['email_type'])) ? $this->userData['email_type'] : '';
	}

	/**
	 * Set the new email type this member can recieve, must be either html, text or mobile.
	 * Automatically set to HTML via init(), but can be overruled via this method.
	 *
	 * @param  $new
	 * @return MailChimpUser
	 */
	public function setEmailType($new) {
		$validTypes = array(MailChimp::EMAIL_TYPE_HTML, MailChimp::EMAIL_TYPE_TEXT, MailChimp::EMAIL_TYPE_MOBILE);
		if (in_array($new, $validTypes)) {
			$this->userData['email_type'] = $new;
		} else {
			$this->errors[] = 'Invalid email type ' . $new . '.';
		}

		return $this;
	}

	/**
	 * Get the IP Address this address opted in from, or empty string if not set.
	 *
	 * @return string
	 */
	public function getIpOpt() {
		return (isset($this->userData['ip_opt'])) ? $this->userData['ip_opt'] : $this->userData['ip_opt'];
	}

	/**
	 * Set the optin-ip when subscribing a user (automatically done via init(), but can be overruled via this method
	 *
	 * @param  $ip
	 * @return MailChimpUser
	 */
	public function setIpOpt($ip) {
		if($this->new) {
			$this->userData['merges']['OPTINIP'] = $_SERVER['REMOTE_ADDR'];
		} else {
			$this->errors[] = 'Cannot (re)set opt-in IP when editing a user.';
		}

		return $this;
	}

	/**
	 * Get the IP Address this address signed up from, or empty string if not set.
	 *
	 * @return string
	 */
	public function getIpSignup() {
		return (isset($this->userData['ip_signup'])) ? $this->userData['ip_signup'] : '';
	}

	/**
	 * Get the rating of the subscriber. Or 0 if not set.
	 *
	 * This will be 1 - 5 as described here: http://eepurl.com/f-2P
	 *
	 * @return int
	 */
	public function getMemberRating() {
		return (isset($this->userData['member_rating'])) ? $this->userData['member_rating'] : 0;
	}

	/**
	 * Get the last time this record was changed. If the record is old enough, this may be 0.
	 *
	 * @return int
	 */
	public function getInfoChanged($inSeconds=true) {
		if (empty($this->userData['info_changed']) == false) {
			if($inSeconds) {
				return strtotime($this->userData['info_changed']);
			} else {
				return $this->userData['info_changed'];
			}
		}

		return 0;
	}

	/**
	 * Get the subscription status for this member, either subscribed, unsubscribed or cleaned
	 *
	 * @return string
	 */
	public function getStatus() {
		return $this->userData['status'];
	}

	/**
	 * Set a new subscription status for this member, must be either subscribed, unsubscribed or cleaned
	 *
	 * @param  $new
	 * @return MailChimpUser
	 */
	public function setStatus($new) {
		$valid = array(MailChimp::MEMBER_STATUS_SUBSCRIBED, MailChimp::MEMBER_STATUS_UNSUBSCRIBED, MailChimp::MEMBER_STATUS_CLEANED);
		if(in_array($new, $valid)) {
			$this->userData['status'] = $new;
		} else {
			$this->errors[] = 'Invalid status ' . $new .'. Must be either subscribed, unsubscribed or cleaned.';
		}

		return $this;
	}

	/**
	 * Get the date/time this member was added to the list as a unix timestamp
	 *
	 * @return int
	 */
	public function getTimestamp($inSeconds=true) {
		if (empty($this->userData['timestamp']) == false) {
			if($inSeconds) {
				return strtotime($this->userData['timestamp']);
			} else {
				return $this->userData['timestamp'];
			}
		}

		return 0;
	}

	/**
	 * Get an associative array of the other lists this member belongs to.
	 * The key is the list id and the value is their status in that list.
	 *
	 * @return array
	 */
	public function getLists() {
		return $this->userData['lists'];
	}

	/**
	 * Get an associative array of all the merge tags and the data for those tags for this email address.
	 * NOTE: Interest Groups are returned as comma delimited strings - if a group name contains a comma, it will be escaped with a backslash. ie, "," => "\,".
	 *       Groupings will be returned with their "id" and "name" as well as a "groups" field formatted just like Interest Groups
	 *
	 * @return array
	 */
	public function getMerges() {
		return $this->userData['merges'];
	}

	/**
	 * Get a specific merge var, or return the value of ifEmpty if not found.
	 *
	 * @param string $name Name of the merge var
	 * @param mixed $ifEmpty To return if the merge var was not found
	 * @return mixed
	 */
	public function getMergeVar($name, $ifEmpty=null) {
		$name = strtoupper($name);
		return (isset($this->userData['merges'][$name])) ? $this->userData['merges'][$name] : $ifEmpty ;
	}

	/**
	 * Set a specific merge var to a new value
	 *
	 * @param string $name
	 * @param string/integer $value
	 * @return boolean True on succes, false if merge var not found
	 */
	public function setMergeVar($name, $value) {
		$name = strtoupper($name);
		if(isset($this->userData['merges'][$name]) || $this->new) {
			$this->userData['merges'][$name] = $value;
			return true;
		}

		return false;
	}

	/**
	 * Set the associative array of merges.
	 *
	 * By default, the current array of merges is first unset, and then replaced with the new one.
	 *
	 * When $mergeWithExistsing is set to true, the current array of mergevars is compared
	 * and merged with the new array. See array_merge in phpdoc.
	 *
	 * @param array $mergeWithExistsing Defaults to false.
	 * @param bool $replace Defaults to true
	 * @return MailChimpUser
	 */
	public function setMerges($new, $mergeWithExistsing=false) {
		if(is_array($new)) {
			// Make key's uppercase, just in case
			$newMerges = array();
			foreach($new as $key => $value) {
				$newMerges[strtoupper($key)] = $value;
			}

			if($mergeWithExistsing) {
				$this->userData['merges'] = array_merge($this->userData['merges'], $newMerges);
			} else {
				unset($this->userData['merges']);
				$this->userData['merges'] = $newMerges;
			}

			unset($new, $newMerges);
		} else {
			$this->errors[] = 'Invalid value for merges. Can only be an array.';
		}

		return $this;
	}

	public function setFirstName($new) {
		$this->setMergeVar('FNAME', $new);
		return $this;
	}
	

	public function setLastName($new) {
		$this->setMergeVar('LNAME', $new);
		return $this;
	}


	public function getMemberId()    { return $this->getMergeVar('MID'); }
	public function getReferralId()  { return $this->getMergeVar('RID'); }
	public function getTitle()       { return $this->getMergeVar('NAME_TITLE'); }
	public function getInitials()    { return $this->getMergeVar('NAME_INITI'); }
	public function getMiddleName()  { return $this->getMergeVar('NAME_MIDDL'); }
	public function getLastName()    { return $this->getMergeVar('NAME_LAST'); }
	public function getStreet()      { return $this->getMergeVar('ADRESS_STR'); }
	public function getHouseNumber() { return $this->getMergeVar('ADRESS_NR'); }
	public function getZipCode()     { return $this->getMergeVar('ADRESS_ZIP'); }
	public function getCity()        { return $this->getMergeVar('ADRESS_CIT'); }
}
