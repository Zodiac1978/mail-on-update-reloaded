<?php

class MailOnUpdate {
	var $mou_lastchecked;
	var $mou_lastmessage;
	var $mou_singlenotification;
	var $mou_mailto;
	var $mou_exclinact;
	var $mou_filtermethod;
	var $mou_filter;

	function __construct() {
		if ( function_exists( 'load_plugin_textdomain' ) ) {
			load_plugin_textdomain( 'mail-on-update', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		// Is WordPress at least version 3.0?
		if ( ! MOUISWP30 ) {
			add_action( 'admin_notices', array( $this, 'wpVersionFailed' ) );
			return;
		}

		// Load mail on update options.
		$this->getOptions();

		add_action( 'wp_footer', array( $this, 'checkPlugins' ) );
		add_action( 'admin_menu', array( $this, 'mouAdminMenu' ) );

		if ( function_exists( 'register_activation_hook' ) ) {
			register_activation_hook( __FILE__, array( $this, 'activate' ) );
		}
		if ( function_exists( 'register_uninstall_hook' ) ) {
			register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );
		}
	}

	function activate() {
		$options = array(
			'mou_lastchecked'        => 0,
			'mou_singlenotification' => '',
			'mou_lastmessage'        => '',
			'mou_mailto'             => '',
			'mou_exclinact'          => '',
			'mou_filtermethod'       => '',
			'mou_filter'             => '',
		);
		add_option( 'mailonupdate', $options, '', 'yes' );
	}

	static function uninstall() {
		delete_option( 'mailonupdate' );
	}

	function getOptions() {
		$options = get_option( 'mailonupdate' );

		$this->mou_lastchecked        = $options['mou_lastchecked'];
		$this->mou_lastmessage        = $options['mou_lastmessage'];
		$this->mou_singlenotification = $options['mou_singlenotification'];
		$this->mou_mailto             = $options['mou_mailto'];
		$this->mou_exclinact          = $options['mou_exclinact'];
		$this->mou_filtermethod       = $options['mou_filtermethod'];
		$this->mou_filter             = $options['mou_filter'];
	}

	function setOptions() {
		$options = array(
			'mou_lastchecked'        => $this->mou_lastchecked,
			'mou_lastmessage'        => $this->mou_lastmessage,
			'mou_singlenotification' => $this->mou_singlenotification,
			'mou_mailto'             => $this->mou_mailto,
			'mou_exclinact'          => $this->mou_exclinact,
			'mou_filtermethod'       => $this->mou_filtermethod,
			'mou_filter'             => $this->mou_filter,
		);

		update_option( 'mailonupdate', $options );
	}

	function wpVersionFailed() {
		echo "<div id='message' class='error fade'><p>" . esc_html__( 'Your WordPress is too old. Mail On Update requires at least WordPress 3.0!', 'mail-on-update' ) . '</p></div>';
	}

	function checkPlugins() {
		if ( DEBUG ) {
				echo '<!-- MOU: Checking available plugin updates -->';
		}

		// Is last check more than 12 hours ago?
		if ( time() < $this->mou_lastchecked + 43200 ) {
			return;
		}

		// Include WordPress update functions.
		@require_once ABSPATH . 'wp-admin/includes/update.php';
		@require_once ABSPATH . 'wp-admin/includes/admin.php';

		// Call the WordPress update function.
		if ( MOUISWP30 ) {
			wp_plugin_update_rows();
			$updates = get_site_transient( 'update_plugins' );
		} else {
			wp_update_plugins();
			$updates = get_transient( 'update_plugins' );
		}

		// Are plugin updates available?
		if ( empty( $updates->response ) ) {
			return;
		}

		// Get all plugins.
		$plugins            = get_plugins();
		$blogname           = get_option( 'blogname' );
		$message            = '';
		$pluginNotVaildated = '';

		// Loop through available plugin updates.
		foreach ( $updates->response as $pluginfile => $update ) {
			if ( $this->mailonupdate_pqual( $plugins[ $pluginfile ]['Name'], $pluginfile ) ) {
				/* translators: %1$s is the plugin name */
				$message .= sprintf( esc_html__( 'A new version of %1$s is available.', 'mail-on-update' ), trim( $plugins[ $pluginfile ]['Name'] ) );
				$message .= "\n";
				/* translators: %1$s is the plugin version which is installed, %2$s is the available version */
				$message .= sprintf( esc_html__( '- Installed: %1$s, Current: %2$s', 'mail-on-update' ), $plugins[ $pluginfile ]['Version'], $update->new_version );
				$message .= "\n\n";
			} else {
				( is_plugin_active( $pluginfile ) ) ? $act = esc_html__( 'active', 'mail-on-update' ) : $act = esc_html__( 'inactive', 'mail-on-update' );
				/* translators: %1$s is the new available version number, %2$s is the plugin name and %3$s is the status (active/inactive) */
				$pluginNotVaildated                       .= "\n" . sprintf( esc_html__( 'A new version (%1$s) of %2$s is available. (%3$s)', 'mail-on-update' ), $update->new_version, $plugins[ $pluginfile ]['Name'], $act );
			}
		}

		if ( $message !== '' && ( $this->mou_singlenotification === '' || ( $message !== $this->mou_lastmessage && $this->mou_singlenotification !== '' ) ) ) {
			$this->mou_lastmessage = $message;

			// Append siteurl to notfication e-mail.
			$message .= esc_html__( 'Update your Plugins at', 'mail-on-update' ) . "\n" . site_url() . '/wp-admin/update-core.php';

			if ( $pluginNotVaildated != '' ) {
				$message .= "\n\n" . esc_html__( 'There are also updates available for the plugins below. However, these plugins are of no concern for this notifier and the information is just for completeness.', 'mail-on-update' ) . "\n" . $pluginNotVaildated;
			}

			// Set mail header for notification message.
			$sender  = 'WordPress@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) );
			$from    = "From: \"$sender\" <$sender>";
			$headers = "$from\n" . 'Content-Type: text/plain; charset="' . get_option( 'blog_charset' ) . "\"\n";

			// Send e-mail notification to admin or multiple recipients.
			/* translators: %s is the name of the site */
			$subject = sprintf( esc_html__( '[%s] Plugin Update Notification', 'mail-on-update' ), $blogname );
			wp_mail( $this->mailonupdate_listOfCommaSeparatedRecipients(), $subject, $message, $headers );
		}

		// Set timestamp of last update check.
		$this->mou_lastchecked = time();
		$this->setOptions();
	}

	function mouAdminMenu() {
		add_options_page( 'Mail On Update', 'Mail On Update', 'manage_options', 'mail-on-update', array( &$this, 'mailonupdateConf' ) );
	}

	function verifyNonce( $nonce ) {
		if ( ! wp_verify_nonce( $nonce, 'mailonupdate-nonce' ) ) {
			wp_die( esc_html__( 'Security-Check failed.', 'mail-on-update' ), '', array( 'response' => 403 ) );
		}

		return true;
	}

	function mailonupdate_listOfCommaSeparatedRecipients() {
		if ( empty( $this->mou_mailto ) ) {
			return get_option( 'admin_email' );
		}

		return $this->mou_mailto;
	}

	function rbc( $option, $state_list, $default ) {
		$checked = 'checked="checked"';
		$state   = $this->mou_filtermethod;
		$hit     = false;

		foreach ( explode( ' ', $state_list ) as $istate ) {
			if ( $state === $istate ) {
				$res[ $istate ] = $checked;
				$hit            = true;
				break;
			}
		}

		$res[ "$default" ] = $hit ? false : $checked;

		if ( ! array_key_exists( 'blacklist', $res ) ) {
			$res['blacklist'] = '';
		}

		if ( ! array_key_exists( 'whitelist', $res ) ) {
			$res['whitelist'] = '';
		}

		if ( ! array_key_exists( 'nolist', $res ) ) {
			$res['nolist'] = '';
		}

		return $res;
	}

	function mailonupdate_pqual( $plugin, $plugin_file ) {
		$plugin       = strtolower( $plugin );
		$filtermethod = $this->mou_filtermethod;

		if ( $filtermethod === 'nolist' ) {
			return true;
		}

		if ( $this->mou_exclinact != '' && ! is_plugin_active( $plugin_file ) ) {
			return false;
		}

		( $filtermethod === 'whitelist' ) ? $state = false : $state = true;

		foreach ( explode( "\n", $this->mou_filter ) as $filter ) {
			$filter = trim( strtolower( $filter ) );
			if ( ! empty( $filter ) ) {
				if ( strpos( $filter, -1 ) !== '-' ) {
					if ( ! ( strpos( $plugin, $filter ) === false ) ) {
						$state = ! $state;
						break;
					}
				}
			}
		}

		return $state;
	}

	function mailonupdate_qualp() {
		$l           = '';
		$all_plugins = get_plugins();
		$del         = '';
		foreach ( (array) $all_plugins as $plugin_file => $plugin_data ) {
			$plugin = wp_kses( $plugin_data['Title'], array() );
			if ( $plugin !== '' ) {
				( is_plugin_active( $plugin_file ) ) ? $inact                  = '' : $inact = ' (' . esc_html__( 'inactive', 'mail-on-update' ) . ')';
				( $this->mailonupdate_pqual( $plugin, $plugin_file ) ) ? $flag = '[x] ' : $flag = '[ ] ';

				$l  .= "$del$flag$plugin$inact";
				$del = "\n";
			};
		};

		return $l;
	}

	function mailonupdateConf() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, but you have no permissions to change settings.', 'mail-on-update' ) );
		}

