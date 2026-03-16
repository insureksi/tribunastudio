<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$editing_coupon = null;
$edit_id        = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;

// Cari kupon yang sedang diedit.
if ( $edit_id ) {
    foreach ( $coupons as $c ) {
        if ( (int) $c->id === $edit_id ) {
            $editing_coupon = $c;
            break;
        }
    }
}
?>
<div class="wrap tsrb-coupons">
    <h1><?php esc_html_e( 'Coupons', 'tribuna-studio-rent-booking' ); ?></h1>

    <?php if ( isset( $_GET['tsrb_msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $msg = sanitize_text_field( wp_unslash( $_GET['tsrb_msg'] ) );
                if ( 'coupon_saved' === $msg ) {
                    esc_html_e( 'Coupon saved.', 'tribuna-studio-rent-booking' );
                } elseif ( 'coupon_invalid' === $msg ) {
                    esc_html_e( 'Coupon code and value are required.', 'tribuna-studio-rent-booking' );
                } elseif ( 'coupon_save_failed' === $msg ) {
                    esc_html_e( 'Failed to save coupon.', 'tribuna-studio-rent-booking' );
                } elseif ( 'coupon_deleted' === $msg ) {
                    esc_html_e( 'Coupon deleted.', 'tribuna-studio-rent-booking' );
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <h2>
        <?php
        echo $editing_coupon
            ? esc_html__( 'Edit Coupon', 'tribuna-studio-rent-booking' )
            : esc_html__( 'Add New Coupon', 'tribuna-studio-rent-booking' );
        ?>
    </h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'tsrb_coupons_action', 'tsrb_coupons_nonce' ); ?>
        <input type="hidden" name="action" value="tsrb_coupons_form">
        <input type="hidden" name="coupon_id" value="<?php echo $editing_coupon ? esc_attr( $editing_coupon->id ) : 0; ?>">

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="tsrb-coupon-code"><?php esc_html_e( 'Coupon Code', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <input type="text" id="tsrb-coupon-code" name="code"
                        value="<?php echo $editing_coupon ? esc_attr( $editing_coupon->code ) : ''; ?>"
                        class="regular-text" required>
                    <p class="description">
                        <?php esc_html_e( 'Use uppercase letters and numbers, e.g. TRIBUNA10.', 'tribuna-studio-rent-booking' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tsrb-coupon-type"><?php esc_html_e( 'Type', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <select id="tsrb-coupon-type" name="type">
                        <option value="fixed" <?php echo ( $editing_coupon && 'fixed' === $editing_coupon->type ) ? 'selected' : ''; ?>>
                            <?php esc_html_e( 'Fixed amount (currency)', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                        <option value="percent" <?php echo ( $editing_coupon && 'percent' === $editing_coupon->type ) ? 'selected' : ''; ?>>
                            <?php esc_html_e( 'Percentage (%)', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tsrb-coupon-value"><?php esc_html_e( 'Discount Value', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <input type="number" min="0" step="0.01" id="tsrb-coupon-value" name="value"
                        value="<?php echo $editing_coupon ? esc_attr( $editing_coupon->value ) : ''; ?>"
                        required>
                    <p class="description">
                        <?php esc_html_e( 'If type is fixed, value is in currency. If percentage, value is 0–100.', 'tribuna-studio-rent-booking' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tsrb-coupon-max-usage"><?php esc_html_e( 'Usage Limit', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <input type="number" min="0" id="tsrb-coupon-max-usage" name="max_usage"
                        value="<?php echo $editing_coupon ? esc_attr( $editing_coupon->max_usage ) : 0; ?>">
                    <p class="description">
                        <?php esc_html_e( 'Maximum times this coupon can be used. 0 = no limit.', 'tribuna-studio-rent-booking' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tsrb-coupon-expires"><?php esc_html_e( 'Expiration Date', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <?php
                    $expires_value = '';
                    if ( $editing_coupon && $editing_coupon->expires_at ) {
                        $expires_value = gmdate( 'Y-m-d', strtotime( $editing_coupon->expires_at ) );
                    }
                    ?>
                    <input type="date" id="tsrb-coupon-expires" name="expires_at"
                        value="<?php echo esc_attr( $expires_value ); ?>">
                    <p class="description">
                        <?php esc_html_e( 'Optional. Leave empty for no expiration.', 'tribuna-studio-rent-booking' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tsrb-coupon-status"><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></label>
                </th>
                <td>
                    <select id="tsrb-coupon-status" name="status">
                        <option value="active" <?php echo ( $editing_coupon && 'active' === $editing_coupon->status ) ? 'selected' : ''; ?>>
                            <?php esc_html_e( 'Active', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                        <option value="inactive" <?php echo ( $editing_coupon && 'inactive' === $editing_coupon->status ) ? 'selected' : ''; ?>>
                            <?php esc_html_e( 'Inactive', 'tribuna-studio-rent-booking' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>

        <?php
        submit_button(
            $editing_coupon
                ? __( 'Update Coupon', 'tribuna-studio-rent-booking' )
                : __( 'Add Coupon', 'tribuna-studio-rent-booking' )
        );
        ?>
    </form>

    <hr>

    <h2><?php esc_html_e( 'Existing Coupons', 'tribuna-studio-rent-booking' ); ?></h2>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Code', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Type', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Value', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Usage', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Expires At', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'tribuna-studio-rent-booking' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $coupons ) ) : ?>
                <?php foreach ( $coupons as $coupon ) : ?>
                    <tr>
                        <td><?php echo esc_html( $coupon->code ); ?></td>
                        <td><?php echo esc_html( ucfirst( $coupon->type ) ); ?></td>
                        <td><?php echo esc_html( $coupon->value ); ?></td>
                        <td><?php echo esc_html( $coupon->used_count ); ?> / <?php echo esc_html( $coupon->max_usage ); ?></td>
                        <td><?php echo esc_html( $coupon->expires_at ? $coupon->expires_at : '—' ); ?></td>
                        <td><?php echo esc_html( ucfirst( $coupon->status ) ); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=tsrb-coupons&edit=' . (int) $coupon->id ) ); ?>">
                                <?php esc_html_e( 'Edit', 'tribuna-studio-rent-booking' ); ?>
                            </a>

                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:4px;">
                                <?php wp_nonce_field( 'tsrb_coupons_action', 'tsrb_coupons_nonce' ); ?>
                                <input type="hidden" name="action" value="tsrb_coupons_form">
                                <input type="hidden" name="coupon_id" value="<?php echo (int) $coupon->id; ?>">
                                <input type="hidden" name="tsrb_coupons_form_action" value="delete">
                                <button type="submit" class="button button-small button-link-delete"
                                    onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this coupon?', 'tribuna-studio-rent-booking' ) ); ?>');">
                                    <?php esc_html_e( 'Delete', 'tribuna-studio-rent-booking' ); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'No coupons found.', 'tribuna-studio-rent-booking' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
