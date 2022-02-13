<?php
/**
 * This template contains the friend editor.
 *
 * @version 1.0
 * @package Friends
 */

$has_last_log = false;
?><form method="post">
	<?php wp_nonce_field( 'edit-friend-' . $args['friend']->ID ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="friends_avatar"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Avatar' ); ?></label></th>
				<td>
				<?php echo get_avatar( $args['friend']->ID ); ?>
			</td>
			</tr>
			<tr>
				<th><label for="friends_display_name"><?php esc_html_e( 'Display Name', 'friends' ); ?></label></th>
				<td><input type="text" name="friends_display_name" id="friends_display_name" value="<?php echo esc_attr( $args['friend']->display_name ); ?>" class="regular-text" /> <p class="description"><?php esc_html_e( 'Careful, your friend can discover this.', 'friends' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="friends_description"><?php esc_html_e( 'Description', 'friends' ); ?></label></th>
				<td><textarea name="friends_description" id="friends_description" rows="5" cols="30"><?php echo esc_html( $args['friend']->description ); ?></textarea><p class="description"><?php esc_html_e( 'Careful, your friend can discover this.', 'friends' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="url"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'URL' ); ?></label></th>
				<td><input type="text" name="user_url" value="<?php echo esc_attr( $args['friend']->user_url ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="url"><?php esc_html_e( 'Feeds', 'friends' ); ?></label></th>
				<td>
					<?php if ( empty( $args['friend']->get_active_feeds() ) ) : ?>
						<?php esc_html_e( 'There are no active feeds.', 'friends' ); ?>
					<?php endif; ?>
					<table class="feed-table<?php echo empty( $args['friend']->get_active_feeds() ) ? ' hidden' : ''; ?>">
						<thead>
							<tr>
								<th class="checkbox"><?php esc_html_e( 'Active', 'friends' ); ?></th>
								<th><?php esc_html_e( 'Feed URL', 'friends' ); ?></th>
								<th><?php esc_html_e( 'Parser', 'friends' ); ?></th>
								<th><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */  esc_html_e( 'Post Format' ); ?></th>
								<th><?php esc_html_e( 'Remarks', 'friends' ); ?></th>
								<?php if ( apply_filters( 'friends_debug', false ) ) : ?>
								<th><?php esc_html_e( 'MIME Type', 'friends' ); ?></th>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $args['friend']->get_feeds() as $term_id => $feed ) :
							if ( $feed->get_last_log() ) {
								$has_last_log = true;
								$last_log = $feed->get_last_log();
							}
							?>
							<tr class="<?php echo $feed->get_active() ? 'active' : 'inactive hidden'; ?>">
								<td><input type="checkbox" name="feeds[<?php echo esc_attr( $term_id ); ?>][active]" value="1" aria-label="<?php esc_attr_e( 'Feed is active', 'friends' ); ?>"<?php checked( $feed->get_active() ); ?> /></td>
								<td><input type="url" name="feeds[<?php echo esc_attr( $term_id ); ?>][url]" value="<?php echo esc_attr( $feed->get_url() ); ?>" size="20" aria-label="<?php esc_attr_e( 'Feed URL', 'friends' ); ?>" class="url" /></td>
								<td><select name="feeds[<?php echo esc_attr( $term_id ); ?>][parser]" aria-label="<?php esc_attr_e( 'Parser', 'friends' ); ?>">
									<?php foreach ( $args['registered_parsers'] as $slug => $parser_name ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $slug, $feed->get_parser() ); ?>><?php echo esc_html( strip_tags( $parser_name ) ); ?></option>
									<?php endforeach; ?>
									<?php if ( 'unsupported' === $feed->get_parser() ) : ?>
										<option value="<?php echo esc_attr( $feed->get_parser() ); ?>" selected="selected">
											<?php
											// translators: %s is the name of a deleted parser.
											echo esc_html( $feed->get_parser() );
											?>
										</option>
									<?php elseif ( ! isset( $args['registered_parsers'][ $feed->get_parser() ] ) ) : ?>
										<option value="<?php echo esc_attr( $feed->get_parser() ); ?>" selected="selected">
											<?php
											// translators: %s is the name of a deleted parser.
											echo esc_html( sprintf( __( '%s (deleted)', 'friends' ), $feed->get_parser() ) );
											?>
										</option>
									<?php endif; ?>
								</select> <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=add-friend&parser=' . urlencode( $feed->get_parser() ) . '&feed=' . urlencode( $term_id ) . '&preview=' . urlencode( $feed->get_url() ) ) ), 'preview-feed' ) ); ?>" class="preview-parser" target="_blank" rel="noopener noreferrer"><?php esc_attr_e( 'Preview', 'friends' ); ?></a></td>
								<td><select name="feeds[<?php echo esc_attr( $term_id ); ?>][post-format]" aria-label="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Post Format' ); ?>">
									<?php foreach ( $args['post_formats'] as $format => $title ) : ?>
										<option value="<?php echo esc_attr( $format ); ?>"<?php selected( $format, $feed->get_post_format() ); ?>><?php echo esc_html( $title ); ?></option>
									<?php endforeach; ?>
								</select></td>
								<td><input type="text" name="feeds[<?php echo esc_attr( $term_id ); ?>][title]" value="<?php echo esc_attr( $feed->get_title() ); ?>" size="20" aria-label="<?php esc_attr_e( 'Feed Name', 'friends' ); ?>" /></td>
								<?php if ( apply_filters( 'friends_debug', false ) ) : ?>
									<td><input type="text" name="feeds[<?php echo esc_attr( $term_id ); ?>][mime-type]" value="<?php echo esc_attr( $feed->get_mime_type() ); ?>" size="20" aria-label="<?php esc_attr_e( 'Feed Type', 'friends' ); ?>" /></td>
								<?php endif; ?>
							</tr>
							<?php if ( $feed->get_last_log() ) : ?>
							<tr class="<?php echo $feed->get_active() ? 'active' : 'inactive hidden'; ?> lastlog hidden">
								<td colspan="5" class="notice"><?php echo esc_html( $feed->get_last_log() ); ?></td>
							</tr>
							<?php endif; ?>
						<?php endforeach; ?>
						<tr class="template hidden">
							<td></td>
							<td><input type="url" name="feeds[new][url]" value="" size="20" aria-label="<?php esc_attr_e( 'Feed URL', 'friends' ); ?>" class="url" /></td>
							<td><select name="feeds[new][parser]" aria-label="<?php esc_attr_e( 'Parser', 'friends' ); ?>">
								<?php foreach ( $args['registered_parsers'] as $slug => $parser_name ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( strip_tags( $parser_name ) ); ?></option>
								<?php endforeach; ?>
							</select> <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=add-friend&parser=&preview=' ) ), 'preview-feed' ) ); ?>" class="preview-parser" target="_blank" rel="noopener noreferrer"><?php esc_attr_e( 'Preview', 'friends' ); ?></a></td>
							<td><select name="feeds[new][post-format]" aria-label="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Post Format' ); ?>">
								<?php foreach ( $args['post_formats'] as $format => $title ) : ?>
									<option value="<?php echo esc_attr( $format ); ?>"><?php echo esc_html( $title ); ?></option>
								<?php endforeach; ?>
							</select></td>
							<td><input type="text" name="feeds[new][title]" value="" size="20" aria-label="<?php esc_attr_e( 'Feed Name', 'friends' ); ?>" /></td>
						</tr>
						</tbody>
					</table>
					<?php if ( count( $args['friend']->get_active_feeds() ) !== count( $args['friend']->get_feeds() ) ) : ?>
					<a href="" class="show-inactive-feeds"><?php esc_html_e( 'Show inactive feeds', 'friends' ); ?></a> |
					<?php endif; ?>
					<?php if ( $has_last_log ) : ?>
					<a href="" class="show-log-lines"><?php esc_html_e( 'Show log output', 'friends' ); ?></a> |
					<?php endif; ?>
					<a href="" class="add-feed"><?php esc_html_e( 'Add a feed', 'friends' ); ?></a>
				</td>
			</tr>
			<?php if ( $args['friend']->can_refresh_feeds() ) : ?>
			<tr>
				<th><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Posts' ); ?></th>
				<td>
					<fieldset>
						<label for="show_on_friends_page">
							<input name="show_on_friends_page" type="checkbox" id="show_on_friends_page" value="1" <?php checked( '1', ! in_array( $args['friend']->ID, $args['hide_from_friends_page'] ) ); ?>>
							<?php esc_html_e( 'Show posts on your friends page', 'friends' ); ?>
						</label>
					</fieldset>
					<fieldset>
					<a href="<?php echo esc_url( $args['friend']->get_local_friends_page_url() ); ?>">
						<?php
						// translators: %d is the number of posts.
						echo esc_html( sprintf( _n( 'View %d post', 'View %d posts', $args['friend_posts']->found_posts, 'friends' ), $args['friend_posts']->found_posts ) );
						?>
					</a>
					</fieldset>
					<p class="description">
					<?php
					// translators: %s is a URL.
					printf( __( '<a href=%s>Explicitly refresh</a> this feed now.', 'friends' ), esc_url( self_admin_url( 'admin.php?page=friends-refresh&user=' . $args['friend']->ID ) ) );
					?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Retention', 'friends' ); ?></th>
				<td>
					<fieldset>
						<div>
							<input type="checkbox" name="friends_enable_retention_days" id="friends_enable_retention_days" value="1" <?php checked( '1', $args['friend']->is_retention_days_enabled() ); ?> />
							<span id="friends_enable_retention_days_line" class="<?php echo esc_attr( $args['friend']->is_retention_days_enabled() ? '' : 'disabled' ); ?>">
							<?php
							echo wp_kses(
								sprintf(
									// translators: %s is an input field that allows specifying a number.
									__( 'Only keep posts for %s days', 'friends' ),
									'<input type="number" min="1" id="friends_retention_days" name="friends_retention_days" value="' . esc_attr( $args['friend']->get_retention_days() ) . '"' . ( $args['friend']->is_retention_days_enabled() ? '' : ' disabled="disabled"' ) . ' size="3">'
								),
								array(
									'input' => array(
										'type'     => array(),
										'min'      => array(),
										'id'       => array(),
										'name'     => array(),
										'value'    => array(),
										'size'     => array(),
										'disabled' => array(),
									),
								)
							);
							?>
							</span>
						</div>
						<div>
							<input type="checkbox" name="friends_enable_retention_number" id="friends_enable_retention_number" value="1" <?php checked( '1', $args['friend']->is_retention_number_enabled() ); ?> />
							<span id="friends_enable_retention_number_line" class="<?php echo esc_attr( $args['friend']->is_retention_number_enabled() ? '' : 'disabled' ); ?>">
							<?php
							echo wp_kses(
								sprintf(
									// translators: %s is an input field that allows specifying a number.
									__( 'Only keep the last %s posts', 'friends' ),
									'<input type="number" min="1" id="friends_retention_number" name="friends_retention_number" value="' . esc_attr( $args['friend']->get_retention_number() ) . '"' . ( $args['friend']->is_retention_number_enabled() ? '' : ' disabled="disabled"' ) . ' size="3">'
								),
								array(
									'input' => array(
										'type'     => array(),
										'min'      => array(),
										'id'       => array(),
										'name'     => array(),
										'value'    => array(),
										'size'     => array(),
										'disabled' => array(),
									),
								)
							);
							?>
							</span>
						</div>
					</fieldset>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
							// translators: %s is a size in bytes or kilo bytes (kB).
								__( 'Currently the posts use %s of disk space. If you need to limit the amount of space, choose one of the options above (they can be combined). The next auto-delete will kick in when refreshing the feeds of this friend.', 'friends' ),
								size_format( $args['total_size'], 1 )
							)
						);
						?>
					</p>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><?php esc_html_e( 'Created', 'friends' ); ?></th>
				<td><?php echo esc_html( date_i18n( /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ __( 'F j, Y g:i a' ), strtotime( $args['friend']->user_registered ) ) ); ?></td>
			</tr>
			<tr>
				<th><label for="status"><?php esc_html_e( 'Status', 'friends' ); ?></label></th>
				<td>
					<?php echo esc_html( $args['friend']->get_role_name() ); ?>
					<?php if ( $args['friend']->has_cap( 'friend_request' ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'accept-friend-request-' . $args['friend']->ID, 'accept-friend-request' ) ); ?>"><?php esc_html_e( 'Accept Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'pending_friend_request' ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'add-friend-' . $args['friend']->ID, 'add-friend' ) ); ?>"><?php esc_html_e( 'Resend Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'subscription' ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'add-friend-' . $args['friend']->ID, 'add-friend' ) ); ?>"><?php esc_html_e( 'Send Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'acquaintance' ) ) : ?>
						<p class="description">
							<?php
							// translators: %s is a friend role.
							echo wp_kses( sprintf( __( 'Change to %s.', 'friends' ), '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'change-to-friend-' . $args['friend']->ID, 'change-to-friend' ) ) . '">' . __( 'Friend', 'friends' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
							?>
							<?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'friend' ) ) : ?>
						<p class="description">
						<?php
							// translators: %s is a friend role.
						echo wp_kses( sprintf( __( 'Change to %s.', 'friends' ), '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'change-to-restricted-friend-' . $args['friend']->ID, 'change-to-restricted-friend' ) ) . '">' . __( 'Acquaintance', 'friends' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
						?>
							<?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'New Post Notification', 'friends' ); ?></th>
				<td>
					<?php if ( get_user_option( 'friends_no_new_post_notification' ) ) : ?>
						<span class="description"><?php esc_html_e( 'You have generally disabled new post notifications for yourself.', 'friends' ); ?> <a href="<?php echo esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=friends-settings' ) ) ); ?>"><?php esc_html_e( 'Change this setting', 'friends' ); ?></a></span>
					<?php else : ?>
					<fieldset>
						<label for="friends_new_post_notification">
							<input name="friends_new_post_notification" type="checkbox" id="friends_new_post_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_new_post_notification_' . $args['friend']->ID ) ); ?> />
							<?php esc_html_e( 'Send me an e-mail for posts of this friend', 'friends' ); ?>
						</label>
					</fieldset>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Keyword Notification', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="friends_keyword_notification">
							<input name="friends_keyword_notification" type="checkbox" id="friends_keyword_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_keyword_notification_' . $args['friend']->ID ) ); ?> />
							<?php
							echo wp_kses_post(
								sprintf(
									// translators: %s is a URL.
									__( 'Send me an e-mail for posts of this friend if matches one of <a href="%s">my keywords</a>', 'friends' ),
									esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=friends-settings' ) ) )
								)
							);
							?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rules', 'friends' ); ?></th>
				<td><a href="<?php echo esc_url( self_admin_url( 'admin.php?page=edit-friend-rules&user=' . $args['friend']->ID ) ); ?>">
					<?php
					// translators: %d is the number of rules.
					echo esc_html( sprintf( _n( '%d rule', '%d rules', count( $args['rules'] ), 'friends' ), count( $args['rules'] ) ) );
					?>
				</td>
			</tr>
			<?php do_action( 'friends_edit_friend_table_end', $args['friend'] ); ?>
		</tbody>
	</table>
	<?php do_action( 'friends_edit_friend_after_form', $args['friend'] ); ?>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
	<p class="description" id="friend_url-description">
		<?php
		// translators: %s is the user URL.
		echo wp_kses( sprintf( __( 'To unfriend this user, just <a href=%s>delete them on the users page</a>.', 'friends' ), '"' . self_admin_url( 'users.php?s=' . urlencode( $args['friend']->user_login ) ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</p>
</form>