		( isset( $_REQUEST['_wpnonce'] ) ) ? $nonce = $_REQUEST['_wpnonce'] : $nonce = '';
		if ( isset( $_POST['submit'] ) && $this->verifyNonce( $nonce ) ) {
			if ( isset( $_POST['mailonupdate_singlenotification'] ) ) {
				$this->mou_singlenotification = $_POST['mailonupdate_singlenotification'];
			} else {
				$this->mou_singlenotification = '';
			}

			if ( isset( $_POST['mailonupdate_recipients'] ) ) {
				$recipients = array();
				foreach ( $_POST['mailonupdate_recipients'] as $selectedOption ) {
					$user = get_user_by( 'id', $selectedOption );
					if ( ! empty( $user ) ) {
						foreach ( $user->roles as $role ) {
							if ( $role == 'administrator' ) {
								$recipients [] = $user->user_email;
							}
						}
					}
				}

				if ( $recipients !== null && ! empty( $recipients ) ) {
					$mailto      = '';
					$lastElement = end( $recipients );
					foreach ( $recipients as $recipient ) {
						$mailto .= $recipient;
						if ( $recipient != $lastElement ) {
							$mailto .= ',';
						}
					}

					$this->mou_mailto = $mailto;
				} else {
					$this->mou_mailto = '';
				}
			}

			if ( isset( $_POST['mailonupdate_filter'] ) ) {
				$this->mou_filter       = $_POST['mailonupdate_filter'];
				$this->mou_filtermethod = $_POST['mailonupdate_filtermethod'];
				$this->mou_exclinact    = $_POST['mailonupdate_exclinact'];
			};

			$this->setOptions();
			echo '<div id="message" class="updated fade"><p><strong>' . esc_html__( 'Mail On Update settings successfully saved.', 'mail-on-update' ) . '</strong></p></div>';
		};

