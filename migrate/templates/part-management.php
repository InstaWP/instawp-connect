<?php
/**
 * Migrate template - Management
 */

?>

<div class="nav-item-content management bg-white rounded-md p-6">
    <form class="instawp-form w-full">
        <div class="instawp-form-fields">
			<?php foreach ( InstaWP_Setting::get_management_settings() as $section ) : ?>
				<?php InstaWP_Setting::generate_section( $section ); ?>
			<?php endforeach; ?>
        </div>
    </form>
</div>