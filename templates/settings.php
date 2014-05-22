<h3><?php _e( 'SendinBlue Newsletter Subscription Options', 'wc_sendinblue' ); ?></h3>

<table class="form-table">
	<tbody>

    <?php if ( $lists['code']=='success' ) { ?>
        <tr valign="top">
			<th class="titledesc" scope="row"><?php _e( 'Subscribe At Checkout', 'wc_sendinblue' ); ?></th>
			<td class="forminp">
				<input id="wc_sb_subscribe_checkout" type="checkbox" <?php checked( $admin_options['subscribe_checkout'], '1' ); ?> name="wc_sb_subscribe_checkout" />
				<span class="description"><?php _e( 'Check this box if you want to present customers with a subcribe to newsletter option at checkout.', 'wc_sendinblue' ); ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th class="titledesc" scope="row"><?php _e( 'Subscribe Label', 'wc_sendinblue' ); ?></th>
			<td class="forminp">
				<input id="wc_sb_subscribe_label" type="text" value="<?php echo $admin_options['subscribe_label']; ?>" name="wc_sb_subscribe_label" />
				<span class="description"><?php _e( 'The label to display next to the subscribe checkbox at checkout.', 'wc_sendinblue' ); ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th class="titledesc" scope="row"><?php _e( 'Subscribe Default Checked', 'wc_sendinblue' ); ?></th>
			<td class="forminp">
				<input id="wc_sb_subscribe_checked" type="checkbox" <?php checked( $admin_options['subscribe_checked'], '1' ); ?> name="wc_sb_subscribe_checked" />
				<span class="description"><?php _e( 'Check this box if you want the subscribe checkbox at checkout to be checked by default.', 'wc_sendinblue' ); ?></span>
			</td>
		</tr>
        <tr valign="top">
			<th class="titledesc" scope="row"><?php _e( 'Subscription List', 'wc_sendinblue' ); ?></th>
			<td class="forminp">
				<select id="wc_sb_subscribe_id" value="<?php echo $admin_options['subscribe_id']; ?>" name="wc_sb_subscribe_id">
                    <?php foreach( (array) $lists['data'] as $this_list ) { ?>
                        <option value="<?php echo esc_attr( $this_list['id'] ); ?>"<?php selected( $this_list['id'], $admin_options['subscribe_id'] ); ?>><?php echo $this_list['name']; ?></option>
                    <?php } ?>
                </select>
				<span class="description"><?php _e( 'Select the list you would like customers to subscribe to.', 'wc_sendinblue' ); ?></span>
			</td>
		</tr>

    <?php } else { ?>
        <tr valign="top">
			<th class="titledesc" scope="row"><?php _e( 'Access Key', 'wc_sendinblue' ); ?></th>
			<td class="forminp">
				<input id="wc_sb_access_key" type="text" name="wc_sb_access_key" value="<?php echo $admin_options['access_token']; ?>"/>
				<span class="description"><?php _e( 'Enter your SendinBlue access key here. ', 'wc_sendinblue' ); ?><a href="https://my.sendinblue.com/advanced/apikey" target="_blank"> https://my.sendinblue.com/advanced/apikey</a></span>
			</td>
		</tr>
		<tr valign="top">
			<th class="titledesc" scope="row"><?php _e( 'Secret Key', 'wc_sendinblue' ); ?></th>
			<td class="forminp">
				<input id="wc_sb_secret_key" type="text" name="wc_sb_secret_key" value="<?php echo $admin_options['access_secret']; ?>"/>
				<span class="description"><?php _e( 'Enter your SendinBlue secret key here. ', 'wc_sendinblue' ); ?><a href="https://my.sendinblue.com/advanced/apikey" target="_blank"> https://my.sendinblue.com/advanced/apikey</a></span>
			</td>
		</tr>
    <?php } ?>
	</tbody>
</table>