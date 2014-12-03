<?php

/**
 * TwitterContoller provides the integration functionality for the front-end
 *
 * @package sstwitter
 * @subpackage control
**/
class TwitterController extends ContentController {

	static $allowed_actions = array(
		"connect",
		"disconnect",
		"login"
	);

	/**
	 * Return a blank form to display front-end messages
	 *
	 * @return Form
	**/
	public function Form() {
		$form = new Form($this, "Twitter", new FieldList(), new FieldList());
		$this->extend("setupForm", $form);
		return $form;
	}

	/**
	* Prevent this request as it doesn't do anything.
	*
	* @return SS_HTTPResponse
	**/
	public function index() {
		return $this->httpError(403, "Forbidden");
	}


	/** 
	 * This will connect a twitter account to a logged in Member.
	 *
	 * @param $request SS_HTTPRequest
	 * @return SS_HTTPResponse
	**/
	public function connect($request) {
		$form = $this->Form();
		$member = Member::currentUser();
		if($member) {
			$twitter = TwitterApp::get()->first()->getTwitter();
			if(!$twitter) {
				$form->sessionMessage("Oops. Unable to fetch Twitter Application. Try again soon.", "bad");
				return $this->renderWith(array("TwitterController", "Page", "Controller"));
			}
			
			$twitter->setOAuthCallback(Director::absoluteURL(Controller::join_links("twitter", "connect")));

			$request = Session::get("Twitter.Request");
			if($request) {
				$twitter->setRequest(unserialize($request));
				Session::clear("Twitter.Request");
			} else {
				$request = $twitter->getRequestToken();
				if($request) {
					Session::set("Twitter.Request", serialize($request));
					return $this->redirect($twitter->getLoginURL());
				}
			}
			
			// Check to see if we have our access tokens stored in the db.
			if($member->accessToken != null && $member->accessSecret != null) {
			   	$twitter->setAccess(new OAuthToken($member->accessToken, $member->accessSecret));
			} else if ($access = $twitter->getAccessToken()) {
				$user = $twitter->getUser();
				if($user) {
					$member->connectTwitterAccount($user['id_str'], $user['screen_name'], $twitter->access()->token, $twitter->access()->secret);
					$form->sessionMessage("You have connected your Twitter account.", "good");
					$this->extend("onAfterTwitterConnect", $member);
				}
			}
		} else {
			$form->sessionMessage("You must be logged in to connect your Twitter account.", "bad");
		}
		return $this->redirect("/");
		//return $this->renderWith(array("TwitterController", "Page", "Controller"));
	}

	/**
	 * This will disconnect a members Twitter account from their SS account.
	 *
	 * @param $request SS_HTTPRequest
	 * @return SS_HTTPResponse
	**/
	public function disconnect($request) {
		$form = $this->Form();
		$member = Member::currentUser();

		if($member) {
			$member->disconnectTwitterAccount();
			$this->extend("onAfterTwitterDisconnect", $member);
		}
		$form->sessionMessage("You have disconnected your account.", "good");

		return $this->renderWith(array("TwitterController", "Page", "Controller"));
	}


	/**
	 * Log the user in via an existing Twitter account connection.
	 *
	 * @return SS_HTTPResponse
	**/
	public function login() {
		$form = $this->Form();
		
		$twitterApp = TwitterApp::get()->first();
		if(!$twitterApp || !$twitterApp->EnableTwitterLogin) {
			$form->sessionMessage("Twitter Login is disabled.", "bad");
		} else {
			if($member = Member::currentUser())
				$member->logOut();

			$twitter = $twitterApp->getTwitter();
			if($twitter) {
				$twitter->setOAuthCallback(Director::absoluteURL(Controller::join_links("twitter", "login")));

				$request = Session::get("Twitter.Request");
				if($request) {
					$twitter->setRequest(unserialize($request));
					Session::clear("Twitter.Request");
				} else {
					$request = $twitter->getRequestToken();
					if($request) {
						Session::set("Twitter.Request", serialize($request));
						return $this->redirect($twitter->getLoginURL());
					}
				}

				// Check to see if we have our access tokens stored in the db.
				if ($access = $twitter->getAccessToken()) {
					$user = $twitter->getUser();
					if($user) {
						// The twitter user is logged in. Find their account.
						$member = Member::get()->filter("TwitterUserID", $user['id_str'])->first();
						if($member) {
							$member->logIn();
							$form->sessionMessage("Success! Logged in with Twitter.", "good");
							$this->extend("onAfterTwitterLogin", $member);
						} else {

							$member = new KeepCupMember();
							$signup = $member->connectTwitterAccount($user['id_str'], $user['screen_name'], $twitter->access()->token, $twitter->access()->secret);

							if($signup) {
								$member->logIn();
								$form->sessionMessage("Success! Signed up with Twitter.", "good");
								$this->extend("onAfterTwitterConnect", $member);
							} else {
								$form->sessionMessage("Opps. Unable to create your account. Check Twitter permitions", "bad");
							}
			
						}
					} else {

						$form->sessionMessage("Oops. Unable to retrieve Twitter account.  Check your Twitter permissions.", "bad");
					}
				} else {
					$form->sessionMessage("Oops. Unable to access Twitter. Try again.", "bad");
				}
			}
		}

		// Extend Failed twitter login
		if(!Member::currentUser()) $this->extend("onAfterFailedTwitterLogin");
		return $this->redirect("/");
		//return $this->renderWith(array("TwitterController", "Page", "Controller"));
	}
}

