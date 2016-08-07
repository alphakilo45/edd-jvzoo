<?php
/**
 * Add the JVZoo Meta Box
 *
 * @since 1.0
 */
function edd_jvzoo_add_jvzoo_meta_box() {

	global $post;

	add_meta_box( 'edd_jvzoo_box', __( 'JVZoo', 'edd_jvzoo' ), 'edd_jvzoo_render_meta_box', 'download', 'side', 'core' );

}
add_action( 'add_meta_boxes', 'edd_jvzoo_add_jvzoo_meta_box', 100 );


/**
 * Render the JVZoo information meta box
 *
 * @since 1.0
 */
function edd_jvzoo_render_meta_box() {
    global $post;
    $post_ipn_url = add_query_arg( array( 'jvzooipn' => 'ipn', 'eddid' => $post->ID ), get_home_url() . '/');
?>
    <table class="form-table">
        <tr>
            <td>
                <strong>JVZoo IPN URL: </strong>
                <span style="font-size: 90%;"><?php echo $post_ipn_url; ?></span>
            </td>
        </tr>
        <tr>
            <td>
                <strong>Instructions:</strong> Copy the above URL into the <strong>JVZIPN URL</strong> field in the Method #1 box under EXTERNAL PROGRAM INTEGRATION on the Edit page for your JVZoo product.
                
            <td>
	</tr>
    </table>
<?php
}