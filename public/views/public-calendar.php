<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="tsrb-public-calendar-wrapper">
    <?php if ( ! empty( $studios ) ) : ?>
        <div class="tsrb-public-studio-select">
            <label for="tsrb-public-studio-id"><?php esc_html_e( 'Studio', 'tribuna-studio-rent-booking' ); ?></label>
            <select id="tsrb-public-studio-id">
                <?php foreach ( $studios as $studio ) : ?>
                    <option value="<?php echo esc_attr( $studio->id ); ?>"><?php echo esc_html( $studio->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div id="tsrb-public-calendar"></div>

    <div class="tsrb-calendar-legend">
        <span class="tsrb-legend-item tsrb-legend-available"><?php esc_html_e( 'Green: Fully Available', 'tribuna-studio-rent-booking' ); ?></span>
        <span class="tsrb-legend-item tsrb-legend-partial"><?php esc_html_e( 'Yellow: Partially Booked', 'tribuna-studio-rent-booking' ); ?></span>
        <span class="tsrb-legend-item tsrb-legend-full"><?php esc_html_e( 'Red: Fully Booked', 'tribuna-studio-rent-booking' ); ?></span>
    </div>
</div>
