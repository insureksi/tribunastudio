<?php
/**
 * Studios list + form.
 *
 * @package Tribuna_Studio_Rent_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$studio_model = new Tribuna_Studio_Model();
$studios      = $studio_model->get_all();

$edit_id   = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
$editing   = null;
$form_mode = $edit_id ? 'edit' : 'add';

if ( $edit_id ) {
    $editing = $studio_model->get( $edit_id );
}

// Parse gallery IDs for preview.
$gallery_ids      = array();
$gallery_ids_attr = '';
if ( $editing && ! empty( $editing->gallery_image_ids ) ) {
    $gallery_ids = array_filter( array_map( 'absint', explode( ',', $editing->gallery_image_ids ) ) );
    if ( ! empty( $gallery_ids ) ) {
        $gallery_ids_attr = implode( ',', $gallery_ids );
    }
}
?>
<div class="wrap tsrb-admin-page">
    <h1 class="wp-heading-inline">
        <?php esc_html_e( 'Studios', 'tribuna-studio-rent-booking' ); ?>
    </h1>

    <?php if ( isset( $_GET['tsrb_msg'] ) && 'studio_saved' === $_GET['tsrb_msg'] ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Studio saved successfully.', 'tribuna-studio-rent-booking' ); ?></p>
        </div>
    <?php elseif ( isset( $_GET['tsrb_msg'] ) && 'studio_deleted' === $_GET['tsrb_msg'] ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Studio deleted.', 'tribuna-studio-rent-booking' ); ?></p>
        </div>
    <?php elseif ( isset( $_GET['tsrb_msg'] ) && 'studio_name_required' === $_GET['tsrb_msg'] ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e( 'Studio name is required.', 'tribuna-studio-rent-booking' ); ?></p>
        </div>
    <?php elseif ( isset( $_GET['tsrb_msg'] ) && 'studio_price_required' === $_GET['tsrb_msg'] ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e( 'Hourly price is required.', 'tribuna-studio-rent-booking' ); ?></p>
        </div>
    <?php endif; ?>

    <hr class="wp-header-end" />

    <div class="tsrb-studios-layout">
        <div class="tsrb-studios-form">
            <h2>
                <?php echo ( 'edit' === $form_mode ) ? esc_html__( 'Edit Studio', 'tribuna-studio-rent-booking' ) : esc_html__( 'Add New Studio', 'tribuna-studio-rent-booking' ); ?>
            </h2>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'tsrb_studios_action', 'tsrb_studios_nonce' ); ?>
                <input type="hidden" name="action" value="tsrb_studios_form" />
                <input type="hidden" name="tsrb_studios_form_action" value="<?php echo ( 'edit' === $form_mode ) ? 'edit' : 'add'; ?>" />
                <?php if ( 'edit' === $form_mode && $editing ) : ?>
                    <input type="hidden" name="studio_id" value="<?php echo (int) $editing->id; ?>" />
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="tsrb-studio-name"><?php esc_html_e( 'Name', 'tribuna-studio-rent-booking' ); ?> <span style="color:red;">*</span></label></th>
                            <td>
                                <input type="text" id="tsrb-studio-name" name="name" class="regular-text" required
                                    value="<?php echo $editing ? esc_attr( $editing->name ) : ''; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tsrb-studio-slug"><?php esc_html_e( 'Slug', 'tribuna-studio-rent-booking' ); ?></label></th>
                            <td>
                                <input type="text" id="tsrb-studio-slug" name="slug" class="regular-text"
                                    value="<?php echo $editing ? esc_attr( $editing->slug ) : ''; ?>" />
                                <p class="description"><?php esc_html_e( 'Optional. If empty, will be generated from the name.', 'tribuna-studio-rent-booking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tsrb-studio-description"><?php esc_html_e( 'Description', 'tribuna-studio-rent-booking' ); ?></label></th>
                            <td>
                                <textarea id="tsrb-studio-description" name="description" rows="4" class="large-text"><?php echo $editing ? esc_textarea( $editing->description ) : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tsrb-studio-status"><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></label></th>
                            <td>
                                <?php $status_val = $editing ? $editing->status : 'active'; ?>
                                <select id="tsrb-studio-status" name="status">
                                    <option value="active" <?php selected( $status_val, 'active' ); ?>><?php esc_html_e( 'Active', 'tribuna-studio-rent-booking' ); ?></option>
                                    <option value="inactive" <?php selected( $status_val, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'tribuna-studio-rent-booking' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tsrb-hourly-price"><?php esc_html_e( 'Hourly Price', 'tribuna-studio-rent-booking' ); ?> <span style="color:red;">*</span></label></th>
                            <td>
                                <input type="number" step="1000" min="0" id="tsrb-hourly-price" name="hourly_price_override" required
                                    value="<?php echo ( $editing && null !== $editing->hourly_price_override ) ? esc_attr( $editing->hourly_price_override ) : ''; ?>" />
                                <p class="description">
                                    <?php esc_html_e( 'Price per hour for this studio (required).', 'tribuna-studio-rent-booking' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Studio Images -->
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Studio Images', 'tribuna-studio-rent-booking' ); ?></label></th>
                            <td>
                                <input type="hidden"
                                       id="tsrb-studio-gallery-ids"
                                       name="gallery_image_ids"
                                       value="<?php echo esc_attr( $gallery_ids_attr ); ?>" />

                                <button type="button" class="button" id="tsrb-studio-gallery-add">
                                    <?php esc_html_e( 'Pilih Gambar', 'tribuna-studio-rent-booking' ); ?>
                                </button>
                                <button type="button" class="button" id="tsrb-studio-gallery-clear">
                                    <?php esc_html_e( 'Hapus Semua', 'tribuna-studio-rent-booking' ); ?>
                                </button>

                                <p class="description">
                                    <?php esc_html_e( 'Pilih satu atau lebih gambar untuk studio ini. Gambar pertama akan digunakan sebagai thumbnail utama.', 'tribuna-studio-rent-booking' ); ?>
                                </p>

                                <div id="tsrb-studio-gallery-preview" class="tsrb-studio-gallery-preview">
                                    <?php
                                    if ( ! empty( $gallery_ids ) ) :
                                        foreach ( $gallery_ids as $img_id ) :
                                            $thumb = wp_get_attachment_image( $img_id, 'thumbnail' );
                                            if ( $thumb ) :
                                                ?>
                                                <div class="tsrb-studio-gallery-item" data-attachment-id="<?php echo (int) $img_id; ?>">
                                                    <?php echo $thumb; ?>
                                                </div>
                                                <?php
                                            endif;
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php
                submit_button(
                    ( 'edit' === $form_mode ) ? __( 'Save Studio', 'tribuna-studio-rent-booking' ) : __( 'Add Studio', 'tribuna-studio-rent-booking' )
                );
                ?>
            </form>
        </div>

        <div class="tsrb-studios-list">
            <h2><?php esc_html_e( 'All Studios', 'tribuna-studio-rent-booking' ); ?></h2>

            <?php if ( empty( $studios ) ) : ?>
                <p><?php esc_html_e( 'No studios found.', 'tribuna-studio-rent-booking' ); ?></p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'tribuna-studio-rent-booking' ); ?></th>
                            <th><?php esc_html_e( 'Name', 'tribuna-studio-rent-booking' ); ?></th>
                            <th><?php esc_html_e( 'Slug', 'tribuna-studio-rent-booking' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'tribuna-studio-rent-booking' ); ?></th>
                            <th><?php esc_html_e( 'Hourly Price', 'tribuna-studio-rent-booking' ); ?></th>
                            <th><?php esc_html_e( 'Images', 'tribuna-studio-rent-booking' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'tribuna-studio-rent-booking' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $studios as $studio ) : ?>
                            <tr>
                                <td><?php echo (int) $studio->id; ?></td>
                                <td><?php echo esc_html( $studio->name ); ?></td>
                                <td><?php echo esc_html( $studio->slug ); ?></td>
                                <td>
                                    <?php
                                    if ( 'active' === $studio->status ) {
                                        esc_html_e( 'Active', 'tribuna-studio-rent-booking' );
                                    } else {
                                        esc_html_e( 'Inactive', 'tribuna-studio-rent-booking' );
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ( null !== $studio->hourly_price_override ) {
                                        echo esc_html( Tribuna_Helpers::format_price( $studio->hourly_price_override ) );
                                    } else {
                                        echo '<span style="color:red;">' . esc_html__( 'Not set', 'tribuna-studio-rent-booking' ) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ( ! empty( $studio->gallery_image_ids ) ) {
                                        $ids = array_filter( array_map( 'absint', explode( ',', $studio->gallery_image_ids ) ) );
                                        if ( ! empty( $ids ) ) {
                                            $first_thumb = wp_get_attachment_image( $ids[0], 'thumbnail' );
                                            if ( $first_thumb ) {
                                                echo $first_thumb;
                                            }
                                        }
                                    } else {
                                        esc_html_e( 'No image', 'tribuna-studio-rent-booking' );
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tsrb-studios&edit=' . (int) $studio->id ) ); ?>">
                                        <?php esc_html_e( 'Edit', 'tribuna-studio-rent-booking' ); ?>
                                    </a>
                                    |
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this studio?', 'tribuna-studio-rent-booking' ) ); ?>');">
                                        <?php wp_nonce_field( 'tsrb_studios_action', 'tsrb_studios_nonce' ); ?>
                                        <input type="hidden" name="action" value="tsrb_studios_form" />
                                        <input type="hidden" name="tsrb_studios_form_action" value="delete" />
                                        <input type="hidden" name="studio_id" value="<?php echo (int) $studio->id; ?>" />
                                        <button type="submit" class="button-link delete">
                                            <?php esc_html_e( 'Delete', 'tribuna-studio-rent-booking' ); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
