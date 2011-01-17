<?php

namespace kwd\helpers\mailchimp;

/**
 * Main connection to the MailChimp API
 *
 * @author Kuipers Web Development
 */
class MailChimp extends MCAPI {
	const EMAIL_TYPE_HTML   = 'html';
	const EMAIL_TYPE_TEXT   = 'text';
	const EMAIL_TYPE_MOBILE = 'mobile';

	const MEMBER_STATUS_SUBSCRIBED   = 'subscribed';
	const MEMBER_STATUS_UNSUBSCRIBED = 'unsubscribed';
	const MEMBER_STATUS_CLEANED      = 'cleaned';
	const MEMBER_STATUS_UPDATED      = 'updated';

	public function __construct($apiKey, $secure = false) {
		parent::MCAPI($apiKey, $secure);
		$this->version = '1.2';
	}

	public function lists($getAsObjects = true) {
		$lists = array();
		$results = $this->callServer('lists', array());

		if ($getAsObjects) {
			foreach ($results as $result) {
				$list = new MailChimpList($this, $result);
				$lists[] = $list;
			}

			unset($results, $list);
			$results = $lists;
		}

		return $results;
	}

}