		$mailtos = explode( ',', $this->mou_mailto );
		$users   = array();
		$exclude = array();
		foreach ( $mailtos as $mailto ) {
			$user = get_user_by( 'email', $mailto );
			if ( $user !== null ) {
				$exclude[] = $user->ID;
				$users []  = $user;
			}
		}
		$administrators = get_users( array( 'role' => 'administrator', 'exclude' => $exclude ) );
		$nonce          = wp_create_nonce( 'mailonupdate-nonce' );

		?>

		<script type="text/javascript">
		jQuery( document ).ready(function() {
			jQuery('#remove').click(function() {
				var value = jQuery('#select1 option:selected').val();
				if (jQuery("#select2 option[value='" + value + "']").length <= 0) {
					return !jQuery('#select1 option:selected').remove().appendTo('#select2');
				} else {
					return !jQuery('#select1 option:selected').remove();
				}
			});
			jQuery('#add').click(function() {
				var value = jQuery('#select2 option:selected').val();
				if (jQuery("#select1 option[value='" + value + "']").length <= 0) {
					return !jQuery('#select2 option:selected').remove().appendTo('#select1');
				} else {
					return !jQuery('#select2 option:selected').remove();
				}
			});
			jQuery('#mailonupdate-conf').submit(function() {
				jQuery('#select1 option').each(function(i) {
					jQuery(this).attr("selected", "selected");
				});
			});
		});
		</script>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
			<h2><?php echo esc_html__( 'Mail On Update Settings', 'mail-on-update' ); ?></h2>
			<div id="poststuff" class="ui-sortable">
				<div class="postbox opened">
					<h3 class="hndle"><label for="title"><?php echo esc_html__( 'Notification settings', 'mail-on-update' ); ?></label></h3>
					<div class="inside">
						<form action="options-general.php?page=mail-on-update&_wpnonce=<?php echo $nonce; ?>" method="post" id="mailonupdate-conf">
						<table class="form-table">
							<tr>
								<td><?php echo esc_html__( 'Selected recipients', 'mail-on-update' ); ?></td>
							</tr>
							<tr>
								<td>
									<select style="width: 100%; max-width: 100%;" multiple id="select1" name="mailonupdate_recipients[]">
									<?php
									foreach ( $users as $user ) {
										if ( ! empty( $user ) ) {
											echo "<option value='" . $user->ID . "'>" . $user->display_name . ' (' . $user->user_email . ')</option>';
										}
									}
									?>
									</select>
									<br /><br /><button class="button-primary" style="width: 100%" id="remove"><?php echo esc_html__( 'Remove', 'mail-on-update' ); ?></button>
								</td>
							</tr>
							<tr>
								<td><?php echo esc_html__( 'Available recipients', 'mail-on-update' ); ?></td>
							</tr>
							<tr>
								<td>
									<select style="width: 100%; max-width: 100%;" multiple id="select2">
									<?php
									foreach ( $administrators as $administrator ) {
										if ( ! empty( $administrator ) ) {
											echo "<option value='" . $administrator->ID . "'>" . $administrator->display_name . ' (' . $administrator->user_email . ')</option>';
										}
									}
									?>
									</select>
									<br /><br /><button class="button-primary" style="width: 100%" id="add"><?php echo esc_html__( 'Add', 'mail-on-update' ); ?></button>
							</tr>
							<tr>
								<td><?php echo esc_html__( 'You can select multiple administrative users as a recipient. If no recipient is selected, the notification will be send to:', 'mail-on-update' ); ?>&nbsp;<?php echo get_option( 'admin_email' ); ?></td>
							</tr>
							<tr>
								<td valign="top">
								<label><input type="checkbox" name="mailonupdate_singlenotification" value="checked" <?php print $this->mou_singlenotification; ?> /> <?php echo esc_html__( 'Send only one notification per update', 'mail-on-update' ); ?></label>
								</td>
							</tr>
						</table>
						<p class="submit"><input type="submit" class='button-primary' name="submit" value="<?php echo esc_html__( 'Save', 'mail-on-update' ); ?>" /></p>
						</form>
					</div>
				</div>
			</div>
			<div id="poststuff" class="ui-sortable">
				<div class="postbox opened">
					<h3 class="hndle"><label for="title"><?php echo esc_html__( 'Filters', 'mail-on-update' ); ?></label></h3>
					<div class="inside">
						<form action="options-general.php?page=mail-on-update&_wpnonce=<?php echo $nonce; ?>" method="post" id="mailonupdate-conf">
						<table class="form-table">
							<tr>
								<td width="10"><textarea id="mailonupdate_filter" name="mailonupdate_filter" cols="40" rows="5"><?php echo $this->mou_filter; ?></textarea></td>
								<td valign="top">
								<?php echo esc_html__( '* A plugin is matched if the filter is a substring', 'mail-on-update' ); ?><br />
								<?php echo esc_html__( '* A filter has to appear on a single line', 'mail-on-update' ); ?><br />
								<?php echo esc_html__( '* A filter is not case sensetive', 'mail-on-update' ); ?><br />
								<?php echo esc_html__( '* A filter is considered as a string and no regexp', 'mail-on-update' ); ?><br />
								<?php echo esc_html__( '* A filter with "-" at the end is not considered', 'mail-on-update' ); ?>
								<?php $rval = $this->rbc( 'mailonupdate_filtermethod', 'nolist blacklist whitelist', 'nolist' ); ?>
								</td>
							</tr>
							<tr>
								<td valign="top">
								<input type="radio" name="mailonupdate_filtermethod" value="nolist" <?php print $rval['nolist']; ?> /> <?php echo esc_html__( 'Don\'t filter plugins', 'mail-on-update' ); ?><br />
								<input type="radio" name="mailonupdate_filtermethod" value="blacklist" <?php print $rval['blacklist']; ?> /> <?php echo esc_html__( 'Blacklist filter (exclude plugins)', 'mail-on-update' ); ?><br />
								<input type="radio" name="mailonupdate_filtermethod" value="whitelist" <?php print $rval['whitelist']; ?> /> <?php echo esc_html__( 'Whitelist filter (include plugins)', 'mail-on-update' ); ?><br />
								<input type="checkbox" name="mailonupdate_exclinact" value="checked" <?php print $this->mou_exclinact; ?> /> <?php echo esc_html__( 'Don\'t validate inactive plugins', 'mail-on-update' ); ?>
								</td>
							</tr>
						</table>
						<p class="submit"><input type="submit" class='button-primary' name="submit" value="<?php echo esc_html__( 'Save', 'mail-on-update' ); ?>" /></p>
						</form>
					</div>
				</div>
			</div>
			<div id="poststuff" class="ui-sortable">
				<div class="postbox opened">
					<h3 class="hndle"><label for="title"><?php echo esc_html__( 'Plugins to validate', 'mail-on-update' ); ?></label></h3>
					<div class="inside">
						<table class="form-table">
							<tr>
								<td><textarea id="mailonupdate_pluginmonitor" name="mailonupdate_pluginmonitor" class="large-text code" readonly="readonly" cols="50" rows="10"><?php print $this->mailonupdate_qualp(); ?></textarea></td>
							</tr>
							<tr>
								<td>
								[x] <?php echo esc_html__( 'Plugin will be validated', 'mail-on-update' ); ?><br />
								[ ] <?php echo esc_html__( 'Plugin will not be validated', 'mail-on-update' ); ?><br />
								</td>
							</tr>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
