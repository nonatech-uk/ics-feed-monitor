<?php defined('ABSPATH') || exit; ?>
<div class="wrap icsfm-wrap">
    <h1><?php echo $platform ? 'Edit Platform' : 'Add New Platform'; ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('icsfm_save_platform'); ?>
        <input type="hidden" name="action" value="icsfm_save_platform">
        <input type="hidden" name="platform_id" value="<?php echo $platform ? (int) $platform->id : 0; ?>">

        <table class="form-table">
            <tr>
                <th scope="row"><label for="name">Platform Name</label></th>
                <td>
                    <input type="text" name="name" id="name" class="regular-text"
                           value="<?php echo $platform ? esc_attr($platform->name) : ''; ?>" required
                           placeholder="e.g. Airbnb, VRBO, Own Website">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sort_order">Sort Order</label></th>
                <td>
                    <input type="number" name="sort_order" id="sort_order" class="small-text"
                           value="<?php echo $platform ? (int) $platform->sort_order : 0; ?>">
                    <p class="description">Lower numbers appear first in dropdowns and lists.</p>
                </td>
            </tr>
        </table>

        <?php submit_button($platform ? 'Update Platform' : 'Add Platform'); ?>
    </form>

    <p><a href="<?php echo esc_url(admin_url('admin.php?page=icsfm-platforms')); ?>">&larr; Back to platforms</a></p>
</div>
