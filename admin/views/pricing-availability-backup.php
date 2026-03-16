<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$days = array(
    'monday'    => __( 'Monday', 'tribuna-studio-rent-booking' ),
    'tuesday'   => __( 'Tuesday', 'tribuna-studio-rent-booking' ),
    'wednesday' => __( 'Wednesday', 'tribuna-studio-rent-booking' ),
    'thursday'  => __( 'Thursday', 'tribuna-studio-rent-booking' ),
    'friday'    => __( 'Friday', 'tribuna-studio-rent-booking' ),
    'saturday'  => __( 'Saturday', 'tribuna-studio-rent-booking' ),
    'sunday'    => __( 'Sunday', 'tribuna-studio-rent-booking' ),
);
?>
<div class="wrap tsrb-pricing">
    <h1><?php esc_html_e( 'Pricing & Availability', 'tribuna-studio-rent-booking' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'tsrb_settings_group' ); ?>
        <?php do_settings_sections( 'tsrb_settings_group' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="tsrb-hourly-price"><?php esc_html_e( 'Hourly Price', 'tribuna-studio-rent-booking' ); ?></label></th>
                <td>
                    <input type="number" min="0" step="1000" id="tsrb-hourly-price" name="tsrb_settings[hourly_price]" value="<?php echo isset( $settings['hourly_price'] ) ? esc_attr( $settings['hourly_price'] ) : 0; ?>">
                    <p class="description"><?php esc_html_e( 'Base hourly price for studio booking.', 'tribuna-studio-rent-booking' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Operating Hours', 'tribuna-studio-rent-booking' ); ?></h2>
        <table class="widefat fixed striped tsrb-operating-hours">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Day', 'tribuna-studio-rent-booking' ); ?></th>
                    <th><?php esc_html_e( 'Open', 'tribuna-studio-rent-booking' ); ?></th>
                    <th><?php esc_html_e( 'Close', 'tribuna-studio-rent-booking' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $days as $key => $label ) : ?>
                    <tr>
                        <td><?php echo esc_html( $label ); ?></td>
                        <td>
                            <input type="time" name="tsrb_settings[operating_hours][<?php echo esc_attr( $key ); ?>][open]"
                                value="<?php echo isset( $settings['operating_hours'][ $key ]['open'] ) ? esc_attr( $settings['operating_hours'][ $key ]['open'] ) : ''; ?>">
                        </td>
                        <td>
                            <input type="time" name="tsrb_settings[operating_hours][<?php echo esc_attr( $key ); ?>][close]"
                                value="<?php echo isset( $settings['operating_hours'][ $key ]['close'] ) ? esc_attr( $settings['operating_hours'][ $key ]['close'] ) : ''; ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php esc_html_e( 'Blocked Dates / Holidays', 'tribuna-studio-rent-booking' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Add dates when studio is closed. These dates will be unavailable for booking.', 'tribuna-studio-rent-booking' ); ?></p>
        <div id="tsrb-blocked-dates">
            <?php
            if ( ! empty( $settings['blocked_dates'] ) && is_array( $settings['blocked_dates'] ) ) :
                foreach ( $settings['blocked_dates'] as $date ) :
                    ?>
                    <div class="tsrb-blocked-date-row">
                        <input type="date" name="tsrb_settings[blocked_dates][]" value="<?php echo esc_attr( $date ); ?>">
                        <button type="button" class="button tsrb-remove-blocked-date">&times;</button>
                    </div>
                    <?php
                endforeach;
            endif;
            ?>
        </div>
        <p>
            <button type="button" class="button" id="tsrb-add-blocked-date"><?php esc_html_e( 'Add Date', 'tribuna-studio-rent-booking' ); ?></button>
        </p>

        <?php submit_button(); ?>
    </form>
</div>
