<?php

defined( 'ABSPATH' ) or die();

class Optin_Comment_Notifications_Test extends WP_UnitTestCase {

	public function tearDown() {
		parent::tearDown();
		$this->unset_current_user();

		// Ensure the filters get removed
		remove_filter( 'c2c_optin_comment_notifications_has_cap', array( $this, 'restrict_capability' ), 10, 2 );
		remove_filter( 'c2c_optin_comment_notifications_has_cap_edit_others', array( $this, 'restrict_capability' ), 10, 2 );
	}


	//
	//
	// HELPER FUNCTIONS
	//
	//


	private function create_user( $email, $optin = false, $role = 'subscriber' ) {
		$user_id = $this->factory->user->create( array( 'user_email' => $email, 'role' => $role ) );
		if ( $optin ) {
			update_user_option(
				$user_id,
				c2c_Optin_Comment_Notifications::$option_name,
				c2c_Optin_Comment_Notifications::$yes_option_value
			);
		}
		return $user_id;
	}

	// helper function, unsets current user globally. Taken from post.php test.
	private function unset_current_user() {
		global $current_user, $user_ID;

		$current_user = $user_ID = null;
	}


	//
	//
	// FUNCTIONS FOR HOOKING ACTIONS/FILTERS
	//
	//


	public function restrict_capability( $default, $caps ) {
		// Only allow administrators and editors.
		return !! array_intersect(
			(array) wp_get_current_user()->roles, // Get current user's roles.
			array( 'administrator', 'editor' )
		);
	}


	//
	//
	// TESTS
	//
	//


	public function test_plugin_version() {
		$this->assertEquals( '1.2', c2c_Optin_Comment_Notifications::version() );
	}

	public function test_opted_in_user_is_notified_about_new_comment() {
		$post_id    = $this->factory->post->create();
		$email      = 'admin@example.com';
		$user_id    = $this->create_user( $email, true, 'administrator' );
		$comment_id = $this->factory->comment->create( array( 'comment_approved' => '1' ) );

		$this->assertEquals( array( $email ), apply_filters( 'comment_notification_recipients', array(), $comment_id ) );
	}

	public function test_non_opted_in_user_is_not_notified_about_new_comment() {
		$post_id    = $this->factory->post->create();
		$email      = 'admin@example.com';
		$user_id    = $this->create_user( $email, false, 'administrator' );
		$comment_id = $this->factory->comment->create( array( 'comment_approved' => '1' ) );

		$this->assertEmpty( apply_filters( 'comment_notification_recipients', array(), $comment_id ) );
	}

	public function test_opted_in_user_is_not_notified_about_their_own_comment() {
		$post_id    = $this->factory->post->create();
		$email      = 'admin@example.com';
		$user_id    = $this->create_user( $email, true, 'administrator' );
		$comment_id = $this->factory->comment->create( array( 'user_id' => $user_id, 'comment_approved' => '1' ) );

		$this->assertEmpty( apply_filters( 'comment_notification_recipients', array(), $comment_id ) );
	}

	public function test_commenter_with_moderate_comments_capability_is_notified_about_moderated_comment() {
		$post_id    = $this->factory->post->create();
		$email      = 'admin@example.com';
		$user_id    = $this->create_user( $email, true, 'administrator' );
		$comment_id = $this->factory->comment->create( array( 'comment_approved' => '0' ) );

		$this->assertEquals( array( $email ), apply_filters( 'comment_moderation_recipients', array(), $comment_id ) );
	}

	public function test_commenter_without_moderate_comments_capability_is_not_notified_about_moderated_comment() {
		$post_id    = $this->factory->post->create();
		$email      = 'subscriber@example.com';
		$user_id    = $this->create_user( $email, true, 'subscriber' );
		$comment_id = $this->factory->comment->create( array( 'comment_approved' => '0' ) );

		$this->assertEmpty( apply_filters( 'comment_moderation_recipients', array(), $comment_id ) );
	}

	public function test_opted_in_user_is_not_notified_about_new_comment_if_capability_is_changed() {
		$post_id    = $this->factory->post->create();
		$email      = 'subscriber@example.com';
		$user_id    = $this->create_user( $email, true, 'subscriber' );
		$comment_id = $this->factory->comment->create( array( 'comment_approved' => '1' ) );

		add_filter( 'c2c_optin_comment_notifications_has_cap', array( $this, 'restrict_capability' ), 10, 2 );

		$this->assertEmpty( apply_filters( 'comment_moderation_recipients', array(), $comment_id ) );
	}

	public function test_user_is_not_double_notified() {
		$post_id    = $this->factory->post->create();
		$email      = 'admin@example.com';
		$user_id    = $this->create_user( $email, true, 'administrator' );
		$comment_id = $this->factory->comment->create( array( 'comment_approved' => '1' ) );

		$this->assertEquals( array( $email ), apply_filters( 'comment_notification_recipients', array( $email ), $comment_id ) );
	}

