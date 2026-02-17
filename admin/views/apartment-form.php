<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1><?php echo $apartment ? 'Edit Apartment' : 'Add New Apartment'; ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('icsfm_save_apartment'); ?>
        <input type="hidden" name="action" value="icsfm_save_apartment">
        <input type="hidden" name="apartment_id" value="<?php echo $apartment ? (int) $apartment->id : 0; ?>">

        <table class="form-table">
            <tr>
                <th scope="row"><label for="name">Apartment Name</label></th>
                <td>
                    <input type="text" name="name" id="name" class="regular-text"
                           value="<?php echo $apartment ? esc_attr($apartment->name) : ''; ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sort_order">Sort Order</label></th>
                <td>
                    <input type="number" name="sort_order" id="sort_order" class="small-text"
                           value="<?php echo $apartment ? (int) $apartment->sort_order : 0; ?>">
                    <p class="description">Lower numbers appear first on the dashboard.</p>
                </td>
            </tr>
        </table>

        <?php submit_button($apartment ? 'Update Apartment' : 'Add Apartment'); ?>
    </form>

    <p><a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-apartments')); ?>">&larr; Back to apartments</a></p>
</div>
