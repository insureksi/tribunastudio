<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$editing_addon = null;
$edit_id       = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;

if ( $edit_id && ! empty( $addons ) ) {
    foreach ( $addons as $a ) {
        if ( (int) $a->id === $edit_id ) {
            $editing_addon = $a;
            break;
        }
    }
}

$form_mode = $editing_addon ? 'edit' : 'add';
?>
<div class="wrap tsrb-addons">
    <h1><?php esc_html_e( 'Add-ons', 'tribuna-studio-rent-booking' ); ?></h1>

    <?php if ( isset( $_GET['tsrb_msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $msg = sanitize_text_field( wp_unslash( $_GET['tsrb_msg'] ) );
                if ( 'addon_saved' === $msg ) {
                    esc_html_e( 'Add-on saved.', 'tribuna-studio-rent-booking' );
                } elseif ( 'addon_deleted' === $msg ) {
                    esc_html_e( 'Add-on deleted.', 'tribuna-studio-rent-booking' );
                } elseif ( 'addon_name_required' === $msg ) {
                    esc_html_e( 'Add-on name is required.', 'tribuna-studio-rent-booking' );
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <h2>
        <?php
        echo $editing_addon
            ? esc_html__( 'Edit Add-on', 'tribuna-studio-rent-booking' )
            : esc_html__( 'Add New Add-on', 'tribuna-studio-rent-booking' );
        ?>
    </h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'tsrb_addons_action', 'tsrb_addons_nonce' ); ?>
        <input type="hidden" name="action" value="tsrb_addons_form">
        <input type="hidden" name="tsrb_addons_form_action" value="<?php echo esc_attr( $form_mode ); ?>">
        <input type="hidden" name="addon_id" value="<?php echo $editing_addon ? esc_attr( $editing_addon->id ) : 0; ?>">

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="tsrb-addon-name"><?php esc_html_e( 'Name', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <input type="text" id="tsrb-addon-name" name="name"
                        value="<?php echo $editing_addon ? esc_attr( $editing_addon->name ) : ''; ?>"
                        class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tsrb-addon-desc"><?php esc_html_e( 'Description', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <textarea id="tsrb-addon-desc" name="description" rows="3" class="large-text"><?php echo $editing_addon ? esc_textarea( $editing_addon->description ) : ''; ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tsrb-addon-price"><?php esc_html_e( 'Price', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <input type="number" min="0" step="1000" id="tsrb-addon-price" name="price"
                        value="<?php echo $editing_addon ? esc_attr( $editing_addon->price ) : ''; ?>">
                    <p class="description">
                        <?php esc_html_e( 'Price per booking, in the same currency as global settings.', 'tribuna-studio-rent-booking' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tsrb-addon-status"><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <select id="tsrb-addon-status" name="status">
                        <option value="active" <?php echo ( $editing_addon && 'active' === $editing_addon->status ) ? 'selected' : ''; ?>>
                            <?php esc_html_e( 'Active', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                        <option value="inactive" <?php echo ( $editing_addon && 'inactive' === $editing_addon->status ) ? 'selected' : ''; ?>>
                            <?php esc_html_e( 'Inactive', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>

        <?php
        submit_button(
            $editing_addon
                ? __( 'Update Add-on', 'tribuna-studio-rent-booking' )
                : __( 'Add Add-on', 'tribuna-studio-rent-booking' )
        );
        ?>
    </form>

    <hr>

    <h2><?php esc_html_e( 'Existing Add-ons', 'tribuna-studio-rent-booking' ); ?></h2>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Name', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Price', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'tribuna-studio-rent-booking' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $addons ) ) : ?>
                <?php foreach ( $addons as $addon ) : ?>
                    <tr>
                        <td><?php echo esc_html( $addon->id ); ?></td>
                        <td><?php echo esc_html( $addon->name ); ?></td>
                        <td><?php echo esc_html( Tribuna_Helpers::format_price( $addon->price ) ); ?></td>
                        <td><?php echo esc_html( ucfirst( $addon->status ) ); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=tsrb-addons&edit=' . (int) $addon->id ) ); ?>">
                                <?php esc_html_e( 'Edit', 'tribuna-studio-rent-booking' ); ?>
                            </a>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                <?php wp_nonce_field( 'tsrb_addons_action', 'tsrb_addons_nonce' ); ?>
                                <input type="hidden" name="action" value="tsrb_addons_form">
                                <input type="hidden" name="tsrb_addons_form_action" value="delete">
                                <input type="hidden" name="addon_id" value="<?php echo esc_attr( $addon->id ); ?>">
                                <button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Are you sure?', 'tribuna-studio-rent-booking' ) ); ?>');">
                                    <?php esc_html_e( 'Delete', 'tribuna-studio-rent-booking' ); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e( 'No add-ons found.', 'tribuna-studio-rent-booking' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