	public function test_verify_existing_notification_email_addresses_are_not_dropped_when_adding() {
		$post_id    = $this->factory->post->create();
		$email      = 'admin@example.com';
		$emails     = array( 'a@example.com', 'b@example.com' );
		$user_id    = $this->create_user( $email, true, 'administrator' );
		$comment_id = $this->factory->comment->create( array( 'comment_approved' => '1' ) );

		$this->assertEquals( array_merge( $emails, array( $email ) ), apply_filters( 'comment_notification_recipients', $emails, $comment_id ) );
	}

	public function test_verify_existing_notification_email_addresses_are_not_dropped_when_not_adding() {
		$post_id    = $this->factory->post->create();
		$email      = 'subscriber@example.com';
		$emails     = array( 'a@example.com', 'b@example.com' );
		$user_id    = $this->create_user( $email, false, 'subscriber' );
		$comment_id = $this->factory->comment->create( array( 'comment_approved' => '1' ) );

		$this->assertEquals( $emails, apply_filters( 'comment_notification_recipients', $emails, $comment_id ) );
	}

	public function test_checkbox_is_output_for_low_privilege_user( $value = false, $current_user_id = false ) {
		$user_id = $this->create_user( 'test@example.com', $value, 'subscriber' );
		wp_set_current_user( $current_user_id ? $current_user_id : $user_id );

		ob_start();
		c2c_Optin_Comment_Notifications::add_comment_notification_checkbox( get_user_by( 'ID', $user_id ) );
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertNotEmpty( $output );

		// For use in another test
		return $output;
	}

	public function test_checkbox_is_output_as_checked_when_option_is_set() {
		$output = $this->test_checkbox_is_output_for_low_privilege_user( true );

		$this->assertRegExp( '/checked=\'checked\'/', $output );
	}

	public function test_checkbox_is_not_output_as_checked_when_option_is_not_set() {
		$output = $this->test_checkbox_is_output_for_low_privilege_user( false );

		$this->assertNotRegExp( '/checked=\'checked\'/', $output );
	}

	public function test_checkbox_label_says_me_for_current_user_profile() {
		$output = $this->test_checkbox_is_output_for_low_privilege_user( true );

		$this->assertRegExp( '/Email me whenever/', $output );
	}

	public function test_checkbox_for_other_user_is_output_for_privileged_user() {
		$user_id = $this->create_user( 'admin@example.com', true, 'administrator' );

		$output = $this->test_checkbox_is_output_for_low_privilege_user( true, $user_id );

		$this->assertNotEmpty( $output );

		// For use in another test
		return $output;
	}

	public function test_checkbox_label_says_this_user_for_other_user_profile() {
		$output = $this->test_checkbox_for_other_user_is_output_for_privileged_user();

		$this->assertRegExp( '/Email this user whenever/', $output );
	}

	public function test_checkbox_not_output_if_user_not_capable() {
		$user_id = $this->create_user( 'test@example.com', false, 'subscriber' );
		wp_set_current_user( $user_id );
		add_filter( 'c2c_optin_comment_notifications_has_cap', array( $this, 'restrict_capability' ), 10, 2 );

		ob_start();
		c2c_Optin_Comment_Notifications::add_comment_notification_checkbox( wp_get_current_user() );
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertEmpty( $output );
	}

	public function test_default_capability_is_true_for_low_privilege_user() {
		$user_id = $this->create_user( 'test@example.com', false, 'subscriber' );
		wp_set_current_user( $user_id );

		$this->assertTrue( user_can( $user_id, c2c_Optin_Comment_Notifications::$cap_name ) );
	}

	public function test_filter_allows_customizing_capability() {
		$user_id = $this->create_user( 'test@example.com', false, 'subscriber' );
		wp_set_current_user( $user_id );

		add_filter( 'c2c_optin_comment_notifications_has_cap', array( $this, 'restrict_capability' ), 10, 2 );

		$this->assertFalse( user_can( $user_id, c2c_Optin_Comment_Notifications::$cap_name ) );
	}

	public function test_default_capability_to_edit_setting_for_others_is_false_for_low_privilege_user() {
		$user_id = $this->create_user( 'test@example.com', false, 'editor' );
		wp_set_current_user( $user_id );

		$this->assertFalse( user_can( $user_id, c2c_Optin_Comment_Notifications::$cap_edit_others ) );
	}

	public function test_filter_allows_customizing_capability_to_edit_setting_for_others() {
		$user_id = $this->create_user( 'test@example.com', false, 'editor' );
		wp_set_current_user( $user_id );

		add_filter( 'c2c_optin_comment_notifications_has_cap_edit_others', array( $this, 'restrict_capability' ), 10, 2 );

		$this->assertTrue( user_can( $user_id, c2c_Optin_Comment_Notifications::$cap_edit_others ) );
	}

}
